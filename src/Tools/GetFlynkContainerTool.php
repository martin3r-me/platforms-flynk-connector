<?php

namespace Platform\FlynkConnector\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\FlynkConnector\Tools\Concerns\ResolvesFlynkTeam;

class GetFlynkContainerTool implements ToolContract, ToolMetadataContract
{
    use ResolvesFlynkTeam;

    public function getName(): string { return 'flynk-connector.containers.show.GET'; }

    public function getDescription(): string
    {
        return 'GET /flynk-connector/containers/{id} - Ein Container inkl. FLYNK-Meta und letzter Ereignisse.';
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
            $rootTeamId = (int) $resolved['root_team_id'];

            $container = $this->findContainer($rootTeamId, $arguments);
            if (! $container) {
                return ToolResult::error('NOT_FOUND', 'Container nicht gefunden.');
            }
            $container->load('connection');

            $events = $container->events()->orderByDesc('created_at')->take(10)->get()
                ->map(fn ($e) => [
                    'type' => $e->type,
                    'title' => $e->title,
                    'message' => $e->message,
                    'created_at' => $e->created_at->toIso8601String(),
                ])->values()->toArray();

            return ToolResult::success(array_merge(
                $this->serializeContainer($container),
                ['events' => $events]
            ));
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Containers: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['flynk', 'container', 'detail'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
