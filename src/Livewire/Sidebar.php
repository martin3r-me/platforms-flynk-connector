<?php

namespace Platform\FlynkConnector\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\FlynkConnector\Models\FlynkContainer;
use Platform\FlynkConnector\Models\FlynkQuestion;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityDimensionBridge;

class Sidebar extends Component
{
    public function render()
    {
        $user = Auth::user();
        $teamId = $user?->currentTeamRelation?->getRootTeam()?->id;

        if (! $teamId) {
            return view('flynk-connector::livewire.sidebar', [
                'entityTypeGroups'     => collect(),
                'unassignedContainers' => collect(),
                'openQuestions'        => 0,
            ]);
        }

        $containers = FlynkContainer::where('team_id', $teamId)->orderBy('name')->get();
        $openQuestions = FlynkQuestion::where('team_id', $teamId)->open()->count();

        // 1. Container ⇄ Knoten auflösen (Dimension-Links, Morph-Alias 'flynk_container').
        $entityItemMap = []; // entity_id => ['containers' => [id, ...]]
        $linkedIds = [];

        try {
            $containerIds = $containers->pluck('id')->all();
            if (! empty($containerIds)) {
                $links = EntityDimensionBridge::linksForLinkables(
                    ['flynk_container', FlynkContainer::class],
                    $containerIds
                );
                foreach ($links as $link) {
                    $entityItemMap[$link->entity_id]['containers'][] = $link->linkable_id;
                    $linkedIds[] = $link->linkable_id;
                }
            }
        } catch (\Throwable $e) {
            // Organization-Modul nicht geladen
        }

        $linkedIds = array_unique($linkedIds);

        // 2. Ancestor-Traversal — Zwischenknoten (Venture, Engagement …) einziehen,
        //    damit der Baum bis zur Wurzel durchgängig ist.
        $directEntityIds = array_keys($entityItemMap);
        if (! empty($directEntityIds)) {
            $directEntities = OrganizationEntity::with('allParents')
                ->whereIn('id', $directEntityIds)
                ->get();

            foreach ($directEntities as $entity) {
                $ancestor = $entity->allParents;
                while ($ancestor) {
                    if (! isset($entityItemMap[$ancestor->id])) {
                        $entityItemMap[$ancestor->id] = [];
                    }
                    $ancestor = $ancestor->allParents;
                }
            }
        }

        // 3. Baum bauen (Root-Entities nach Typ gruppiert).
        $entityTypeGroups = $this->buildGroups($entityItemMap, $containers);

        // 4. Container ohne Verortung → eigene "Nicht verortet"-Ablage.
        $unassignedContainers = $containers
            ->filter(fn ($c) => ! in_array($c->id, $linkedIds))
            ->values();

        return view('flynk-connector::livewire.sidebar', [
            'entityTypeGroups'     => $entityTypeGroups,
            'unassignedContainers' => $unassignedContainers,
            'openQuestions'        => $openQuestions,
        ]);
    }

    /** Baut die nach Entity-Typ gruppierten Root-Bäume. */
    protected function buildGroups(array $entityItemMap, $containers)
    {
        $entityIds = array_keys($entityItemMap);
        if (empty($entityIds)) {
            return collect();
        }

        $entities = OrganizationEntity::with('type')
            ->whereIn('id', $entityIds)
            ->get()
            ->keyBy('id');

        // Parent-Child-Map + Roots (relativ zu den geladenen Entities).
        $childrenMap = [];
        $rootIds = [];
        foreach ($entities as $entity) {
            $parentId = $entity->parent_entity_id;
            if ($parentId && $entities->has($parentId)) {
                $childrenMap[$parentId][] = $entity->id;
            } else {
                $rootIds[] = $entity->id;
            }
        }

        $buildTree = function (int $entityId) use (&$buildTree, $entities, $childrenMap, $entityItemMap, $containers): ?array {
            $entity = $entities->get($entityId);
            if (! $entity) {
                return null;
            }

            $childNodes = collect($childrenMap[$entityId] ?? [])
                ->map(fn ($childId) => $buildTree($childId))
                ->filter();

            // Kinder nach Typ gruppieren (Sortierung nach type.sort_order).
            $childrenByType = $childNodes
                ->groupBy(fn ($child) => $child['type_id'])
                ->map(function ($group) use ($entities) {
                    $first = $group->first();
                    $type = $entities->get($first['entity_id'])?->type;

                    return [
                        'type_id'    => $first['type_id'],
                        'type_name'  => $type?->name ?? 'Sonstige',
                        'type_icon'  => $type?->icon ?? null,
                        'sort_order' => $type?->sort_order ?? 999,
                        'children'   => $group->sortBy('entity_name')->values(),
                    ];
                })
                ->sortBy('sort_order')
                ->values();

            $ownContainers = collect($entityItemMap[$entityId]['containers'] ?? [])
                ->map(fn ($id) => $containers->firstWhere('id', $id))
                ->filter()
                ->sortBy('name')
                ->values();

            $totalItems = $ownContainers->count();
            foreach ($childNodes as $child) {
                $totalItems += $child['total_items'];
            }

            if ($totalItems === 0) {
                return null;
            }

            return [
                'entity_id'        => $entityId,
                'entity_name'      => $entity->name,
                'type_id'          => $entity->type?->id,
                'type_icon'        => $entity->type?->icon,
                'containers'       => $ownContainers,
                'children_by_type' => $childrenByType,
                'total_items'      => $totalItems,
            ];
        };

        $groupedByType = [];
        foreach ($rootIds as $entityId) {
            $entity = $entities->get($entityId);
            if (! $entity || ! $entity->type) {
                continue;
            }

            $tree = $buildTree($entityId);
            if (! $tree) {
                continue;
            }

            $typeId = $entity->type->id;
            $groupedByType[$typeId] ??= [
                'type_id'    => $typeId,
                'type_name'  => $entity->type->name,
                'type_icon'  => $entity->type->icon,
                'sort_order' => $entity->type->sort_order ?? 999,
                'entities'   => [],
            ];
            $groupedByType[$typeId]['entities'][] = $tree;
        }

        return collect($groupedByType)
            ->sortBy('sort_order')
            ->map(function ($group) {
                $group['entities'] = collect($group['entities'])->sortBy('entity_name')->values();
                return $group;
            })
            ->values();
    }
}
