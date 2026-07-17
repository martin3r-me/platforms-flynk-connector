<?php

namespace Platform\FlynkConnector\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\FlynkConnector\Models\FlynkQuestion;
use Platform\FlynkConnector\Tools\Concerns\ResolvesFlynkTeam;

class ListFlynkQuestionsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesFlynkTeam;

    public function getName(): string { return 'flynk-connector.questions.GET'; }

    public function getDescription(): string
    {
        return 'GET /flynk-connector/questions - Listet FLYNK-Rückfragen (Tasks type=question), bei denen wir am Zug sind. Default: nur offene.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'      => ['type' => 'integer'],
                'container_id' => ['type' => 'integer', 'description' => 'Optional: nur Rückfragen dieses Containers.'],
                'include_answered' => ['type' => 'boolean', 'description' => 'Auch beantwortete/geschlossene einbeziehen. Default: false.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) return $resolved['error'];
            $rootTeamId = (int) $resolved['root_team_id'];

            $q = FlynkQuestion::query()->where('team_id', $rootTeamId)->with('container');

            if (! empty($arguments['container_id'])) {
                $q->where('flynk_container_id', (int) $arguments['container_id']);
            }
            if (empty($arguments['include_answered'])) {
                $q->open();
            }

            $items = $q->orderByDesc('flynk_created_at')->get()->map(fn (FlynkQuestion $x) => [
                'id'            => $x->id,
                'external_id'   => $x->external_id,
                'container_id'  => $x->flynk_container_id,
                'container'     => $x->container?->name,
                'title'         => $x->title,
                'description'   => $x->description,
                'status'        => $x->status,
                'priority'      => $x->priority,
                'target_url'    => $x->target_url,
                'is_open'       => $x->isOpen(),
                'answered_at'   => $x->answered_at?->toIso8601String(),
                'flynk_created_at' => $x->flynk_created_at?->toIso8601String(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'open_count' => FlynkQuestion::where('team_id', $rootTeamId)->open()->count(),
                'team_id' => $resolved['team_id'],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Rückfragen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['flynk', 'questions', 'inbox'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
