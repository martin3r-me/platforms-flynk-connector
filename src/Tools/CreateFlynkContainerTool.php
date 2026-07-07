<?php

namespace Platform\FlynkConnector\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\FlynkConnector\Enums\FlynkContainerStatus;
use Platform\FlynkConnector\Models\FlynkContainer;
use Platform\FlynkConnector\Services\FlynkContainerService;
use Platform\FlynkConnector\Tools\Concerns\ResolvesFlynkTeam;

class CreateFlynkContainerTool implements ToolContract, ToolMetadataContract
{
    use ResolvesFlynkTeam;

    public function getName(): string { return 'flynk-connector.containers.POST'; }

    public function getDescription(): string
    {
        return 'POST /flynk-connector/containers - Legt einen Container an und verbindet ihn mit FLYNK. '
            . 'link_mode: "create" (neues FLYNK-Project), "link" (bestehendes via external_id), "none" (nur lokal).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'                   => ['type' => 'integer'],
                'name'                      => ['type' => 'string', 'description' => 'ERFORDERLICH: Name des Containers.'],
                'description'               => ['type' => 'string'],
                'owner_entity_id'           => ['type' => 'integer', 'description' => 'Optional: Organisations-Knoten (Verortung).'],
                'integration_connection_id' => ['type' => 'integer', 'description' => 'Optional: FLYNK-Verbindung (sonst Team-Standard).'],
                'link_mode'                 => ['type' => 'string', 'description' => 'create | link | none. Default: none.'],
                'external_id'               => ['type' => 'string', 'description' => 'FLYNK-Project-UUID (erforderlich bei link_mode=link).'],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) return $resolved['error'];
            $rootTeamId = (int) $resolved['root_team_id'];

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $mode = $arguments['link_mode'] ?? 'none';
            if (! in_array($mode, ['create', 'link', 'none'], true)) {
                return ToolResult::error('VALIDATION_ERROR', 'link_mode muss create, link oder none sein.');
            }
            if ($mode === 'link' && empty($arguments['external_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'external_id ist bei link_mode=link erforderlich.');
            }

            $container = FlynkContainer::create([
                'team_id'                   => $rootTeamId,
                'user_id'                   => $context->user?->id,
                'name'                      => $name,
                'description'               => ($arguments['description'] ?? null) ?: null,
                'owner_entity_id'           => ! empty($arguments['owner_entity_id']) ? (int) $arguments['owner_entity_id'] : null,
                'integration_connection_id' => ! empty($arguments['integration_connection_id']) ? (int) $arguments['integration_connection_id'] : null,
                'status'                    => FlynkContainerStatus::DRAFT,
            ]);

            $service = app(FlynkContainerService::class);
            $flynkError = null;

            try {
                if ($mode === 'create') {
                    $service->createRemote($container);
                } elseif ($mode === 'link') {
                    $service->linkRemote($container, (string) $arguments['external_id']);
                }
            } catch (\Throwable $e) {
                $flynkError = $e->getMessage();
            }

            $container->refresh()->load('ownerEntity');

            return ToolResult::success(array_merge(
                $this->serializeContainer($container),
                [
                    'link_mode' => $mode,
                    'flynk_error' => $flynkError,
                    'message' => $flynkError
                        ? 'Container lokal angelegt, FLYNK-Aktion fehlgeschlagen.'
                        : 'Container angelegt.',
                ]
            ));
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen des Containers: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['flynk', 'container', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
