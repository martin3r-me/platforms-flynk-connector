<?php

namespace Platform\FlynkConnector\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\FlynkConnector\Services\FlynkContainerService;
use Platform\FlynkConnector\Tools\Concerns\ResolvesFlynkTeam;

class UnregisterFlynkContainerTool implements ToolContract, ToolMetadataContract
{
    use ResolvesFlynkTeam;

    public function getName(): string { return 'flynk-connector.containers.unregister.POST'; }

    public function getDescription(): string
    {
        return 'POST /flynk-connector/containers/{id}/unregister - "Abmelden": entfernt das FLYNK-Project (DELETE /api/projects/{uuid}) und entkoppelt den Container.';
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

            app(FlynkContainerService::class)->unregister($container);
            $container->refresh();

            return ToolResult::success(array_merge(
                $this->serializeContainer($container),
                ['message' => 'Container von FLYNK abgemeldet.']
            ));
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Abmelden fehlgeschlagen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['flynk', 'container', 'unregister', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
