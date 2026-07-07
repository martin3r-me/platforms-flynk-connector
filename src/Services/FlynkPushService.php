<?php

namespace Platform\FlynkConnector\Services;

use Platform\FlynkConnector\Enums\FlynkPushStatus;
use Platform\FlynkConnector\Models\FlynkContainer;
use Platform\FlynkConnector\Models\FlynkContainerEvent;
use Platform\FlynkConnector\Models\FlynkPush;
use Platform\FlynkConnector\Models\FlynkSyncState;
use Platform\Integrations\Exceptions\FlynkApiException;
use Platform\Integrations\Services\FlynkApiService;

/**
 * Push-Engine: baut den Kontext-Envelope, legt pro Push einen FlynkPush an
 * (eigene UUID), sendet ihn an FLYNK — aber nur, wenn ein Delta vorliegt —
 * und holt später das Feedback (was FLYNK daraus gemacht hat).
 */
class FlynkPushService
{
    /** external_type der Sync-Einheit auf Projekt-/Container-Ebene. */
    private const UNIT = 'project';

    public function __construct(
        protected FlynkContainerService $containers,
        protected FlynkApiService $api,
    ) {}

    /**
     * Pusht den aktuellen Kontext des Containers an FLYNK — delta-bewusst.
     *
     * @return array{skipped: bool, reason?: string, push?: FlynkPush}
     */
    public function push(FlynkContainer $container, bool $force = false): array
    {
        if (! $container->external_id) {
            throw new \RuntimeException('Container ist mit keinem FLYNK-Project verbunden.');
        }

        $context = $this->containers->assembleContext($container);
        $hash = hash('sha256', $this->containers->canonicalJson($context));

        $state = FlynkSyncState::firstOrNew([
            'flynk_container_id' => $container->id,
            'external_type' => self::UNIT,
        ]);

        // Delta-Prüfung: nach dem ersten Push nur bei Änderung erneut senden.
        if (! $force && $state->exists && $state->payload_hash === $hash && $state->status === 'synced') {
            return ['skipped' => true, 'reason' => 'no delta'];
        }

        $push = FlynkPush::create([
            'flynk_container_id' => $container->id,
            'status' => FlynkPushStatus::PENDING,
            'payload_hash' => $hash,
        ]);

        $envelope = $this->containers->buildPushEnvelope($container, $context, $push->uuid);
        $push->update(['payload' => $envelope]);

        $connection = $this->containers->resolveConnection($container);

        try {
            $response = $this->api->pushProjectContext($connection, $container->external_id, $envelope);

            $status = FlynkPushStatus::tryFrom($response['status'] ?? '') ?? FlynkPushStatus::ACCEPTED;
            $push->update(['status' => $status, 'response' => $response, 'sent_at' => now()]);

            $state->fill([
                'team_id' => $container->team_id,
                'external_id' => $container->external_id,
                'payload_hash' => $hash,
                'last_pushed_at' => now(),
                'status' => 'synced',
                'direction' => 'outbound',
                'last_error' => null,
            ])->save();

            $container->update(['last_synced_at' => now()]);

            $this->logEvent($container, 'pushed', 'Kontext gepusht', "Push {$push->uuid} gesendet.", ['push_uuid' => $push->uuid]);
        } catch (FlynkApiException $e) {
            $push->update(['status' => FlynkPushStatus::FAILED, 'response' => $e->toArray()]);

            $state->fill([
                'team_id' => $container->team_id,
                'external_id' => $container->external_id,
                'status' => 'failed',
                'direction' => 'outbound',
                'last_error' => $e->getMessage(),
            ])->save();

            $this->logEvent($container, 'error', 'Push fehlgeschlagen', $e->getMessage(), ['push_uuid' => $push->uuid]);

            throw $e;
        }

        return ['skipped' => false, 'push' => $push];
    }

    /** Holt das Feedback zu einem Push (was FLYNK daraus gemacht hat). */
    public function pullFeedback(FlynkPush $push): FlynkPush
    {
        $container = $push->container;
        $connection = $this->containers->resolveConnection($container);

        $response = $this->api->getPush($connection, $push->uuid);

        $status = FlynkPushStatus::tryFrom($response['status'] ?? '') ?? $push->status;
        $push->update(['status' => $status, 'response' => $response, 'feedback_at' => now()]);

        $count = count($response['results'] ?? []);
        $this->logEvent($container, 'feedback', 'Feedback erhalten', "Push {$push->uuid}: {$count} Ergebnis(se), Status {$status->value}.", ['push_uuid' => $push->uuid]);

        return $push;
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
