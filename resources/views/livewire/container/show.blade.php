<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'FLYNK Container', 'href' => route('flynk-connector.containers.index')],
            ['label' => $container->name],
        ]">
            <x-slot name="left">
                @if($this->isDirty)
                    <x-ui-button variant="secondary" size="xs" wire:click="loadForm">Abbrechen</x-ui-button>
                    <x-ui-button variant="primary" size="xs" wire:click="save">Speichern</x-ui-button>
                @endif
            </x-slot>

            <x-ui-badge :color="$container->status->color()" size="sm">{{ $container->status->label() }}</x-ui-badge>
        </x-ui-page-actionbar>
    </x-slot>

    {{-- Left sidebar: Meta --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Container" :defaultOpen="true" side="left">
            <div class="p-4 space-y-4 text-xs">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400 mb-1">Verortung</div>
                    @forelse($container->linkedEntities() as $entity)
                        <div class="flex items-center gap-1.5 text-gray-700">
                            @svg('heroicon-o-building-office', 'w-3.5 h-3.5 text-gray-400')
                            <span>{{ $entity->name }}</span>
                        </div>
                    @empty
                        <span class="text-gray-400">Kein Knoten</span>
                    @endforelse
                </div>
                <div class="border-t border-gray-200 pt-3">
                    <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400 mb-1">FLYNK-Verbindung</div>
                    <div class="text-gray-700">{{ $container->connection?->name ?? 'Team-Standard' }}</div>
                </div>
                <div class="border-t border-gray-200 pt-3">
                    <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400 mb-1">FLYNK-Project</div>
                    <div class="text-gray-700 break-all" style="font-family: 'JetBrains Mono', monospace;">
                        {{ $container->external_id ?? 'nicht verbunden' }}
                    </div>
                </div>
                @if($container->external_url)
                    <div class="border-t border-gray-200 pt-3">
                        <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400 mb-1">Live-Website</div>
                        <a href="{{ $container->external_url }}" target="_blank" rel="noopener" class="text-[rgb(var(--ui-primary-rgb))] hover:underline inline-flex items-center gap-1 break-all">
                            @svg('heroicon-o-globe-alt', 'w-3 h-3 flex-shrink-0') {{ $container->external_url }}
                        </a>
                    </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Right activity sidebar: Event-Log --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivität" icon="heroicon-o-bolt" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-3">
                @forelse($this->events as $event)
                    <div class="flex gap-2.5 text-xs">
                        <div class="flex-shrink-0 mt-0.5">
                            @svg($event->icon(), 'w-4 h-4 text-[rgb(var(--ui-' . $event->color() . '-rgb))]')
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900">{{ $event->title }}</p>
                            @if($event->message)
                                <p class="text-[11px] text-gray-500 mt-0.5 break-words">{{ $event->message }}</p>
                            @endif
                            <div class="flex items-center gap-2 text-gray-400 mt-0.5">
                                <span style="font-family: 'JetBrains Mono', monospace;">{{ $event->created_at->format('d.m. H:i') }}</span>
                                @if($event->user)
                                    <span>&middot; {{ $event->user->name }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-gray-400 text-center py-4">Noch keine Ereignisse.</p>
                @endforelse
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Main content --}}
    <x-ui-page-container>
        @php
            $flynkMeta = $container->metadata['flynk'] ?? [];
            $completeness = $flynkMeta['context_completeness'] ?? null;
            $pct = $completeness !== null ? (int) round((float) $completeness * (($completeness <= 1) ? 100 : 1)) : null;
            $circ = 126; $ringOffset = $pct !== null ? $circ - ($circ * $pct / 100) : $circ;
            $ctxSub = $pct === null ? '—' : ($pct <= 0 ? 'noch leer' : ($pct >= 100 ? 'vollständig' : 'in Arbeit'));
            $totalTasks = $flynkMeta['total_tasks'] ?? null;
            $goLiveRaw = $flynkMeta['went_live_at'] ?? ($flynkMeta['go_live_at'] ?? null);
            $goLive = $goLiveRaw ? \Illuminate\Support\Carbon::parse($goLiveRaw)->format('d.m.Y') : null;
            $statusVal = $container->status->value;
            [$stDot, $stBg] = $statusVal === 'active' ? ['#059669', 'rgba(16,185,129,.12)'] : ['#64748b', 'rgba(100,116,139,.12)'];
            $devUrl = $flynkMeta['dev_url'] ?? null;
            $repoUrl = $flynkMeta['github_repo'] ?? null;
        @endphp

        <div class="py-6 max-w-4xl space-y-5">

            {{-- ═══ Hero ═══ --}}
            <div class="relative overflow-hidden border border-black/5 bg-white/70 backdrop-blur-sm p-6"
                 style="border-radius: 1.5rem;">
                {{-- Deko-Glow --}}
                <span class="absolute pointer-events-none" aria-hidden="true"
                      style="top: -5rem; right: -5rem; width: 15rem; height: 15rem; border-radius: 9999px; background: radial-gradient(circle, rgb(var(--ui-primary-rgb) / 0.18), transparent 70%);"></span>

                <div class="relative flex items-start justify-between gap-4 flex-wrap">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-[11px] text-gray-400 mb-2 flex-wrap" style="font-family: 'JetBrains Mono', monospace;">
                            @foreach($container->linkedEntities() as $entity)
                                <span class="inline-flex items-center gap-1">@svg('heroicon-o-building-office', 'w-3 h-3') {{ $entity->name }}</span>
                                <span class="text-gray-300">·</span>
                            @endforeach
                            @if($container->external_id)
                                <span class="inline-flex items-center gap-1">@svg('heroicon-o-link', 'w-3 h-3') {{ \Illuminate\Support\Str::limit($container->external_id, 20) }}</span>
                            @endif
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight text-[color:var(--ui-text)]">{{ $container->name }}</h1>
                        <div class="flex items-center gap-3 mt-2 text-xs flex-wrap">
                            @if($container->external_url)
                                <a href="{{ $container->external_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[rgb(var(--ui-primary-rgb))] font-semibold hover:underline">
                                    @svg('heroicon-o-globe-alt', 'w-3.5 h-3.5') Website
                                </a>
                            @endif
                            @if($devUrl)
                                <a href="{{ $devUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-gray-500 hover:underline">
                                    @svg('heroicon-o-beaker', 'w-3.5 h-3.5') Dev / Staging
                                </a>
                            @endif
                            @if($repoUrl)
                                <a href="{{ $repoUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-gray-500 hover:underline">
                                    @svg('heroicon-o-code-bracket', 'w-3.5 h-3.5') Repo
                                </a>
                            @endif
                        </div>
                    </div>
                    <span class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
                          style="background: {{ $stBg }}; color: {{ $stDot }};">
                        <span class="w-1.5 h-1.5 rounded-full" style="background: {{ $stDot }};"></span>
                        {{ $container->status->label() }}
                    </span>
                </div>

                {{-- Stat strip --}}
                <div class="relative grid grid-cols-2 md:grid-cols-4 gap-3 mt-6">
                    <div class="border border-black/5 p-4" style="border-radius: 1rem; background: rgba(255,255,255,.6);">
                        <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400" style="font-family: 'JetBrains Mono', monospace;">Offene Aufgaben</div>
                        <div class="text-2xl font-bold text-[rgb(var(--ui-primary-rgb))] mt-1" style="font-family: 'JetBrains Mono', monospace;">{{ $flynkMeta['open_tasks'] ?? '—' }}</div>
                        <div class="text-[10px] text-gray-400 mt-0.5" style="font-family: 'JetBrains Mono', monospace;">{{ $totalTasks !== null ? 'von '.$totalTasks.' gesamt' : ' ' }}</div>
                    </div>
                    <div class="border border-black/5 p-4 flex items-center gap-3" style="border-radius: 1rem; background: rgba(255,255,255,.6);">
                        <svg width="50" height="50" viewBox="0 0 50 50" class="flex-shrink-0">
                            <circle cx="25" cy="25" r="20" fill="none" stroke="#E5E7EB" stroke-width="5"/>
                            <circle cx="25" cy="25" r="20" fill="none" stroke="rgb(var(--ui-primary-rgb))" stroke-width="5" stroke-linecap="round"
                                    stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $ringOffset }}" transform="rotate(-90 25 25)"
                                    style="transition: stroke-dashoffset .6s ease;" />
                        </svg>
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400" style="font-family: 'JetBrains Mono', monospace;">Context</div>
                            <div class="text-xl font-bold" style="font-family: 'JetBrains Mono', monospace;">{{ $pct !== null ? $pct.'%' : '—' }}</div>
                            <div class="text-[10px] text-gray-400" style="font-family: 'JetBrains Mono', monospace;">{{ $ctxSub }}</div>
                        </div>
                    </div>
                    <div class="border border-black/5 p-4" style="border-radius: 1rem; background: rgba(255,255,255,.6);">
                        <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400" style="font-family: 'JetBrains Mono', monospace;">Website</div>
                        @php
                            $wh = $flynkMeta['website_health'] ?? null;
                            $whMap = ['healthy' => ['success', 'Gesund'], 'warning' => ['warning', 'Warnung'], 'critical' => ['danger', 'Kritisch']];
                            [$whColor, $whLabel] = $whMap[$wh] ?? ['muted', 'unbekannt'];
                        @endphp
                        <div class="mt-2">
                            <x-ui-badge :color="$whColor" size="sm">{{ $whLabel }}</x-ui-badge>
                        </div>
                        <div class="text-[10px] text-gray-400 mt-1.5" style="font-family: 'JetBrains Mono', monospace;">{{ $goLive ? 'Go-Live '.$goLive : ' ' }}</div>
                    </div>
                    <div class="border border-black/5 p-4" style="border-radius: 1rem; background: rgba(255,255,255,.6);">
                        <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400" style="font-family: 'JetBrains Mono', monospace;">Letzter Sync</div>
                        <div class="text-sm font-semibold text-gray-700 mt-2" style="font-family: 'JetBrains Mono', monospace;">{{ $container->last_synced_at?->diffForHumans() ?? '—' }}</div>
                        <div class="text-[10px] text-gray-400 mt-0.5" style="font-family: 'JetBrains Mono', monospace;">{{ $container->last_synced_at?->format('d.m. H:i') ?? ' ' }}</div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="relative flex flex-wrap items-center gap-2 mt-5">
                    @if($container->isLinked())
                        <x-ui-button variant="primary" size="sm" wire:click="pushNow" wire:loading.attr="disabled">
                            @svg('heroicon-o-paper-airplane', 'w-4 h-4')
                            Kontext an FLYNK pushen
                        </x-ui-button>
                        <x-ui-button variant="secondary" size="sm" wire:click="syncMeta" wire:loading.attr="disabled">
                            @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                            Meta aktualisieren
                        </x-ui-button>
                        <x-ui-button variant="secondary" size="sm" wire:click="pushUpdate" wire:loading.attr="disabled">
                            @svg('heroicon-o-arrow-path', 'w-4 h-4')
                            Projektfelder
                        </x-ui-button>
                        <x-ui-button variant="secondary" size="sm" wire:click="testConnection">
                            @svg('heroicon-o-signal', 'w-4 h-4')
                            Verbindung testen
                        </x-ui-button>
                        <div class="ml-auto">
                            <x-ui-button variant="danger" size="sm" wire:click="unregister" wire:confirm="Container wirklich von FLYNK abmelden? Das FLYNK-Project wird entfernt.">
                                @svg('heroicon-o-x-circle', 'w-4 h-4')
                                Abmelden
                            </x-ui-button>
                        </div>
                    @else
                        <x-ui-button variant="primary" size="sm" wire:click="createRemote" wire:loading.attr="disabled">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            In FLYNK anlegen
                        </x-ui-button>
                        <div class="flex items-end gap-2 flex-1" style="min-width: 15rem;">
                            <div class="flex-1">
                                <x-ui-input-text wire:model="relinkExternalId" label="Bestehendes Project verknüpfen (UUID)" placeholder="FLYNK-Project-UUID" size="sm" />
                            </div>
                            <x-ui-button variant="secondary" size="sm" wire:click="relink">
                                @svg('heroicon-o-link', 'w-4 h-4')
                                Verknüpfen
                            </x-ui-button>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ═══ Rückfragen (inbound) ═══ --}}
            <div class="rounded-2xl border border-black/5 bg-white/70 backdrop-blur-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--ui-text)]" style="font-family: 'JetBrains Mono', monospace;">
                        Rückfragen
                        @if($this->openQuestions->isNotEmpty())<span class="text-[rgb(var(--ui-warning-rgb))]"> ({{ $this->openQuestions->count() }})</span>@endif
                    </h2>
                    <button wire:click="pullQuestions" wire:loading.attr="disabled" class="text-[10px] font-medium text-[rgb(var(--ui-primary-rgb))] hover:underline inline-flex items-center gap-1">
                        @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5') abrufen
                    </button>
                </div>

                @forelse($this->openQuestions as $question)
                    <div class="border-b border-gray-100 last:border-0 py-3">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-sm font-medium text-gray-900">{{ $question->title }}</h3>
                            @if($question->priority === 'high')
                                <x-ui-badge color="danger" size="xs">Hoch</x-ui-badge>
                            @endif
                        </div>
                        @if($question->description)
                            <p class="text-xs text-gray-600 mt-1 whitespace-pre-line leading-relaxed">{{ \Illuminate\Support\Str::limit($question->description, 300) }}</p>
                        @endif

                        @if($answeringId === $question->id)
                            <div class="mt-2 space-y-2">
                                <x-ui-input-textarea wire:model="answerText" label="Antwort an FLYNK" rows="3" placeholder="Deine Antwort…" />
                                <div class="flex justify-end gap-2">
                                    <x-ui-button variant="secondary" size="sm" wire:click="cancelAnswer">Abbrechen</x-ui-button>
                                    <x-ui-button variant="primary" size="sm" wire:click="submitAnswer">
                                        @svg('heroicon-o-paper-airplane', 'w-4 h-4') Senden
                                    </x-ui-button>
                                </div>
                            </div>
                        @else
                            <button wire:click="startAnswer({{ $question->id }})" class="mt-2 text-xs font-medium text-[rgb(var(--ui-primary-rgb))] hover:underline inline-flex items-center gap-1">
                                @svg('heroicon-o-chat-bubble-left-right', 'w-3.5 h-3.5') Antworten
                            </button>
                        @endif
                    </div>
                @empty
                    <p class="text-xs text-gray-400">Keine offenen Rückfragen — wir sind hier bei nichts am Zug.</p>
                @endforelse
            </div>

            {{-- ═══ Aufgaben ═══ --}}
            @php
                $taskMeta = [
                    'in_progress' => ['#6366f1', 'In Arbeit'],
                    'planned'     => ['#64748b', 'Geplant'],
                    'new'         => ['#0ea5e9', 'Neu'],
                    'review'      => ['#f59e0b', 'Review'],
                    'suggested'   => ['#94a3b8', 'Vorschlag'],
                    'on_hold'     => ['#6b7280', 'Pausiert'],
                    'done'        => ['#10b981', 'Erledigt'],
                    'rejected'    => ['#ef4444', 'Abgelehnt'],
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
                $statusCounts = $this->tasks->groupBy(fn ($t) => $t['status'] ?? 'other')->map->count();
            @endphp

            @if($cAll > 0)
                <div class="rounded-2xl border border-black/5 bg-white/70 backdrop-blur-sm p-5" x-data="{ tab: '{{ $cOffen > 0 ? 'offen' : 'alle' }}' }">
                    <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
                        <h2 class="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--ui-text)] inline-flex items-center gap-2" style="font-family: 'JetBrains Mono', monospace;">
                            @svg('heroicon-o-clipboard-document-list', 'w-4 h-4')
                            Aufgaben
                            @if(!empty($flynkMeta['flynk_url']))
                                <a href="{{ $flynkMeta['flynk_url'] }}" target="_blank" rel="noopener" class="text-[rgb(var(--ui-primary-rgb))] normal-case tracking-normal text-[10px] font-medium hover:underline">in FLYNK öffnen ↗</a>
                            @endif
                        </h2>
                        <div class="inline-flex items-center gap-0.5 p-0.5 rounded-xl bg-black/[0.04] text-xs font-semibold">
                            <button @click="tab='offen'" :class="tab==='offen' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'" class="px-3 py-1.5 rounded-lg transition">Offen · {{ $cOffen }}</button>
                            <button @click="tab='review'" :class="tab==='review' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'" class="px-3 py-1.5 rounded-lg transition">Review · {{ $cReview }}</button>
                            <button @click="tab='erledigt'" :class="tab==='erledigt' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'" class="px-3 py-1.5 rounded-lg transition">Erledigt · {{ $cDone }}</button>
                            <button @click="tab='alle'" :class="tab==='alle' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'" class="px-3 py-1.5 rounded-lg transition">Alle · {{ $cAll }}</button>
                        </div>
                    </div>

                    {{-- Status-Verteilung --}}
                    <div class="flex items-center h-1.5 rounded-full overflow-hidden mb-4 bg-gray-100">
                        @foreach($statusCounts as $st => $cnt)
                            <div style="width: {{ $cAll ? round($cnt / $cAll * 100) : 0 }}%; background: {{ $taskMeta[$st][0] ?? '#cbd5e1' }};" title="{{ $st }}: {{ $cnt }}"></div>
                        @endforeach
                    </div>

                    {{-- Task-Zeilen --}}
                    <div>
                        @foreach($this->tasks as $t)
                            @php
                                $st = $t['status'] ?? 'other';
                                [$stColor, $stLabel] = $taskMeta[$st] ?? ['#94a3b8', $st];
                                $bucket = $bucketOf($st);
                            @endphp
                            <div x-show="tab==='alle' || tab==='{{ $bucket }}'" class="flex items-center gap-3 py-3 border-b border-gray-100 last:border-0 group">
                                <span class="w-1 self-stretch rounded-full flex-shrink-0" style="background: {{ $stColor }};"></span>
                                <span class="w-7 h-7 flex-shrink-0 rounded-lg bg-black/[0.04] flex items-center justify-center text-gray-500">
                                    @svg($typeIcons[$t['type']] ?? 'heroicon-o-minus-circle', 'w-4 h-4')
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $t['title'] }}</p>
                                        @if(($t['priority'] ?? null) === 'high')
                                            <span class="flex-shrink-0 px-1.5 py-0.5 rounded text-[9px] font-bold" style="background: rgba(239,68,68,.12); color: #dc2626;">HOCH</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 text-[11px] text-gray-400 mt-0.5" style="font-family: 'JetBrains Mono', monospace;">
                                        <span>{{ $t['type'] }}</span>
                                        @if(!empty($t['assignee']))
                                            <span class="inline-flex items-center gap-1">
                                                <span class="text-gray-300">·</span>
                                                <span class="w-4 h-4 rounded-full bg-[rgb(var(--ui-primary-rgb))] text-white text-[8px] font-bold flex items-center justify-center">{{ mb_substr($t['assignee'], 0, 1) }}</span>
                                                {{ $t['assignee'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <span class="flex-shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold" style="background: {{ $stColor }}1f; color: {{ $stColor }};">{{ $stLabel }}</span>
                                @if(!empty($t['created_at']))
                                    <span class="flex-shrink-0 text-[10px] text-gray-400 w-11 text-right" style="font-family: 'JetBrains Mono', monospace;">{{ \Illuminate\Support\Carbon::parse($t['created_at'])->format('d.m.') }}</span>
                                @endif
                            </div>
                        @endforeach
                        <p x-show="(tab==='offen' && {{ $cOffen }} === 0) || (tab==='review' && {{ $cReview }} === 0) || (tab==='erledigt' && {{ $cDone }} === 0)" x-cloak class="text-xs text-gray-400 py-4 text-center">Keine Aufgaben in dieser Ansicht.</p>
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-black/5 bg-white/70 backdrop-blur-sm p-5">
                    <h2 class="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--ui-text)] inline-flex items-center gap-2 mb-2" style="font-family: 'JetBrains Mono', monospace;">
                        @svg('heroicon-o-clipboard-document-list', 'w-4 h-4')
                        Aufgaben
                    </h2>
                    <p class="text-xs text-gray-400">Noch keine Aufgaben — sobald FLYNK Tasks anlegt, erscheinen sie hier (Abruf über „Meta aktualisieren").</p>
                </div>
            @endif

            {{-- ═══ FLYNK-Projekt + Pushes ═══ --}}
            <div class="grid gap-5 @if(!empty($flynkMeta)) md:grid-cols-2 @endif">

                {{-- FLYNK-Projekt --}}
                @if(!empty($flynkMeta))
                    <div class="rounded-2xl border border-black/5 bg-white/70 backdrop-blur-sm p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--ui-text)]" style="font-family: 'JetBrains Mono', monospace;">FLYNK-Projekt</h2>
                            @if(!empty($flynkMeta['fetched_at']))
                                <span class="text-[10px] text-gray-400" style="font-family: 'JetBrains Mono', monospace;">
                                    Stand {{ \Illuminate\Support\Carbon::parse($flynkMeta['fetched_at'])->format('d.m.Y H:i') }}
                                </span>
                            @endif
                        </div>

                        {{-- Link-Chips --}}
                        <div class="flex flex-wrap gap-2 mb-4">
                            @if(!empty($flynkMeta['production_url']))
                                <a href="{{ $flynkMeta['production_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[rgb(var(--ui-primary-rgb))]/10 text-[rgb(var(--ui-primary-rgb))] text-xs font-medium hover:opacity-80">
                                    @svg('heroicon-o-globe-alt', 'w-3.5 h-3.5') Website
                                </a>
                            @endif
                            @if(!empty($flynkMeta['dev_url']))
                                <a href="{{ $flynkMeta['dev_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-black/[0.04] text-gray-700 text-xs font-medium hover:bg-black/[0.08]">
                                    @svg('heroicon-o-beaker', 'w-3.5 h-3.5') Dev / Staging
                                </a>
                            @endif
                            @if(!empty($flynkMeta['github_repo']))
                                <a href="{{ $flynkMeta['github_repo'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-black/[0.04] text-gray-700 text-xs font-medium hover:bg-black/[0.08]">
                                    @svg('heroicon-o-code-bracket', 'w-3.5 h-3.5') Repo
                                </a>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-xs">
                            @foreach([
                                'client_name' => 'Kunde', 'agency' => 'Agentur',
                                'status' => 'Status', 'flynk_tier' => 'Tier',
                                'maintenance_interval' => 'Wartung', 'primary_contact' => 'Kontakt',
                                'timezone' => 'Zeitzone', 'forge_server' => 'Forge-Server',
                            ] as $key => $label)
                                @if(!empty($flynkMeta[$key]))
                                    <div class="flex justify-between gap-2 border-b border-gray-100 py-1">
                                        <span class="text-gray-500">{{ $label }}</span>
                                        <span class="font-medium text-gray-800 truncate">{{ $flynkMeta[$key] }}</span>
                                    </div>
                                @endif
                            @endforeach
                            @if(!empty($flynkMeta['went_live_at']))
                                <div class="flex justify-between gap-2 border-b border-gray-100 py-1">
                                    <span class="text-gray-500">Go-Live</span>
                                    <span class="font-medium text-gray-800">{{ \Illuminate\Support\Carbon::parse($flynkMeta['went_live_at'])->format('d.m.Y') }}</span>
                                </div>
                            @elseif(!empty($flynkMeta['go_live_at']))
                                <div class="flex justify-between gap-2 border-b border-gray-100 py-1">
                                    <span class="text-gray-500">Go-Live geplant</span>
                                    <span class="font-medium text-gray-800">{{ \Illuminate\Support\Carbon::parse($flynkMeta['go_live_at'])->format('d.m.Y') }}</span>
                                </div>
                            @endif
                        </div>

                        @if(!empty($flynkMeta['tasks_by_status']) && is_array($flynkMeta['tasks_by_status']))
                            <div class="flex flex-wrap gap-1.5 mt-3">
                                @foreach($flynkMeta['tasks_by_status'] as $st => $cnt)
                                    @if($cnt > 0)
                                        <span class="px-2 py-0.5 rounded-full bg-black/[0.04] text-[10px] text-gray-600">{{ $st }}: {{ $cnt }}</span>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        @if(!empty($flynkMeta['stack']) && is_array($flynkMeta['stack']))
                            <div class="flex flex-wrap gap-1.5 mt-3">
                                @foreach($flynkMeta['stack'] as $tech)
                                    <span class="px-2 py-0.5 rounded-full bg-black/[0.04] text-[10px] text-gray-600">{{ $tech }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if(!empty($flynkMeta['notes']))
                            <div class="mt-3 pt-3 border-t border-gray-100" x-data="{ open: false }">
                                <button type="button" @click="open = !open" class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400 hover:text-gray-600">
                                    @svg('heroicon-o-document-text', 'w-3.5 h-3.5')
                                    Notizen
                                    <span class="transition-transform" :class="open ? 'rotate-90' : ''">@svg('heroicon-o-chevron-right', 'w-3 h-3')</span>
                                </button>
                                <p x-show="open" x-collapse x-cloak class="mt-2 text-[11px] text-gray-600 whitespace-pre-line leading-relaxed">{{ $flynkMeta['notes'] }}</p>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Pushes (Timeline) --}}
                <div class="rounded-2xl border border-black/5 bg-white/70 backdrop-blur-sm p-5">
                    <h2 class="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--ui-text)] mb-4" style="font-family: 'JetBrains Mono', monospace;">Pushes</h2>
                    @forelse($this->pushes as $push)
                        @php
                            $pIcon = $push->status->value === 'processed' ? 'heroicon-o-check'
                                : ($push->status->value === 'failed' ? 'heroicon-o-x-mark' : 'heroicon-o-paper-airplane');
                        @endphp
                        <div class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <span class="w-6 h-6 rounded-full bg-black/[0.04] flex items-center justify-center">
                                    @svg($pIcon, 'w-3.5 h-3.5 text-[rgb(var(--ui-' . $push->status->color() . '-rgb))]')
                                </span>
                                @unless($loop->last)<span class="w-px flex-1 bg-gray-200 mt-1"></span>@endunless
                            </div>
                            <div class="flex-1 min-w-0 pb-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-gray-800">{{ $push->status->label() }}</span>
                                    <span class="text-[10px] text-gray-400 truncate" style="font-family: 'JetBrains Mono', monospace;">{{ \Illuminate\Support\Str::limit($push->uuid, 12) }}</span>
                                    <span class="ml-auto text-[10px] text-gray-400" style="font-family: 'JetBrains Mono', monospace;">{{ $push->created_at->format('d.m. H:i') }}</span>
                                </div>
                                @php $results = $push->results(); @endphp
                                @if(!empty($results))
                                    <div class="mt-1 space-y-1">
                                        @foreach($results as $r)
                                            <a href="{{ $r['url'] ?? '#' }}" @if(!empty($r['url'])) target="_blank" rel="noopener" @endif class="flex items-center gap-1.5 text-[11px] text-gray-600 hover:text-[rgb(var(--ui-primary-rgb))]">
                                                @svg('heroicon-o-document-text', 'w-3 h-3 text-gray-400 flex-shrink-0')
                                                <span class="truncate">{{ $r['title'] ?? ($r['type'] ?? 'Ergebnis') }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                                @if(in_array($push->status->value, ['sent','accepted','processing']))
                                    <button wire:click="pullFeedback({{ $push->id }})" class="mt-1 text-[10px] font-medium text-[rgb(var(--ui-primary-rgb))] hover:underline">
                                        Feedback abrufen
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">Noch keine Pushes.</p>
                    @endforelse
                </div>
            </div>

            {{-- ═══ Push-Vorschau ═══ --}}
            <div class="rounded-2xl border border-black/5 bg-white/70 backdrop-blur-sm p-5" x-data="{ open: false }">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--ui-text)]" style="font-family: 'JetBrains Mono', monospace;">Push-Vorschau</h2>
                    <button type="button" @click="open = !open" class="text-[10px] font-medium text-[rgb(var(--ui-primary-rgb))] hover:underline">
                        <span x-text="open ? 'Ausblenden' : 'JSON anzeigen'"></span>
                    </button>
                </div>
                <p class="text-[11px] text-gray-500 mt-1">Struktur des Envelopes (<code>push</code> + <code>context</code>), den der Connector an FLYNK sendet. Der <code>context.brand</code>-Block ist derzeit ein Beispiel und wird ab dem Brands-Port real gefüllt.</p>
                <div x-show="open" x-collapse x-cloak class="mt-3">
                    <pre class="text-[10px] leading-relaxed bg-gray-900 text-gray-100 rounded-lg p-4 overflow-x-auto" style="font-family: 'JetBrains Mono', monospace;">{{ $this->pushPreviewJson }}</pre>
                </div>
            </div>

            {{-- ═══ Einstellungen ═══ --}}
            <div class="rounded-2xl border border-black/5 bg-white/70 backdrop-blur-sm p-5">
                <h2 class="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--ui-text)] mb-4" style="font-family: 'JetBrains Mono', monospace;">Einstellungen</h2>
                <div class="space-y-4">
                    <x-ui-input-text wire:model="form.name" label="Name" required />
                    <x-ui-input-textarea wire:model="form.description" label="Beschreibung" rows="2" />
                    <x-ui-input-select
                        name="form.integration_connection_id"
                        wire:model="form.integration_connection_id"
                        label="FLYNK-Verbindung"
                        :options="$this->flynkConnections->pluck('name', 'id')->toArray()"
                        :nullable="true"
                        nullLabel="Team-Standard verwenden"
                    />
                </div>
                @if($this->isDirty)
                    <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-gray-100">
                        <x-ui-button variant="secondary" size="sm" wire:click="loadForm">Abbrechen</x-ui-button>
                        <x-ui-button variant="primary" size="sm" wire:click="save">Speichern</x-ui-button>
                    </div>
                @endif
            </div>

            {{-- ═══ Danger zone ═══ --}}
            <div class="rounded-2xl border border-[rgb(var(--ui-danger-rgb))]/20 bg-[rgb(var(--ui-danger-rgb))]/5 p-5">
                <h3 class="text-sm font-semibold text-[rgb(var(--ui-danger-rgb))] mb-2">Gefahrenzone</h3>
                <p class="text-xs text-[color:var(--ui-secondary)] mb-4">Löscht den Container lokal. Ein verbundenes FLYNK-Project bleibt bestehen — melde es vorher ab, wenn es entfernt werden soll.</p>
                <x-ui-button variant="danger" size="sm" wire:click="delete" wire:confirm="Container wirklich löschen?">
                    @svg('heroicon-o-trash', 'w-4 h-4')
                    Container löschen
                </x-ui-button>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
