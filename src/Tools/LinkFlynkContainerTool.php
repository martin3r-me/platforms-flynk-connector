<?php

namespace Platform\FlynkConnector\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\FlynkConnector\Services\FlynkContainerService;
use Platform\FlynkConnector\Tools\Concerns\ResolvesFlynkTeam;

class LinkFlynkContainerTool implements ToolContract, ToolMetadataContract
{
    use ResolvesFlynkTeam;

    public function getName(): string { return 'flynk-connector.containers.link.POST'; }

    public function getDescription(): string
    {
        return 'POST /flynk-connector/containers/{id}/link - Verknüpft einen Container mit einem bestehenden FLYNK-Project (external_id).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'      => ['type' => 'integer'],
                'container_id' => ['type' => 'integer', 'description' => 'ID des Containers (alternativ uuid).'],
                'uuid'         => ['type' => 'string', 'description' => 'UUID des Containers (alternativ container_id).'],
                'external_id'  => ['type' => 'string', 'description' => 'ERFORDERLICH: FLYNK-Project-UUID.'],
            ],
            'required' => ['external_id'],
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

            if (empty($arguments['external_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'external_id ist erforderlich.');
            }

            app(FlynkContainerService::class)->linkRemote($container, (string) $arguments['external_id']);
            $container->refresh()->load('ownerEntity');

            return ToolResult::success(array_merge(
                $this->serializeContainer($container),
                ['message' => 'Container mit FLYNK-Project verknüpft.']
            ));
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Verknüpfen fehlgeschlagen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['flynk', 'container', 'link'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
