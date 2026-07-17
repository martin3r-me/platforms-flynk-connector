<?php

namespace Platform\FlynkConnector\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\FlynkConnector\Services\FlynkQuestionService;
use Platform\FlynkConnector\Tools\Concerns\ResolvesFlynkTeam;

class PullFlynkQuestionsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesFlynkTeam;

    public function getName(): string { return 'flynk-connector.questions.pull.POST'; }

    public function getDescription(): string
    {
        return 'POST /flynk-connector/questions/pull - Zieht FLYNK-Rückfragen (type=question) aus FLYNK ins Team (alle aktiven Container oder einen).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'      => ['type' => 'integer'],
                'container_id' => ['type' => 'integer', 'description' => 'Optional: nur diesen Container abrufen.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) return $resolved['error'];
            $rootTeamId = (int) $resolved['root_team_id'];

            $service = app(FlynkQuestionService::class);

            if (! empty($arguments['container_id'])) {
                $container = $this->findContainer($rootTeamId, ['container_id' => (int) $arguments['container_id']]);
                if (! $container) {
                    return ToolResult::error('NOT_FOUND', 'Container nicht gefunden.');
                }
                $r = $service->pull($container);
                return ToolResult::success(array_merge($r, ['container_id' => $container->id, 'message' => 'Rückfragen abgerufen.']));
            }

            $r = $service->pullForTeam($rootTeamId);

            return ToolResult::success(array_merge($r, ['message' => 'Rückfragen für alle Container abgerufen.']));
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Abruf fehlgeschlagen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['flynk', 'questions', 'pull'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
