<?php

namespace Platform\FlynkConnector\Organization;

use Illuminate\Database\Eloquent\Builder;
use Platform\Organization\Contracts\EntityLinkProvider;

/**
 * Macht FLYNK-Container über Dimension-Links unter Organisations-Knoten sichtbar
 * (Sidebar-Baum + Kennzahlen) — analog zu ChangeEntityLinkProvider.
 */
class FlynkContainerEntityLinkProvider implements EntityLinkProvider
{
    public function morphAliases(): array
    {
        return ['flynk_container'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'flynk_container' => [
                'label' => 'FLYNK Container',
                'singular' => 'FLYNK Container',
                'icon' => 'arrow-right-left',
                'route' => 'flynk-connector.containers.show',
            ],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        $query->with('connection');
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        return [
            'status' => $model->status?->value ?? null,
            'external_id' => $model->external_id,
            'connection' => $model->connection?->name,
            'last_synced' => $model->last_synced_at?->format('d.m.Y H:i'),
        ];
    }

    public function metadataDisplayRules(): array
    {
        return [
            'status' => ['type' => 'badge', 'label' => 'Status'],
            'external_id' => ['type' => 'text', 'label' => 'FLYNK-Project'],
            'connection' => ['type' => 'text', 'label' => 'Verbindung'],
            'last_synced' => ['type' => 'text', 'label' => 'Letzter Sync'],
        ];
    }

    public function timeTrackableCascades(): array
    {
        return [];
    }

    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $result[$entityId] = [
                'flynk_containers_total' => count($ids),
            ];
        }

        return $result;
    }

    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }
}
