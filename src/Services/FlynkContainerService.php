<?php

namespace Platform\FlynkConnector\Services;

use Illuminate\Support\Facades\Log;
use Platform\FlynkConnector\Enums\FlynkContainerStatus;
use Platform\FlynkConnector\Models\FlynkContainer;
use Platform\FlynkConnector\Models\FlynkContainerEvent;
use Platform\Integrations\Exceptions\FlynkApiException;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Services\FlynkApiService;
use Platform\Integrations\Services\FlynkIntegrationService;

/**
 * Orchestriert die drei Container-Verben Richtung FLYNK:
 *   anlegen (createRemote), update mit Daten (pushUpdate), abmelden (unregister).
 * Plus verknüpfen (linkRemote) eines bestehenden FLYNK-Projects.
 *
 * Nutzt die FLYNK-Service-Schicht aus dem integrations-Paket. Was FLYNK aus den
 * Daten macht, ist nicht unser Problem — wir rufen nur die Endpunkte.
 */
class FlynkContainerService
{
    public function __construct(
        protected FlynkApiService $api,
        protected FlynkIntegrationService $integration,
    ) {}

    /**
     * Löst die FLYNK-Connection eines Containers auf: bevorzugt die explizit
     * gewählte, sonst die Team-Default-Connection.
     */
    public function resolveConnection(FlynkContainer $container): IntegrationConnection
    {
        if ($container->integration_connection_id) {
            $connection = IntegrationConnection::find($container->integration_connection_id);
            if ($connection) {
                return $connection;
            }
        }

        $team = $container->team;
        $connection = $team ? $this->integration->getConnectionForTeam($team) : null;

        if (! $connection) {
            throw new \RuntimeException('Keine FLYNK-Verbindung für diesen Container/Team konfiguriert.');
        }

        return $connection;
    }

    /**
     * v1-Payload: nur die Container-eigenen Felder. Dies ist die Naht, an der
     * Phase 2 (Daten-Abo) den vollständigen Knoten-Payload einhängt.
     */
    public function buildPayload(FlynkContainer $container): array
    {
        return [
            'name' => $container->name,
            'description' => $container->description,
            'external_reference' => $container->uuid,
        ];
    }

    /** "anlegen" — neues FLYNK-Project erstellen. */
    public function createRemote(FlynkContainer $container): FlynkContainer
    {
        $connection = $this->resolveConnection($container);

        try {
            $response = $this->api->createProject($connection, $this->buildPayload($container));
            $externalId = $this->extractProjectId($response);

            $container->update([
                'integration_connection_id' => $connection->id,
                'external_id' => $externalId,
                'external_url' => $this->extractProjectUrl($response),
                'status' => FlynkContainerStatus::ACTIVE,
                'last_synced_at' => now(),
            ]);

            $this->logEvent($container, 'created', 'In FLYNK angelegt', "FLYNK-Project {$externalId} erstellt.", $response);
        } catch (FlynkApiException $e) {
            $this->handleError($container, 'Anlegen fehlgeschlagen', $e);
            throw $e;
        }

        return $container;
    }

    /** "verknüpfen" — bestehendes FLYNK-Project anbinden. */
    public function linkRemote(FlynkContainer $container, string $externalId): FlynkContainer
    {
        $connection = $this->resolveConnection($container);
        $externalId = trim($externalId);

        try {
            // Existenz prüfen — wirft bei 404.
            $response = $this->api->getProject($connection, $externalId);

            $container->update([
                'integration_connection_id' => $connection->id,
                'external_id' => $externalId,
                'external_url' => $this->extractProjectUrl($response),
                'status' => FlynkContainerStatus::ACTIVE,
                'last_synced_at' => now(),
            ]);

            $this->logEvent($container, 'linked', 'Mit FLYNK verknüpft', "Bestehendes FLYNK-Project {$externalId} verknüpft.", $response);
        } catch (FlynkApiException $e) {
            $this->handleError($container, 'Verknüpfen fehlgeschlagen', $e);
            throw $e;
        }

        return $container;
    }

    /** "update mit Daten" — FLYNK-Project aktualisieren. */
    public function pushUpdate(FlynkContainer $container): FlynkContainer
    {
        if (! $container->external_id) {
            throw new \RuntimeException('Container ist mit keinem FLYNK-Project verbunden.');
        }

        $connection = $this->resolveConnection($container);

        try {
            $response = $this->api->updateProject($connection, $container->external_id, $this->buildPayload($container));

            $container->update([
                'status' => FlynkContainerStatus::ACTIVE,
                'last_synced_at' => now(),
            ]);

            $this->logEvent($container, 'updated', 'Daten gepusht', 'FLYNK-Project aktualisiert.', $response);
        } catch (FlynkApiException $e) {
            $this->handleError($container, 'Update fehlgeschlagen', $e);
            throw $e;
        }

        return $container;
    }

