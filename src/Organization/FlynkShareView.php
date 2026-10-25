<?php

namespace Platform\FlynkConnector\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Platform\FlynkConnector\Models\FlynkContainer;
use Platform\Organization\Contracts\ProvidesShareView;

/**
 * Kunden-Tor · „Websites": je verknüpftem FLYNK-Container eine Website mit Zustand,
 * Go-Live, Wartung und den Aufträgen an die Website (FLYNK-Tasks) nach Stand.
 *
 * Quelle ist nur der lokale Stand aus `flynk:pull` (stündlich): metadata['flynk'] und
 * metadata['flynk_tasks'] — keine Live-Abrufe beim Seitenaufruf. Bewusst NICHT nach
 * außen: interne Links (flynk_url, dev, Forge, GitHub), Notizen/Profil/Strategie,
 * Rückfragen (type=question), Vorschläge (suggested) und abgelehnte Aufträge.
 */
class FlynkShareView implements ProvidesShareView
{
    private const HEALTH = [
        'healthy' => ['Online', 'success', 'Alles in Ordnung'],
        'warning' => ['Hinweise', 'warning', 'Läuft, mit Hinweisen'],
        'critical' => ['Störung', 'danger', 'Gestört — wir kümmern uns'],
    ];

    /** Kundentaugliche Stände der Aufträge, in Board-Reihenfolge. */
    private const STATES = [
        'new' => ['Neu', 'slate'],
        'planned' => ['Eingeplant', 'indigo'],
        'in_progress' => ['In Arbeit', 'sky'],
        'review' => ['Zur Abnahme', 'amber'],
        'on_hold' => ['Pausiert', 'rose'],
        'done' => ['Erledigt', 'emerald'],
    ];

    public function shareTypes(): array
    {
        return [
            'flynk_container' => ['label' => 'Websites', 'singular' => 'Website', 'icon' => 'globe-alt', 'slug' => 'website',
                'group' => 'wirkung', 'nav' => 'items', 'order' => 10],
        ];
    }

    public function shareItems(string $alias, Collection $models, array $options): array
    {
        return $models->filter(fn ($c) => $c instanceof FlynkContainer && $c->isLinked())
            ->map(function (FlynkContainer $c) {
                $meta = (array) data_get($c->metadata, 'flynk', []);
                $health = self::HEALTH[$meta['website_health'] ?? ''] ?? null;
                $tasks = $this->tasks($c);

                return [
                    'id' => $c->id,
                    'title' => $this->host($c) ?? $c->name,
                    'subtitle' => $c->name,
                    'state' => 'active',
                    'state_label' => $health[0] ?? 'Live',
                    'updated_at' => $c->last_synced_at,
                    'nav_status' => $health ? ['text' => '● ' . $health[0], 'tone' => $health[1]] : null,
                    'counts' => ['open' => $tasks->whereNotIn('status', ['done'])->count(), 'done' => $tasks->where('status', 'done')->count()],
                ];
            })->values()->all();
    }

    public function shareSummary(string $alias, Collection $models, array $options): ?array
    {
        $sites = $models->filter(fn ($c) => $c instanceof FlynkContainer && $c->isLinked());
        if ($sites->isEmpty()) {
            return null;
        }
        $healths = $sites->map(fn ($c) => data_get($c->metadata, 'flynk.website_health'))->filter();
        $worst = collect(['critical', 'warning', 'healthy'])->first(fn ($h) => $healths->contains($h));
        $tasks = $sites->flatMap(fn ($c) => $this->tasks($c));
        $review = $tasks->where('status', 'review')->count();
        $live = $sites->map(fn ($c) => $this->liveSince($c))->filter()->min();

        return [
            'title' => $sites->count() === 1 ? 'Website · ' . ($this->host($sites->first()) ?? $sites->first()->name) : $sites->count() . ' Websites',
            'status' => $worst ? ['text' => '● ' . self::HEALTH[$worst][0], 'tone' => self::HEALTH[$worst][1]] : null,
            'kpis' => array_values(array_filter([
                $worst ? ['label' => 'Zustand', 'value' => self::HEALTH[$worst][0], 'hint' => self::HEALTH[$worst][2], 'tone' => self::HEALTH[$worst][1]] : null,
                ['label' => 'Offene Aufträge', 'value' => (string) $tasks->whereIn('status', ['new', 'planned', 'in_progress', 'on_hold'])->count(), 'hint' => $tasks->where('status', 'in_progress')->count() . ' in Arbeit'],
                $review ? ['label' => 'Zur Abnahme', 'value' => (string) $review, 'hint' => 'wartet auf Ihr Okay', 'tone' => 'warning'] : null,
                ['label' => 'Erledigt', 'value' => (string) $tasks->where('status', 'done')->count(), 'hint' => 'Aufträge insgesamt'],
                $live ? ['label' => 'Live seit', 'value' => $live->copy()->locale('de')->isoFormat('MMM YYYY'), 'hint' => $live->copy()->locale('de')->diffForHumans(null, true)] : null,
            ])),
        ];
    }

