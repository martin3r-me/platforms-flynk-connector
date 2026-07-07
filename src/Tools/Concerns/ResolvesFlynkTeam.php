<?php

namespace Platform\FlynkConnector\Tools\Concerns;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;

trait ResolvesFlynkTeam
{
    /**
     * Resolves a team the user has access to, and derives its root/parent team.
     *
     * @return array{team_id:int|null, root_team_id:int|null, team:Team|null, error:ToolResult|null}
     */
    protected function resolveTeamAndRoot(array $arguments, ToolContext $context): array
    {
        $teamId = $arguments['team_id'] ?? $context->team?->id;
        if ($teamId === 0 || $teamId === '0') {
            $teamId = null;
        }

        if (!$teamId) {
            return [
                'team_id' => null,
                'root_team_id' => null,
                'team' => null,
                'error' => ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.'),
            ];
        }

        $team = Team::find((int)$teamId);
        if (!$team) {
            return [
                'team_id' => (int)$teamId,
                'root_team_id' => null,
                'team' => null,
                'error' => ToolResult::error('TEAM_NOT_FOUND', 'Team nicht gefunden.'),
            ];
        }

        if (!$context->user) {
            return [
                'team_id' => (int)$teamId,
                'root_team_id' => null,
                'team' => $team,
                'error' => ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.'),
            ];
        }

        $userHasAccess = $context->user->teams()->where('teams.id', $team->id)->exists();
        if (!$userHasAccess) {
            return [
                'team_id' => (int)$teamId,
                'root_team_id' => null,
                'team' => $team,
                'error' => ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Team.'),
            ];
        }

        $root = $team->getRootTeam();

        return [
            'team_id' => $team->id,
            'root_team_id' => $root->id,
            'team' => $team,
            'error' => null,
        ];
    }

    /** Findet einen Container im Team per container_id (int) oder uuid. */
    protected function findContainer(int $rootTeamId, array $arguments): ?\Platform\FlynkConnector\Models\FlynkContainer
    {
        $q = \Platform\FlynkConnector\Models\FlynkContainer::where('team_id', $rootTeamId);

        if (! empty($arguments['container_id'])) {
            return $q->find((int) $arguments['container_id']);
        }
        if (! empty($arguments['uuid'])) {
            return $q->where('uuid', (string) $arguments['uuid'])->first();
        }

        return null;
    }

    /** Serialisiert einen Container für Tool-Antworten. */
    protected function serializeContainer(\Platform\FlynkConnector\Models\FlynkContainer $c): array
    {
        $entities = $c->linkedEntities();

        return [
            'id'              => $c->id,
            'uuid'            => $c->uuid,
            'name'            => $c->name,
            'description'     => $c->description,
            'status'          => $c->status?->value,
            'entity_ids'      => $entities->pluck('id')->all(),
            'entity_names'    => $entities->pluck('name')->all(),
            'connection_id'   => $c->integration_connection_id,
            'external_id'     => $c->external_id,
            'external_url'    => $c->external_url,
            'flynk_meta'      => $c->metadata['flynk'] ?? null,
            'last_synced_at'  => $c->last_synced_at?->toIso8601String(),
            'team_id'         => $c->team_id,
        ];
    }
}
