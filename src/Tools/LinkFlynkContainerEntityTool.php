<?php

namespace Platform\FlynkConnector\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\FlynkConnector\Tools\Concerns\ResolvesFlynkTeam;
use Platform\Organization\Services\EntityDimensionBridge;

/**
 * Verortet einen bestehenden Container an einem Organisations-Knoten (Dimension-Link)
 * oder löst die Verortung wieder.
 */
class LinkFlynkContainerEntityTool implements ToolContract, ToolMetadataContract
{
    use ResolvesFlynkTeam;

    public function getName(): string { return 'flynk-connector.containers.link-entity.POST'; }

    public function getDescription(): string
    {
        return 'POST /flynk-connector/containers/{id}/link-entity - Verortet einen Container an einem Organisations-Knoten (action=attach) oder löst die Verortung (action=detach).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'      => ['type' => 'integer'],
                'container_id' => ['type' => 'integer', 'description' => 'ID des Containers (alternativ uuid).'],
                'uuid'         => ['type' => 'string', 'description' => 'UUID des Containers (alternativ container_id).'],
                'entity_id'    => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID des Organisations-Knotens.'],
                'action'       => ['type' => 'string', 'description' => 'attach | detach. Default: attach.'],
            ],
            'required' => ['entity_id'],
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

            $entityId = (int) ($arguments['entity_id'] ?? 0);
            if ($entityId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_id ist erforderlich.');
            }

            $action = $arguments['action'] ?? 'attach';
            if (! in_array($action, ['attach', 'detach'], true)) {
                return ToolResult::error('VALIDATION_ERROR', 'action muss attach oder detach sein.');
            }

            if ($action === 'attach') {
                EntityDimensionBridge::createLink($entityId, 'flynk_container', $container->id);
            } else {
                EntityDimensionBridge::deleteLink($entityId, 'flynk_container', $container->id);
            }

            $container->refresh();

            return ToolResult::success(array_merge(
                $this->serializeContainer($container),
                ['action' => $action, 'message' => $action === 'attach' ? 'Container am Knoten verortet.' : 'Verortung gelöst.']
            ));
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Verortung fehlgeschlagen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['flynk', 'container', 'entity', 'link'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
