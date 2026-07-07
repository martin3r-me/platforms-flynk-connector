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
                    <div class="flex items-center gap-1.5 text-gray-700">
                        @svg('heroicon-o-building-office', 'w-3.5 h-3.5 text-gray-400')
                        <span>{{ $container->ownerEntity?->name ?? 'Kein Knoten' }}</span>
                    </div>
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
                    @if($container->external_url)
                        <a href="{{ $container->external_url }}" target="_blank" rel="noopener" class="text-[rgb(var(--ui-primary-rgb))] hover:underline inline-flex items-center gap-1 mt-1">
                            @svg('heroicon-o-arrow-top-right-on-square', 'w-3 h-3') Öffnen
                        </a>
                    @endif
                </div>
                @if($container->last_synced_at)
                    <div class="border-t border-gray-200 pt-3">
                        <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400 mb-1">Letzter Sync</div>
                        <div class="text-gray-700" style="font-family: 'JetBrains Mono', monospace;">{{ $container->last_synced_at->format('d.m.Y H:i') }}</div>
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
        <div class="py-6 max-w-3xl space-y-6">

            {{-- FLYNK-Aktionen --}}
            <div class="rounded-xl border border-black/5 bg-white/60 backdrop-blur-sm p-5">
                <h2 class="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--ui-text)] mb-4" style="font-family: 'JetBrains Mono', monospace;">FLYNK-Aktionen</h2>

                @if($container->isLinked())
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui-button variant="primary" size="sm" wire:click="pushUpdate" wire:loading.attr="disabled">
                            @svg('heroicon-o-arrow-path', 'w-4 h-4')
                            Update mit Daten pushen
                        </x-ui-button>
                        <x-ui-button variant="secondary" size="sm" wire:click="syncMeta" wire:loading.attr="disabled">
                            @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                            Meta aktualisieren
                        </x-ui-button>
                        <x-ui-button variant="secondary" size="sm" wire:click="testConnection">
                            @svg('heroicon-o-signal', 'w-4 h-4')
                            Verbindung testen
                        </x-ui-button>
                        <x-ui-button variant="danger" size="sm" wire:click="unregister" wire:confirm="Container wirklich von FLYNK abmelden? Das FLYNK-Project wird entfernt.">
                            @svg('heroicon-o-x-circle', 'w-4 h-4')
                            Abmelden
                        </x-ui-button>
                    </div>
                @else
                    <p class="text-xs text-gray-500 mb-3">Dieser Container ist mit keinem FLYNK-Project verbunden.</p>
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        <x-ui-button variant="primary" size="sm" wire:click="createRemote" wire:loading.attr="disabled">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            In FLYNK anlegen
                        </x-ui-button>
                    </div>
                    <div class="flex items-end gap-2 pt-3 border-t border-gray-100">
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

            {{-- FLYNK-Meta --}}
            @php $flynkMeta = $container->metadata['flynk'] ?? null; @endphp
            @if($flynkMeta)
                <div class="rounded-xl border border-black/5 bg-white/60 backdrop-blur-sm p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--ui-text)]" style="font-family: 'JetBrains Mono', monospace;">FLYNK-Projekt</h2>
                        @if(!empty($flynkMeta['fetched_at']))
                            <span class="text-[10px] text-gray-400" style="font-family: 'JetBrains Mono', monospace;">
                                Stand {{ \Illuminate\Support\Carbon::parse($flynkMeta['fetched_at'])->format('d.m.Y H:i') }}
                            </span>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-xs">
                        @foreach([
                            'name' => 'Name', 'client_name' => 'Kunde', 'agency' => 'Agentur',
                            'status' => 'Status', 'timezone' => 'Zeitzone', 'forge_server' => 'Forge-Server',
                        ] as $key => $label)
                            @if(!empty($flynkMeta[$key]))
                                <div class="flex justify-between gap-2 border-b border-gray-100 py-1">
                                    <span class="text-gray-500">{{ $label }}</span>
                                    <span class="font-medium text-gray-800 truncate">{{ $flynkMeta[$key] }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    @if(!empty($flynkMeta['production_url']) || !empty($flynkMeta['github_repo']))
                        <div class="flex flex-wrap gap-3 mt-3 text-xs">
                            @if(!empty($flynkMeta['production_url']))
                                <a href="{{ $flynkMeta['production_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[rgb(var(--ui-primary-rgb))] hover:underline">
                                    @svg('heroicon-o-globe-alt', 'w-3.5 h-3.5') Website
                                </a>
                            @endif
                            @if(!empty($flynkMeta['github_repo']))
                                <a href="{{ $flynkMeta['github_repo'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[rgb(var(--ui-primary-rgb))] hover:underline">
                                    @svg('heroicon-o-code-bracket', 'w-3.5 h-3.5') Repo
                                </a>
                            @endif
                        </div>
                    @endif
                    @if(!empty($flynkMeta['stack']) && is_array($flynkMeta['stack']))
                        <div class="flex flex-wrap gap-1.5 mt-3">
                            @foreach($flynkMeta['stack'] as $tech)
                                <span class="px-2 py-0.5 rounded-full bg-black/[0.04] text-[10px] text-gray-600">{{ $tech }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            {{-- Einstellungen --}}
            <div class="rounded-xl border border-black/5 bg-white/60 backdrop-blur-sm p-5">
                <h2 class="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--ui-text)] mb-4" style="font-family: 'JetBrains Mono', monospace;">Einstellungen</h2>
                <div class="space-y-4">
                    <x-ui-input-text wire:model="form.name" label="Name" required />
                    <x-ui-input-textarea wire:model="form.description" label="Beschreibung" rows="2" />
                    <x-ui-input-select
                        name="form.owner_entity_id"
                        wire:model="form.owner_entity_id"
                        label="Organisations-Knoten (Verortung)"
                        :options="$this->availableEntities->pluck('name', 'id')->toArray()"
                        :nullable="true"
                        nullLabel="Kein Knoten"
                    />
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

            {{-- Danger zone --}}
            <div class="rounded-xl border border-[rgb(var(--ui-danger-rgb))]/20 bg-[rgb(var(--ui-danger-rgb))]/5 p-5">
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