    public function shareDetail(string $alias, Model $model, array $options): ?array
    {
        if (! $model instanceof FlynkContainer || ! $model->isLinked() || ($options['sub'] ?? null) !== null) {
            return null;
        }
        $meta = (array) data_get($model->metadata, 'flynk', []);
        $health = self::HEALTH[$meta['website_health'] ?? ''] ?? null;
        $tasks = $this->tasks($model);
        $live = $this->liveSince($model);
        $goLive = ! $live ? $this->date($meta['go_live_at'] ?? null) : null;
        $url = $meta['production_url'] ?? $model->external_url;

        $columns = collect(self::STATES)->map(fn ($s, $key) => [
            'key' => $key,
            'title' => $s[0],
            'tone' => $s[1],
            'tasks' => $tasks->where('status', $key)->sortByDesc(fn ($t) => $this->date($t['created_at'] ?? null)?->timestamp ?? 0)->values()->all(),
            'muted' => in_array($key, ['new', 'done'], true),
        ])->filter(fn ($col) => $col['tasks'] || in_array($col['key'], ['new', 'in_progress', 'review', 'done'], true))->values()->all();

        return [
            'title' => $this->host($model) ?? $model->name,
            'subtitle' => $model->name !== $this->host($model) ? $model->name : null,
            'description' => null,
            'state' => 'active',
            'state_label' => $health[0] ?? 'Live',
            'progress' => null,
            'facts' => array_values(array_filter([
                ['label' => 'Zustand', 'value' => $health[2] ?? 'unbekannt'],
                $live ? ['label' => 'Live seit', 'value' => $live->copy()->locale('de')->isoFormat('D. MMMM YYYY')]
                    : ($goLive ? ['label' => 'Go-Live geplant', 'value' => $goLive->copy()->locale('de')->isoFormat('D. MMMM YYYY')] : null),
                ! empty($meta['maintenance_interval']) ? ['label' => 'Wartung', 'value' => $this->interval((string) $meta['maintenance_interval'])] : null,
                ['label' => 'Offene Aufträge', 'value' => (string) $tasks->whereNotIn('status', ['done'])->count()],
            ])),
            'tabs' => [[
                'key' => 'auftraege', 'label' => 'Aufträge', 'count' => $tasks->whereNotIn('status', ['done'])->count(), 'order' => 10,
                'view' => 'flynk-connector::share.website',
                'data' => ['url' => $url, 'columns' => $columns, 'synced' => $model->last_synced_at],
            ]],
        ];
    }

    // ── intern ─────────────────────────────────────────────────

    /** Aufträge ohne Interna: keine Rückfragen, Vorschläge oder abgelehnten. */
    protected function tasks(FlynkContainer $c): Collection
    {
        return collect((array) data_get($c->metadata, 'flynk_tasks', []))
            ->filter(fn ($t) => is_array($t) && ($t['type'] ?? null) !== 'question' && isset(self::STATES[$t['status'] ?? '']))
            ->map(fn ($t) => [
                'title' => (string) ($t['title'] ?? ''),
                'type' => $t['type'] ?? null,
                'type_label' => $this->typeLabel($t['type'] ?? null),
                'status' => $t['status'],
                'priority' => $t['priority'] ?? null,
                'edge' => match (true) {
                    ($t['status'] ?? null) === 'done' => 'var(--nx-success)',
                    ($t['status'] ?? null) === 'review' => 'var(--nx-warning)',
                    in_array($t['priority'] ?? null, ['high', 'urgent'], true) => 'var(--nx-danger)',
                    default => 'var(--nx-line-strong)',
                },
                'created_at' => $t['created_at'] ?? null,
                'created' => $this->date($t['created_at'] ?? null),
            ])->values();
    }

    protected function host(FlynkContainer $c): ?string
    {
        $url = data_get($c->metadata, 'flynk.production_url') ?: $c->external_url;
        $host = $url ? parse_url((string) $url, PHP_URL_HOST) : null;

        return $host ? preg_replace('/^www\./', '', $host) : null;
    }

    protected function liveSince(FlynkContainer $c): ?Carbon
    {
        return $this->date(data_get($c->metadata, 'flynk.went_live_at'));
    }

    protected function date($v): ?Carbon
    {
        try {
            return $v ? Carbon::parse($v) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function typeLabel(?string $type): ?string
    {
        return match ($type) {
            'bug' => 'Fehler',
            'content', 'content_request' => 'Inhalt',
            'page_edit', 'page' => 'Seite',
            'feature' => 'Funktion',
            'seo' => 'SEO',
            'maintenance' => 'Wartung',
            null, '' => null,
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    protected function interval(string $v): string
    {
        return match ($v) {
            'weekly' => 'wöchentlich',
            'monthly' => 'monatlich',
            'quarterly' => 'vierteljährlich',
            'yearly', 'annually' => 'jährlich',
            default => $v,
        };
    }
}