    /** "abmelden" — FLYNK-Project entfernen und Container entkoppeln. */
    public function unregister(FlynkContainer $container): FlynkContainer
    {
        if (! $container->external_id) {
            throw new \RuntimeException('Container ist mit keinem FLYNK-Project verbunden.');
        }

        $connection = $this->resolveConnection($container);
        $formerId = $container->external_id;

        try {
            $this->api->deleteProject($connection, $formerId);
        } catch (FlynkApiException $e) {
            $this->handleError($container, 'Abmelden fehlgeschlagen', $e);
            throw $e;
        }

        $metadata = $container->metadata ?? [];
        $metadata['former_external_id'] = $formerId;

        $container->update([
            'external_id' => null,
            'external_url' => null,
            'status' => FlynkContainerStatus::UNREGISTERED,
            'metadata' => $metadata,
            'last_synced_at' => now(),
        ]);

        $this->logEvent($container, 'unregistered', 'Abgemeldet', "FLYNK-Project {$formerId} abgemeldet.");

        return $container;
    }

    /**
     * Zieht die FLYNK-Projekt-Metadaten (GET /api/projects/{uuid}) und cached
     * eine kuratierte Auswahl unter metadata['flynk']. Gibt die Meta zurück.
     */
    public function syncMeta(FlynkContainer $container): array
    {
        if (! $container->external_id) {
            throw new \RuntimeException('Container ist mit keinem FLYNK-Project verbunden.');
        }

        $connection = $this->resolveConnection($container);

        try {
            $response = $this->api->getProject($connection, $container->external_id);
        } catch (FlynkApiException $e) {
            $this->handleError($container, 'Meta-Abruf fehlgeschlagen', $e);
            throw $e;
        }

        $project = $response['data'] ?? $response;
        $meta = $this->buildMeta($project);

        $metadata = $container->metadata ?? [];
        $metadata['flynk'] = $meta;

        $container->update([
            'metadata' => $metadata,
            'external_url' => $meta['production_url'] ?? $container->external_url,
            'last_synced_at' => now(),
        ]);

        $this->logEvent($container, 'meta', 'Meta aktualisiert', 'FLYNK-Projekt-Metadaten abgerufen.', $meta);

        return $meta;
    }

    /** Kuratierte Meta-Auswahl aus dem FLYNK-Project-Datensatz. */
    protected function buildMeta(array $project): array
    {
        $stack = $project['stack'] ?? null;
        if (is_string($stack)) {
            $decoded = json_decode($stack, true);
            $stack = is_array($decoded) ? $decoded : $stack;
        }

        return [
            'name'           => $project['name'] ?? null,
            'client_name'    => $project['client_name'] ?? null,
            'agency'         => $project['agency'] ?? null,
            'status'         => $project['status'] ?? null,
            'production_url' => $project['production_url'] ?? ($project['url'] ?? null),
            'github_repo'    => $project['github_repo'] ?? null,
            'forge_server'   => $project['forge_server'] ?? null,
            'timezone'       => $project['timezone'] ?? null,
            'stack'          => $stack,
            'updated_at'     => $project['updated_at'] ?? null,
            'fetched_at'     => now()->toIso8601String(),
        ];
    }

    /** Verbindungstest über die Container-Connection. */
    public function testConnection(FlynkContainer $container): array
    {
        $connection = $this->resolveConnection($container);
        $result = $this->integration->testConnection($connection);

        $this->logEvent(
            $container,
            $result['success'] ? 'test' : 'error',
            'Verbindungstest',
            $result['message'] ?? null
        );

        return $result;
    }

    // =========================================================================
    // INTERN
    // =========================================================================

    protected function extractProjectId(array $response): ?string
    {
        return $response['data']['id']
            ?? $response['id']
            ?? $response['data']['uuid']
            ?? $response['uuid']
            ?? null;
    }

    protected function extractProjectUrl(array $response): ?string
    {
        return $response['data']['url'] ?? $response['url'] ?? null;
    }

    protected function handleError(FlynkContainer $container, string $title, FlynkApiException $e): void
    {
        $container->update(['status' => FlynkContainerStatus::ERROR]);
        $this->logEvent($container, 'error', $title, $e->getMessage(), $e->toArray());

        Log::warning('FLYNK Container-Aktion fehlgeschlagen', [
            'container_id' => $container->id,
            'title' => $title,
            'error' => $e->getMessage(),
        ]);
    }

    protected function logEvent(FlynkContainer $container, string $type, string $title, ?string $message = null, ?array $payload = null): void
    {
        FlynkContainerEvent::create([
            'flynk_container_id' => $container->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'payload' => $payload,
        ]);
    }
}
