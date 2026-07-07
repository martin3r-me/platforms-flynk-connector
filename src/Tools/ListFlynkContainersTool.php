<?php

namespace Platform\FlynkConnector\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\FlynkConnector\Models\FlynkContainer;
use Platform\FlynkConnector\Tools\Concerns\ResolvesFlynkTeam;
use Platform\Organization\Services\EntityDimensionBridge;

class ListFlynkContainersTool implements ToolContract, ToolMetadataContract
{
    use ResolvesFlynkTeam;

    public function getName(): string { return 'flynk-connector.containers.GET'; }

    public function getDescription(): string
    {
        return 'GET /flynk-connector/containers - Listet FLYNK-Container. Filter: status, entity_id (verorteter Knoten).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'   => ['type' => 'integer'],
                'status'    => ['type' => 'string', 'description' => 'Optional: draft | active | error | unregistered.'],
                'entity_id' => ['type' => 'integer', 'description' => 'Optional: Filter nach verortetem Organisations-Knoten (Dimension-Link).'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) return $resolved['error'];
            $rootTeamId = (int) $resolved['root_team_id'];

            $q = FlynkContainer::query()->where('team_id', $rootTeamId)->with('connection');

            if (! empty($arguments['status'])) {
                $q->where('status', (string) $arguments['status']);
            }
            if (! empty($arguments['entity_id'])) {
                $containerIds = EntityDimensionBridge::linksForEntity((int) $arguments['entity_id'])
                    ->where('linkable_type', 'flynk_container')
                    ->pluck('linkable_id')
                    ->all();
                $q->whereIn('id', $containerIds ?: [0]);
            }

            $items = $q->orderBy('name')->get()->map(fn (FlynkContainer $c) => $this->serializeContainer($c))->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'count' => count($items),
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Container: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['flynk', 'container', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
