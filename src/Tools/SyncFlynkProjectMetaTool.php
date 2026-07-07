<?php

namespace Platform\FlynkConnector\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\FlynkConnector\Services\FlynkContainerService;
use Platform\FlynkConnector\Tools\Concerns\ResolvesFlynkTeam;

class SyncFlynkProjectMetaTool implements ToolContract, ToolMetadataContract
{
    use ResolvesFlynkTeam;

    public function getName(): string { return 'flynk-connector.containers.meta.POST'; }

    public function getDescription(): string
    {
        return 'POST /flynk-connector/containers/{id}/meta - Zieht die FLYNK-Projekt-Metadaten (GET /api/projects/{uuid}) und cached sie am Container.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'      => ['type' => 'integer'],
                'container_id' => ['type' => 'integer', 'description' => 'ID des Containers (alternativ uuid).'],
                'uuid'         => ['type' => 'string', 'description' => 'UUID des Containers (alternativ container_id).'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) return $resolved['error'];

            $container = $this->findContainer((int) $resolved['root_team_id'], $arguments);
            if (! $container) {
                return ToolResult::error('NOT_FOUND', 'Container nicht gefunden.');
            }

            $meta = app(FlynkContainerService::class)->syncMeta($container);

            return ToolResult::success([
                'container_id' => $container->id,
                'external_id'  => $container->external_id,
                'flynk_meta'   => $meta,
                'message'      => 'FLYNK-Meta aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Meta-Abruf fehlgeschlagen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['flynk', 'container', 'meta'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
