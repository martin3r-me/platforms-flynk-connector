<?php

namespace Platform\FlynkConnector\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\FlynkConnector\Services\FlynkPushService;
use Platform\FlynkConnector\Tools\Concerns\ResolvesFlynkTeam;

class PushFlynkContextTool implements ToolContract, ToolMetadataContract
{
    use ResolvesFlynkTeam;

    public function getName(): string { return 'flynk-connector.containers.push-context.POST'; }

    public function getDescription(): string
    {
        return 'POST /flynk-connector/containers/{id}/push-context - Baut den Kontext-Envelope und pusht ihn (delta-bewusst) an FLYNK. Legt einen Push (eigene UUID) an.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'      => ['type' => 'integer'],
                'container_id' => ['type' => 'integer', 'description' => 'ID des Containers (alternativ uuid).'],
                'uuid'         => ['type' => 'string', 'description' => 'UUID des Containers (alternativ container_id).'],
                'force'        => ['type' => 'boolean', 'description' => 'Auch ohne Delta senden. Default: false.'],
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

            $result = app(FlynkPushService::class)->push($container, (bool) ($arguments['force'] ?? false));

            if ($result['skipped'] ?? false) {
                return ToolResult::success(['skipped' => true, 'reason' => $result['reason'] ?? 'no delta', 'message' => 'Kein Delta — nichts gesendet.']);
            }

            $push = $result['push'];

            return ToolResult::success([
                'skipped' => false,
                'push_uuid' => $push->uuid,
                'status' => $push->status?->value,
                'container_id' => $container->id,
                'message' => 'Kontext an FLYNK gepusht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Push fehlgeschlagen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['flynk', 'container', 'push', 'context'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
