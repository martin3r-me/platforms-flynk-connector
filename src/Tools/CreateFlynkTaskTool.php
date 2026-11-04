<?php

namespace Platform\FlynkConnector\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\FlynkConnector\Models\FlynkContainerEvent;
use Platform\FlynkConnector\Services\FlynkContainerService;
use Platform\FlynkConnector\Tools\Concerns\ResolvesFlynkTeam;
use Platform\Integrations\Exceptions\FlynkApiException;
use Platform\Integrations\Services\FlynkApiService;

/**
 * Legt einen Auftrag (FLYNK-Task) am verknüpften FLYNK-Project eines Containers an —
 * über die Team-Verbindung des Containers, also ohne eigenen FLYNK-Zugang des Users.
 */
class CreateFlynkTaskTool implements ToolContract, ToolMetadataContract
{
    use ResolvesFlynkTeam;

    private const TYPES = ['bug', 'question', 'text_change', 'image_update', 'design_change', 'page_edit', 'new_page', 'landing_page',
        'new_section', 'collection_entry', 'blog_post', 'social_post', 'mail_change', 'feature_request', 'change', 'other'];

    public function getName(): string { return 'flynk-connector.tasks.POST'; }

    public function getDescription(): string
    {
        return 'POST /flynk-connector/containers/{id}/tasks - Legt einen Auftrag (FLYNK-Task) am verknüpften FLYNK-Project des Containers an (Brief, Bug, Seitenänderung, …). Container per container_id oder uuid (siehe flynk-connector.containers.GET). Neue Aufträge starten in FLYNK immer im Status new.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'       => ['type' => 'integer'],
                'container_id'  => ['type' => 'integer', 'description' => 'ID des Containers (alternativ uuid).'],
                'uuid'          => ['type' => 'string', 'description' => 'UUID des Containers (alternativ container_id).'],
                'title'         => ['type' => 'string', 'description' => 'ERFORDERLICH: Titel des Auftrags (max. 255 Zeichen).'],
                'description'   => ['type' => 'string', 'description' => 'Beschreibung / Brief (Markdown).'],
                'type'          => ['type' => 'string', 'enum' => self::TYPES, 'description' => 'Art des Auftrags (Default: change). Möglichst spezifisch wählen.'],
                'priority'      => ['type' => 'string', 'enum' => ['low', 'normal', 'high'], 'description' => 'Default: normal.'],
                'target_url'    => ['type' => 'string', 'description' => 'Betroffene Seite.'],
                'reference_url' => ['type' => 'string', 'description' => 'Referenz für Kontext.'],
            ],
            'required' => ['title'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) return $resolved['error'];
            $rootTeamId = (int) $resolved['root_team_id'];

            $title = trim((string) ($arguments['title'] ?? ''));
            if ($title === '') {
                return ToolResult::error('VALIDATION_ERROR', 'title ist erforderlich.');
            }
            if (mb_strlen($title) > 255) {
                return ToolResult::error('VALIDATION_ERROR', 'title darf höchstens 255 Zeichen lang sein.');
            }
            $type = $arguments['type'] ?? 'change';
            if (! in_array($type, self::TYPES, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'Unbekannter type: '.$type);
            }

            $container = $this->findContainer($rootTeamId, $arguments);
            if (! $container) {
                return ToolResult::error('NOT_FOUND', 'Container nicht gefunden.');
            }
            if (! $container->isLinked()) {
                return ToolResult::error('NOT_LINKED', 'Container ist nicht mit einem FLYNK-Project verknüpft.');
            }

            $payload = array_filter([
                'project_id'    => $container->external_id,
                'title'         => $title,
                'description'   => $arguments['description'] ?? null,
                'type'          => $type,
                'priority'      => $arguments['priority'] ?? 'normal',
                'target_url'    => $arguments['target_url'] ?? null,
                'reference_url' => $arguments['reference_url'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            $service = app(FlynkContainerService::class);
            $connection = $service->resolveConnection($container);

            try {
                $response = app(FlynkApiService::class)->post($connection, '/tasks', $payload);
            } catch (FlynkApiException $e) {
                return ToolResult::error('FLYNK_ERROR', 'FLYNK hat den Auftrag abgelehnt: '.$e->getMessage());
            }

            $task = is_array($response['data'] ?? null) ? $response['data'] : $response;
            $externalId = $task['id'] ?? $task['uuid'] ?? null;
            // FLYNK liefert Aufzählungen als { value, label }.
            $status = $task['status'] ?? 'new';
            $status = is_array($status) ? ($status['value'] ?? 'new') : $status;

            FlynkContainerEvent::create([
                'flynk_container_id' => $container->id,
                'user_id' => $context->user?->id,
                'type' => 'task_created',
                'title' => 'Auftrag angelegt',
                'message' => \Illuminate\Support\Str::limit($title, 80),
                'payload' => ['task_external_id' => $externalId, 'type' => $type],
            ]);

            // Lokalen Aufgaben-Stand gleich auffrischen (sonst erst beim stündlichen flynk:pull) — best-effort.
            try {
                $service->syncMeta($container);
            } catch (\Throwable) {
            }

            return ToolResult::success([
                'container_id' => $container->id,
                'project_id' => $container->external_id,
                'task_id' => $externalId,
                'title' => $task['title'] ?? $title,
                'status' => $status,
                'url' => $task['url'] ?? null,
                'message' => 'Auftrag an FLYNK angelegt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Auftrag anlegen fehlgeschlagen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['flynk', 'tasks', 'auftrag', 'brief', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
