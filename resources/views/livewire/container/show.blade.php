<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    @php
        $statusVariant = in_array($container->status->color(), ['success', 'danger', 'warning', 'info']) ? $container->status->color() : 'neutral';
    @endphp

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'FLYNK Container', 'href' => route('flynk-connector.containers.index')],
            ['label' => $container->name],
        ]">
            <x-slot name="left">
                @if($this->isDirty)
                    <x-nx-button variant="ghost" size="sm" wire:click="loadForm">Abbrechen</x-nx-button>
                    <x-nx-button variant="primary" size="sm" wire:click="save">Speichern</x-nx-button>
                @endif
            </x-slot>

            <x-nx-badge :variant="$statusVariant" dot>{{ $container->status->label() }}</x-nx-badge>
        </x-ui-page-actionbar>
    </x-slot>

    {{-- Left sidebar: Meta --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Container" :defaultOpen="true" side="left">
            <div class="p-4 space-y-4 text-xs">
                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-[color:var(--nx-faint)] mb-1">Verortung</div>
                    @forelse($container->linkedEntities() as $entity)
                        <div class="flex items-center gap-1.5 text-[color:var(--nx-text)]">
                            @svg('heroicon-o-building-office', 'w-3.5 h-3.5 text-[color:var(--nx-faint)]')
                            <span>{{ $entity->name }}</span>
                        </div>
                    @empty
                        <span class="text-[color:var(--nx-faint)]">Kein Knoten</span>
                    @endforelse
                </div>
                <div class="border-t border-[color:var(--nx-line)] pt-3">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-[color:var(--nx-faint)] mb-1">FLYNK-Verbindung</div>
                    <div class="text-[color:var(--nx-text)]">{{ $container->connection?->name ?? 'Team-Standard' }}</div>
                </div>
                <div class="border-t border-[color:var(--nx-line)] pt-3">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-[color:var(--nx-faint)] mb-1">FLYNK-Project</div>
                    <div class="text-[color:var(--nx-muted)] break-all tabular-nums">{{ $container->external_id ?? 'nicht verbunden' }}</div>
                </div>
                @if($container->external_url)
                    <div class="border-t border-[color:var(--nx-line)] pt-3">
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-[color:var(--nx-faint)] mb-1">Live-Website</div>
                        <a href="{{ $container->external_url }}" target="_blank" rel="noopener" class="text-[color:var(--nx-accent)] hover:underline inline-flex items-center gap-1 break-all">
                            @svg('heroicon-o-globe-alt', 'w-3 h-3 flex-shrink-0') {{ $container->external_url }}
                        </a>
                    </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Right activity sidebar --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivität" icon="heroicon-o-bolt" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-3">
                @forelse($this->events as $event)
                    <div class="flex gap-2.5 text-xs">
                        <div class="flex-shrink-0 mt-0.5">
                            @svg($event->icon(), 'w-4 h-4 text-[rgb(var(--ui-' . $event->color() . '-rgb))]')
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-[color:var(--nx-text)]">{{ $event->title }}</p>
                            @if($event->message)
                                <p class="text-[11px] text-[color:var(--nx-muted)] mt-0.5 break-words">{{ $event->message }}</p>
                            @endif
                            <div class="flex items-center gap-2 text-[color:var(--nx-faint)] mt-0.5 tabular-nums">
                                <span>{{ $event->created_at->format('d.m. H:i') }}</span>
                                @if($event->user)<span>&middot; {{ $event->user->name }}</span>@endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-[color:var(--nx-faint)] text-center py-4">Noch keine Ereignisse.</p>
                @endforelse
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Main content --}}
    <x-ui-page-container>
        @php
            $flynkMeta = $container->metadata['flynk'] ?? [];
            $comp = $this->completeness;
            $totalTasks = $flynkMeta['total_tasks'] ?? null;
            $goLiveRaw = $flynkMeta['went_live_at'] ?? ($flynkMeta['go_live_at'] ?? null);
            $goLive = $goLiveRaw ? \Illuminate\Support\Carbon::parse($goLiveRaw)->format('d.m.Y') : null;
            $devUrl = $flynkMeta['dev_url'] ?? null;
            $repoUrl = $flynkMeta['github_repo'] ?? null;
            $wh = $flynkMeta['website_health'] ?? null;
            $whMap = ['healthy' => ['success', 'Gesund'], 'warning' => ['warning', 'Warnung'], 'critical' => ['danger', 'Kritisch']];
            [$whVariant, $whLabel] = $whMap[$wh] ?? ['neutral', 'unbekannt'];
        @endphp

        <div class="py-6 max-w-4xl space-y-5">

            {{-- ═══ Header ═══ --}}
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-[11px] text-[color:var(--nx-faint)] mb-1.5 flex-wrap tabular-nums">
                            @foreach($container->linkedEntities() as $entity)
                                <span class="inline-flex items-center gap-1">@svg('heroicon-o-building-office', 'w-3 h-3') {{ $entity->name }}</span>
                                <span class="opacity-50">·</span>
                            @endforeach
                            @if($container->external_id)
                                <span class="inline-flex items-center gap-1">@svg('heroicon-o-link', 'w-3 h-3') {{ \Illuminate\Support\Str::limit($container->external_id, 20) }}</span>
                            @endif
                        </div>
                        <h1 class="text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $container->name }}</h1>
                        <div class="flex items-center gap-3 mt-1.5 text-xs flex-wrap">
                            @if($container->external_url)
                                <a href="{{ $container->external_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[color:var(--nx-accent)] font-medium hover:underline">
                                    @svg('heroicon-o-globe-alt', 'w-3.5 h-3.5') Website
                                </a>
                            @endif
                            @if($devUrl)
                                <a href="{{ $devUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[color:var(--nx-muted)] hover:underline">
                                    @svg('heroicon-o-beaker', 'w-3.5 h-3.5') Dev / Staging
                                </a>
                            @endif
                            @if($repoUrl)
                                <a href="{{ $repoUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[color:var(--nx-muted)] hover:underline">
                                    @svg('heroicon-o-code-bracket', 'w-3.5 h-3.5') Repo
                                </a>
                            @endif
                        </div>
                    </div>
                    <x-nx-badge :variant="$statusVariant" dot>{{ $container->status->label() }}</x-nx-badge>
                </div>

                {{-- Stat strip --}}
                <x-nx-stat-grid :cols="4">
                    <x-nx-stat label="Offene Aufgaben" :value="$flynkMeta['open_tasks'] ?? '—'" :hint="$totalTasks !== null ? 'von '.$totalTasks.' gesamt' : null" icon="heroicon-o-clipboard-document-check" />
                    <x-nx-stat label="Füllgrad" :value="$comp['score'].'%'" :hint="data_get($comp, 'gates.'.$comp['stage'].'.label')" icon="heroicon-o-clipboard-document-check" accent="var(--nx-accent)" />
                    <x-nx-stat label="Website" :value="$whLabel" :hint="$goLive ? 'Go-Live '.$goLive : null" icon="heroicon-o-heart" />
                    <x-nx-stat label="Letzter Sync" :value="$container->last_synced_at?->diffForHumans() ?? '—'" :hint="$container->last_synced_at?->format('d.m. H:i')" icon="heroicon-o-clock" />
                </x-nx-stat-grid>

                {{-- Actions --}}
                <div class="flex flex-wrap items-center gap-2">
                    @if($container->isLinked())
                        <x-nx-button variant="primary" size="sm" wire:click="pushNow" wire:loading.attr="disabled">
                            @svg('heroicon-o-paper-airplane', 'w-4 h-4') Kontext an FLYNK pushen
                        </x-nx-button>
                        <x-nx-button variant="secondary" size="sm" wire:click="syncMeta" wire:loading.attr="disabled">
                            @svg('heroicon-o-arrow-down-tray', 'w-4 h-4') Meta aktualisieren
                        </x-nx-button>
                        <x-nx-button variant="secondary" size="sm" wire:click="pushUpdate" wire:loading.attr="disabled">
                            @svg('heroicon-o-arrow-path', 'w-4 h-4') Projektfelder
                        </x-nx-button>
                        <x-nx-button variant="secondary" size="sm" wire:click="testConnection">
                            @svg('heroicon-o-signal', 'w-4 h-4') Verbindung testen
                        </x-nx-button>
                        <div class="ml-auto">
                            <x-nx-button variant="danger" size="sm" wire:click="unregister" wire:confirm="Container wirklich von FLYNK abmelden? Das FLYNK-Project wird entfernt.">
                                @svg('heroicon-o-x-circle', 'w-4 h-4') Abmelden
                            </x-nx-button>
                        </div>
                    @else
                        <x-nx-button variant="primary" size="sm" wire:click="createRemote" wire:loading.attr="disabled">
                            @svg('heroicon-o-plus', 'w-4 h-4') In FLYNK anlegen
                        </x-nx-button>
                        <div class="flex items-end gap-2 flex-1" style="min-width: 15rem;">
                            <div class="flex-1">
                                <x-nx-input-text name="relinkExternalId" wire:model="relinkExternalId" label="Bestehendes Project verknüpfen (UUID)" placeholder="FLYNK-Project-UUID" size="sm" />
                            </div>
                            <x-nx-button variant="secondary" size="sm" wire:click="relink">
                                @svg('heroicon-o-link', 'w-4 h-4') Verknüpfen
                            </x-nx-button>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ═══ Füllgrad ═══ --}}
            @php
                $gates = $comp['gates'];
                $fields = collect($comp['fields']);
                $missing = $fields->where('satisfied', false);
                $blockers = $missing->whereIn('tier', ['required', 'publishable', 'draft'])
                    ->sortBy(fn ($f) => ['required' => 0, 'publishable' => 1, 'draft' => 2][$f['tier']] ?? 9)->values();
                $idealMissing = $missing->where('tier', 'ideal')->values();
                $moduleLabel = ['brand' => 'Brand', 'seo' => 'SEO', 'crm' => 'CRM', 'commerce' => 'Commerce', 'connector' => 'Connector', '—' => 'ohne Quelle'];
                $nodeLabel = ['self' => 'Website-Knoten', 'customer' => 'Kunden-Knoten', '—' => '—'];
            @endphp

            <x-nx-card>
                <x-nx-section icon="heroicon-o-clipboard-document-check" title="Füllgrad" description="gegen FLYNK-Anforderungsprofil">
                    <x-slot name="action">
                        <div class="text-right leading-tight">
                            <div class="text-lg font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $comp['score'] }}%</div>
                            <div class="text-[10px] text-[color:var(--nx-faint)] tabular-nums">Ideal {{ $comp['ideal']['done'] }}/{{ $comp['ideal']['total'] }}</div>
                        </div>
                    </x-slot>

                    {{-- Tore --}}
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        @foreach($gates as $tier => $g)
                            @php
                                $passed = $g['passed'];
                                [$bg, $fg] = $passed
                                    ? ['rgba(47,158,68,.10)', 'var(--nx-success)']
                                    : ($tier === 'draft' ? ['rgba(232,89,12,.10)', 'var(--nx-warning)'] : ['rgba(224,49,49,.09)', 'var(--nx-danger)']);
                            @endphp
                            <div class="flex items-center gap-2 px-3 py-2 rounded-[8px]" style="background: {{ $bg }};">
                                @svg($passed ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-circle', 'w-4 h-4 flex-shrink-0', ['style' => "color: {$fg}"])
                                <div class="min-w-0">
                                    <div class="text-[11px] font-semibold truncate" style="color: {{ $fg }};">{{ $g['label'] }}</div>
                                    <div class="text-[10px] tabular-nums" style="color: {{ $fg }}; opacity:.75;">{{ $g['done'] }}/{{ $g['total'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Blocker --}}
                    @if($blockers->isNotEmpty())
                        <div class="space-y-1.5">
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-[color:var(--nx-faint)]">Blocker — {{ $blockers->count() }}</div>
                            @foreach($blockers as $f)
                                <div class="flex items-start gap-2.5 py-1.5 border-b border-[color:var(--nx-line)] last:border-0">
                                    <span class="mt-1 w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: {{ $f['tier'] === 'draft' ? 'var(--nx-warning)' : 'var(--nx-danger)' }};"></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-xs font-medium text-[color:var(--nx-text)]">{{ $f['label'] }}</span>
                                            <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-muted)]">{{ $moduleLabel[$f['module']] ?? $f['module'] }} · {{ $nodeLabel[$f['node']] ?? $f['node'] }}</span>
                                        </div>
                                        <p class="text-[11px] text-[color:var(--nx-faint)] mt-0.5 leading-snug">{{ $f['consequence'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex items-center gap-2 text-xs text-[color:var(--nx-success)] font-medium">
                            @svg('heroicon-o-check-badge', 'w-4 h-4') Alle Tore erfüllt — go-live- und entwurfsfähig.
                        </div>
                    @endif

                    {{-- Ideal-Lücken --}}
                    @if($idealMissing->isNotEmpty())
                        <div class="mt-3 pt-3 border-t border-[color:var(--nx-line)]" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-[color:var(--nx-faint)] hover:text-[color:var(--nx-muted)]">
                                Weitere Ideal-Lücken · {{ $idealMissing->count() }}
                                <span class="transition-transform" :class="open ? 'rotate-90' : ''">@svg('heroicon-o-chevron-right', 'w-3 h-3')</span>
                            </button>
                            <div x-show="open" x-collapse x-cloak class="mt-2 flex flex-wrap gap-1.5">
                                @foreach($idealMissing as $f)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[color:var(--nx-accent-soft)] text-[10px] text-[color:var(--nx-muted)]" title="{{ $f['consequence'] }}">
                                        {{ $f['label'] }}
                                        <span class="opacity-50">·</span>
                                        {{ $moduleLabel[$f['module']] ?? $f['module'] }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </x-nx-section>
            </x-nx-card>

            {{-- ═══ Rückfragen ═══ --}}
            <x-nx-panel>
                <x-slot name="actions">
                    <x-nx-button variant="ghost" size="sm" wire:click="pullQuestions" wire:loading.attr="disabled">
                        @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5') abrufen
                    </x-nx-button>
                </x-slot>
                <x-slot name="title">Rückfragen{{ $this->openQuestions->isNotEmpty() ? ' · '.$this->openQuestions->count() : '' }}</x-slot>

                @forelse($this->openQuestions as $question)
                    <div class="border-b border-[color:var(--nx-line)] last:border-0 py-3 first:pt-0">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-sm font-medium text-[color:var(--nx-text)]">{{ $question->title }}</h3>
                            @if($question->priority === 'high')
                                <x-nx-badge variant="danger">Hoch</x-nx-badge>
                            @endif
                        </div>
                        @if($question->description)
                            <p class="text-xs text-[color:var(--nx-muted)] mt-1 whitespace-pre-line leading-relaxed">{{ \Illuminate\Support\Str::limit($question->description, 300) }}</p>
                        @endif

                        @if($answeringId === $question->id)
                            <div class="mt-2 space-y-2">
                                <x-nx-input-textarea name="answerText" wire:model="answerText" label="Antwort an FLYNK" rows="3" placeholder="Deine Antwort…" size="sm" />
                                <div class="flex justify-end gap-2">
                                    <x-nx-button variant="ghost" size="sm" wire:click="cancelAnswer">Abbrechen</x-nx-button>
                                    <x-nx-button variant="primary" size="sm" wire:click="submitAnswer">
                                        @svg('heroicon-o-paper-airplane', 'w-4 h-4') Senden
                                    </x-nx-button>
                                </div>
                            </div>
                        @else
                            <x-nx-button variant="ghost" size="sm" wire:click="startAnswer({{ $question->id }})" class="mt-2">
                                @svg('heroicon-o-chat-bubble-left-right', 'w-3.5 h-3.5') Antworten
                            </x-nx-button>
                        @endif
                    </div>
                @empty
                    <p class="text-xs text-[color:var(--nx-faint)]">Keine offenen Rückfragen — wir sind hier bei nichts am Zug.</p>
                @endforelse
            </x-nx-panel>

            {{-- ═══ Aufgaben ═══ --}}
            @php
                $taskMeta = [
                    'in_progress' => ['info', 'In Arbeit'], 'planned' => ['neutral', 'Geplant'],
                    'new' => ['info', 'Neu'], 'review' => ['warning', 'Review'],
                    'suggested' => ['neutral', 'Vorschlag'], 'on_hold' => ['neutral', 'Pausiert'],
                    'done' => ['success', 'Erledigt'], 'rejected' => ['danger', 'Abgelehnt'],
                ];
                $typeIcons = [
                    'change' => 'heroicon-o-pencil-square', 'feature_request' => 'heroicon-o-sparkles',
                    'bug' => 'heroicon-o-bug-ant', 'question' => 'heroicon-o-question-mark-circle',
                    'new_page' => 'heroicon-o-document-plus', 'blog_post' => 'heroicon-o-pencil',
                    'page_edit' => 'heroicon-o-pencil-square', 'text_change' => 'heroicon-o-language',
                    'image_update' => 'heroicon-o-photo', 'new_section' => 'heroicon-o-squares-plus',
                    'social_post' => 'heroicon-o-megaphone', 'other' => 'heroicon-o-ellipsis-horizontal-circle',
                ];
                $bucketOf = fn ($s) => in_array($s, ['done', 'rejected']) ? 'erledigt' : ($s === 'review' ? 'review' : 'offen');
                $buckets = $this->tasks->groupBy(fn ($t) => $bucketOf($t['status'] ?? ''));
                $cOffen = ($buckets['offen'] ?? collect())->count();
                $cReview = ($buckets['review'] ?? collect())->count();
                $cDone = ($buckets['erledigt'] ?? collect())->count();
                $cAll = $this->tasks->count();
            @endphp

            @if($cAll > 0)
                <x-nx-panel flush x-data="{ tab: '{{ $cOffen > 0 ? 'offen' : 'alle' }}' }">
                    <x-slot name="title">Aufgaben</x-slot>
                    <x-slot name="actions">
                        <div class="flex items-center gap-2">
                            @if(!empty($flynkMeta['flynk_url']))
                                <a href="{{ $flynkMeta['flynk_url'] }}" target="_blank" rel="noopener" class="text-[color:var(--nx-accent)] text-[11px] font-medium hover:underline">in FLYNK ↗</a>
                            @endif
                            <div class="inline-flex items-center gap-0.5 p-0.5 rounded-[8px] bg-[color:var(--nx-bg)] text-xs font-medium">
                                <button @click="tab='offen'" :class="tab==='offen' ? 'bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] shadow-sm' : 'text-[color:var(--nx-faint)]'" class="px-2.5 py-1 rounded-[6px] transition">Offen · {{ $cOffen }}</button>
                                <button @click="tab='review'" :class="tab==='review' ? 'bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] shadow-sm' : 'text-[color:var(--nx-faint)]'" class="px-2.5 py-1 rounded-[6px] transition">Review · {{ $cReview }}</button>
                                <button @click="tab='erledigt'" :class="tab==='erledigt' ? 'bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] shadow-sm' : 'text-[color:var(--nx-faint)]'" class="px-2.5 py-1 rounded-[6px] transition">Erledigt · {{ $cDone }}</button>
                                <button @click="tab='alle'" :class="tab==='alle' ? 'bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] shadow-sm' : 'text-[color:var(--nx-faint)]'" class="px-2.5 py-1 rounded-[6px] transition">Alle · {{ $cAll }}</button>
                            </div>
                        </div>
                    </x-slot>

                    <ul class="divide-y divide-[color:var(--nx-line)]">
                        @foreach($this->tasks as $t)
                            @php
                                $st = $t['status'] ?? 'other';
                                [$stVariant, $stLabel] = $taskMeta[$st] ?? ['neutral', $st];
                                $bucket = $bucketOf($st);
                            @endphp
                            <li x-show="tab==='alle' || tab==='{{ $bucket }}'" class="flex items-center gap-3 px-4 py-2.5">
                                <span class="w-7 h-7 flex-shrink-0 rounded-[6px] bg-[color:var(--nx-bg)] flex items-center justify-center text-[color:var(--nx-muted)]">
                                    @svg($typeIcons[$t['type']] ?? 'heroicon-o-minus-circle', 'w-4 h-4')
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm text-[color:var(--nx-text)] truncate">{{ $t['title'] }}</p>
                                        @if(($t['priority'] ?? null) === 'high')
                                            <x-nx-badge variant="danger">Hoch</x-nx-badge>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 text-[11px] text-[color:var(--nx-faint)] mt-0.5">
                                        <span>{{ $t['type'] }}</span>
                                        @if(!empty($t['assignee']))<span class="opacity-50">·</span><span>{{ $t['assignee'] }}</span>@endif
                                    </div>
                                </div>
                                <x-nx-badge :variant="$stVariant">{{ $stLabel }}</x-nx-badge>
                                @if(!empty($t['created_at']))
                                    <span class="flex-shrink-0 text-[10px] text-[color:var(--nx-faint)] w-11 text-right tabular-nums">{{ \Illuminate\Support\Carbon::parse($t['created_at'])->format('d.m.') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <p x-show="(tab==='offen' && {{ $cOffen }} === 0) || (tab==='review' && {{ $cReview }} === 0) || (tab==='erledigt' && {{ $cDone }} === 0)" x-cloak class="text-xs text-[color:var(--nx-faint)] py-4 text-center">Keine Aufgaben in dieser Ansicht.</p>
                </x-nx-panel>
            @endif

            {{-- ═══ FLYNK-Projekt + Pushes ═══ --}}
            <div class="grid gap-5 @if(!empty($flynkMeta)) md:grid-cols-2 @endif">

                @if(!empty($flynkMeta))
                    <x-nx-panel>
                        <x-slot name="title">FLYNK-Projekt</x-slot>
                        @if(!empty($flynkMeta['fetched_at']))
                            <x-slot name="subtitle">Stand {{ \Illuminate\Support\Carbon::parse($flynkMeta['fetched_at'])->format('d.m.Y H:i') }}</x-slot>
                        @endif

                        <div class="flex flex-wrap gap-2 mb-4">
                            @if(!empty($flynkMeta['production_url']))
                                <x-nx-badge variant="accent" :href="$flynkMeta['production_url']">@svg('heroicon-o-globe-alt', 'w-3.5 h-3.5') Website</x-nx-badge>
                            @endif
                            @if(!empty($flynkMeta['dev_url']))
                                <x-nx-badge :href="$flynkMeta['dev_url']">@svg('heroicon-o-beaker', 'w-3.5 h-3.5') Dev / Staging</x-nx-badge>
                            @endif
                            @if(!empty($flynkMeta['github_repo']))
                                <x-nx-badge :href="$flynkMeta['github_repo']">@svg('heroicon-o-code-bracket', 'w-3.5 h-3.5') Repo</x-nx-badge>
                            @endif
                        </div>

                        <div class="text-xs">
                            @foreach([
                                'client_name' => 'Kunde', 'agency' => 'Agentur', 'status' => 'Status', 'flynk_tier' => 'Tier',
                                'maintenance_interval' => 'Wartung', 'primary_contact' => 'Kontakt', 'timezone' => 'Zeitzone', 'forge_server' => 'Forge-Server',
                            ] as $key => $label)
                                @if(!empty($flynkMeta[$key]))
                                    <div class="flex justify-between gap-2 border-b border-[color:var(--nx-line)] py-1.5 last:border-0">
                                        <span class="text-[color:var(--nx-muted)]">{{ $label }}</span>
                                        <span class="font-medium text-[color:var(--nx-text)] truncate">{{ $flynkMeta[$key] }}</span>
                                    </div>
                                @endif
                            @endforeach
                            @if($goLive)
                                <div class="flex justify-between gap-2 border-b border-[color:var(--nx-line)] py-1.5 last:border-0">
                                    <span class="text-[color:var(--nx-muted)]">Go-Live</span>
                                    <span class="font-medium text-[color:var(--nx-text)] tabular-nums">{{ $goLive }}</span>
                                </div>
                            @endif
                        </div>

                        @if(!empty($flynkMeta['stack']) && is_array($flynkMeta['stack']))
                            <div class="flex flex-wrap gap-1.5 mt-3">
                                @foreach($flynkMeta['stack'] as $tech)
                                    <span class="px-2 py-0.5 rounded-full bg-[color:var(--nx-accent-soft)] text-[10px] text-[color:var(--nx-muted)]">{{ $tech }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if(!empty($flynkMeta['notes']))
                            <div class="mt-3 pt-3 border-t border-[color:var(--nx-line)]" x-data="{ open: false }">
                                <button type="button" @click="open = !open" class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-[color:var(--nx-faint)] hover:text-[color:var(--nx-muted)]">
                                    @svg('heroicon-o-document-text', 'w-3.5 h-3.5') Notizen
                                    <span class="transition-transform" :class="open ? 'rotate-90' : ''">@svg('heroicon-o-chevron-right', 'w-3 h-3')</span>
                                </button>
                                <p x-show="open" x-collapse x-cloak class="mt-2 text-[11px] text-[color:var(--nx-muted)] whitespace-pre-line leading-relaxed">{{ $flynkMeta['notes'] }}</p>
                            </div>
                        @endif
                    </x-nx-panel>
                @endif

                {{-- Pushes --}}
                <x-nx-panel>
                    <x-slot name="title">Pushes</x-slot>
                    @forelse($this->pushes as $push)
                        @php
                            $pVariant = in_array($push->status->color(), ['success', 'danger', 'warning', 'info']) ? $push->status->color() : 'neutral';
                            $pIcon = $push->status->value === 'processed' ? 'heroicon-o-check'
                                : ($push->status->value === 'failed' ? 'heroicon-o-x-mark' : 'heroicon-o-paper-airplane');
                        @endphp
                        <div class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <span class="w-6 h-6 rounded-full bg-[color:var(--nx-bg)] flex items-center justify-center">
                                    @svg($pIcon, 'w-3.5 h-3.5 text-[rgb(var(--ui-' . $push->status->color() . '-rgb))]')
                                </span>
                                @unless($loop->last)<span class="w-px flex-1 bg-[color:var(--nx-line)] mt-1"></span>@endunless
                            </div>
                            <div class="flex-1 min-w-0 pb-4">
                                <div class="flex items-center gap-2">
                                    <x-nx-badge :variant="$pVariant">{{ $push->status->label() }}</x-nx-badge>
                                    <span class="text-[10px] text-[color:var(--nx-faint)] truncate tabular-nums">{{ \Illuminate\Support\Str::limit($push->uuid, 12) }}</span>
                                    <span class="ml-auto text-[10px] text-[color:var(--nx-faint)] tabular-nums">{{ $push->created_at->format('d.m. H:i') }}</span>
                                </div>
                                @php $results = $push->results(); @endphp
                                @if(!empty($results))
                                    <div class="mt-1 space-y-1">
                                        @foreach($results as $r)
                                            <a href="{{ $r['url'] ?? '#' }}" @if(!empty($r['url'])) target="_blank" rel="noopener" @endif class="flex items-center gap-1.5 text-[11px] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-accent)]">
                                                @svg('heroicon-o-document-text', 'w-3 h-3 text-[color:var(--nx-faint)] flex-shrink-0')
                                                <span class="truncate">{{ $r['title'] ?? ($r['type'] ?? 'Ergebnis') }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                                @if(in_array($push->status->value, ['sent','accepted','processing']))
                                    <x-nx-button variant="ghost" size="sm" wire:click="pullFeedback({{ $push->id }})" class="mt-1">Feedback abrufen</x-nx-button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-[color:var(--nx-faint)]">Noch keine Pushes.</p>
                    @endforelse
                </x-nx-panel>
            </div>

            {{-- ═══ Push-Vorschau ═══ --}}
            <x-nx-card x-data="{ open: false }">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-[color:var(--nx-text)]">Push-Vorschau</h2>
                    <x-nx-button variant="ghost" size="sm" @click="open = !open">
                        <span x-text="open ? 'Ausblenden' : 'JSON anzeigen'"></span>
                    </x-nx-button>
                </div>
                <p class="text-[11px] text-[color:var(--nx-faint)] mt-1">Struktur des Envelopes (<code>push</code> + <code>context</code>), den der Connector an FLYNK sendet. Die Kontext-Slices (<code>brand</code>, <code>seo</code>, <code>crm</code>, <code>commerce</code>) liefern die registrierten Quell-Provider pro Knoten.</p>
                <div x-show="open" x-collapse x-cloak class="mt-3">
                    <pre class="text-[10px] leading-relaxed bg-[color:var(--nx-bg)] text-[color:var(--nx-text)] border border-[color:var(--nx-line)] rounded-[8px] p-4 overflow-x-auto tabular-nums">{{ $this->pushPreviewJson }}</pre>
                </div>
            </x-nx-card>

            {{-- ═══ Einstellungen ═══ --}}
            <x-nx-panel>
                <x-slot name="title">Einstellungen</x-slot>
                <div class="space-y-4">
                    <x-nx-input-text name="form.name" wire:model="form.name" label="Name" required size="sm" />
                    <x-nx-input-textarea name="form.description" wire:model="form.description" label="Beschreibung" rows="2" size="sm" />
                    <x-nx-input-select
                        name="form.integration_connection_id"
                        wire:model="form.integration_connection_id"
                        label="FLYNK-Verbindung"
                        :options="$this->flynkConnections->pluck('name', 'id')->toArray()"
                        nullable
                        nullLabel="Team-Standard verwenden"
                        size="sm"
                    />
                </div>
                @if($this->isDirty)
                    <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-[color:var(--nx-line)]">
                        <x-nx-button variant="ghost" size="sm" wire:click="loadForm">Abbrechen</x-nx-button>
                        <x-nx-button variant="primary" size="sm" wire:click="save">Speichern</x-nx-button>
                    </div>
                @endif
            </x-nx-panel>

            {{-- ═══ Danger zone ═══ --}}
            <x-nx-callout variant="danger" title="Gefahrenzone">
                Löscht den Container lokal. Ein verbundenes FLYNK-Project bleibt bestehen — melde es vorher ab, wenn es entfernt werden soll.
                <x-slot name="action">
                    <x-nx-button variant="danger" size="sm" wire:click="delete" wire:confirm="Container wirklich löschen?">
                        @svg('heroicon-o-trash', 'w-4 h-4') Container löschen
                    </x-nx-button>
                </x-slot>
            </x-nx-callout>
        </div>
    </x-ui-page-container>
</x-ui-page>
