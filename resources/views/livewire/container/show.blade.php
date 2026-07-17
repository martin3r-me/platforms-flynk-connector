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
            $circ = 113; $ringOffset = $pct !== null ? $circ - ($circ * $pct / 100) : $circ;
        @endphp

        <div class="py-6 max-w-4xl space-y-5">

            {{-- ═══ Hero ═══ --}}
            <div class="rounded-2xl border border-black/5 bg-white/70 backdrop-blur-sm p-6">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-[11px] text-gray-400 mb-1 flex-wrap">
                            <span style="font-family: 'JetBrains Mono', monospace;">FLYNK Container</span>
                            @foreach($container->linkedEntities() as $entity)
                                <span>&middot;</span>
                                <span class="inline-flex items-center gap-1">@svg('heroicon-o-building-office', 'w-3 h-3') {{ $entity->name }}</span>
                            @endforeach
                        </div>
                        <h1 class="text-xl font-bold tracking-tight text-[color:var(--ui-text)]">{{ $container->name }}</h1>
                        <div class="flex items-center gap-3 mt-2 text-xs flex-wrap">
                            @if($container->external_id)
                                <span class="inline-flex items-center gap-1 text-gray-400" style="font-family: 'JetBrains Mono', monospace;">
                                    @svg('heroicon-o-link', 'w-3.5 h-3.5') {{ \Illuminate\Support\Str::limit($container->external_id, 22) }}
                                </span>
                            @endif
                            @if($container->external_url)
                                <a href="{{ $container->external_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[rgb(var(--ui-primary-rgb))] font-medium hover:underline">
                                    @svg('heroicon-o-globe-alt', 'w-3.5 h-3.5') Website
                                </a>
                            @endif
                        </div>
                    </div>
                    <x-ui-badge :color="$container->status->color()" size="sm">{{ $container->status->label() }}</x-ui-badge>
                </div>

                {{-- Stat strip (graceful: 2×2 Basis, 4-across ab md wenn CSS-Klasse vorhanden) --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-5">
                    <div class="rounded-xl bg-black/[0.02] border border-black/5 p-3">
                        <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400 mb-1" style="font-family: 'JetBrains Mono', monospace;">Offene Aufgaben</div>
                        <div class="text-2xl font-bold text-[rgb(var(--ui-primary-rgb))]" style="font-family: 'JetBrains Mono', monospace;">{{ $flynkMeta['open_tasks'] ?? '—' }}</div>
                    </div>
                    <div class="rounded-xl bg-black/[0.02] border border-black/5 p-3 flex items-center gap-3">
                        <svg width="46" height="46" viewBox="0 0 46 46" class="flex-shrink-0">
                            <circle cx="23" cy="23" r="18" fill="none" stroke="#E5E7EB" stroke-width="5"/>
                            <circle cx="23" cy="23" r="18" fill="none" stroke="rgb(var(--ui-primary-rgb))" stroke-width="5" stroke-linecap="round"
                                    stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $ringOffset }}" transform="rotate(-90 23 23)"
                                    style="transition: stroke-dashoffset .6s ease;" />
                        </svg>
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400 mb-0.5" style="font-family: 'JetBrains Mono', monospace;">Context</div>
                            <div class="text-lg font-bold" style="font-family: 'JetBrains Mono', monospace;">{{ $pct !== null ? $pct.'%' : '—' }}</div>
                        </div>
                    </div>
                    <div class="rounded-xl bg-black/[0.02] border border-black/5 p-3">
                        <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400 mb-1" style="font-family: 'JetBrains Mono', monospace;">Website</div>
                        @php
                            $wh = $flynkMeta['website_health'] ?? null;
                            $whMap = ['healthy' => ['success', 'Gesund'], 'warning' => ['warning', 'Warnung'], 'critical' => ['danger', 'Kritisch']];
                            [$whColor, $whLabel] = $whMap[$wh] ?? ['muted', '—'];
                        @endphp
                        <div class="mt-1.5">
                            <x-ui-badge :color="$whColor" size="sm">{{ $whLabel }}</x-ui-badge>
                        </div>
                    </div>
                    <div class="rounded-xl bg-black/[0.02] border border-black/5 p-3">
                        <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400 mb-1" style="font-family: 'JetBrains Mono', monospace;">Letzter Sync</div>
                        <div class="text-sm font-semibold text-gray-700 mt-1.5" style="font-family: 'JetBrains Mono', monospace;">{{ $container->last_synced_at?->diffForHumans() ?? '—' }}</div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex flex-wrap items-center gap-2 mt-5">
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
                        Rückfragen@if($this->openQuestions->isNotEmpty()) <span class="text-[rgb(var(--ui-warning-rgb))]">({{ $this->openQuestions->count() }})</span>@endif
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
