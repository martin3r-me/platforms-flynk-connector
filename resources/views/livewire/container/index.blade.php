<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="FLYNK Container" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[['label' => 'FLYNK Container']]">
            <x-slot name="left">
                <x-ui-input-select
                    wire:key="filter-status"
                    name="statusFilter"
                    :options="collect(\Platform\FlynkConnector\Enums\FlynkContainerStatus::cases())->mapWithKeys(fn($s) => [$s->value => $s->label()])->toArray()"
                    wire:model.live="statusFilter"
                    :nullable="true"
                    nullLabel="Alle Status"
                    size="xs"
                />
            </x-slot>

            <x-ui-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Container</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Suche" width="w-72" :defaultOpen="true" side="left">
            <div class="p-4">
                <x-ui-input-text wire:model.live.debounce.300ms="search" placeholder="Name, Beschreibung..." size="sm" />
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Activity Sidebar (right) --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivität" icon="heroicon-o-bolt" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-3">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-[color:var(--ui-muted)]">Letzte Ereignisse</div>
                @forelse($this->recentActivity as $event)
                    <a href="{{ route('flynk-connector.containers.show', $event->flynk_container_id) }}" class="flex gap-2.5 text-xs group">
                        <div class="flex-shrink-0 mt-0.5">
                            @svg($event->icon(), 'w-4 h-4 text-[rgb(var(--ui-' . $event->color() . '-rgb))]')
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 truncate group-hover:text-[rgb(var(--ui-primary-rgb))] transition-colors">{{ $event->title }}</p>
                            @if($event->container)
                                <p class="text-[10px] text-gray-400 truncate">{{ $event->container->name }}</p>
                            @endif
                            <div class="flex items-center gap-2 text-gray-400 mt-0.5">
                                <span style="font-family: 'JetBrains Mono', monospace;">{{ $event->created_at->format('d.m. H:i') }}</span>
                                @if($event->user)
                                    <span>&middot; {{ $event->user->name }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <p class="text-xs text-gray-400 text-center py-4">Noch keine Aktivität.</p>
                @endforelse
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Main content --}}
    <x-ui-page-container>
        <div class="pt-6">
            @if($this->containers->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    @svg('heroicon-o-arrows-right-left', 'w-12 h-12 text-gray-300 mb-4')
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">Keine Container</h3>
                    <p class="text-xs text-gray-500 mb-4">Lege einen Container an und verbinde ihn mit einem FLYNK-Project.</p>
                    <x-ui-button variant="primary" size="sm" wire:click="create">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        Neuer Container
                    </x-ui-button>
                </div>
            @else
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($this->containers as $container)
                        <a href="{{ route('flynk-connector.containers.show', $container) }}"
                           class="group block rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 border-l-[4px] border-l-[rgb(var(--ui-{{ $container->status->color() }}-rgb))]">

                            <div class="flex items-start justify-between gap-3 mb-2">
                                <h3 class="font-bold text-sm text-gray-900 truncate group-hover:text-gray-700 transition-colors">{{ $container->name }}</h3>
                                <x-ui-badge :color="$container->status->color()" size="xs">{{ $container->status->label() }}</x-ui-badge>
                            </div>

                            @if($container->description)
                                <p class="text-xs text-gray-500 line-clamp-2 mb-3">{{ $container->description }}</p>
                            @endif

                            <div class="space-y-1.5 text-xs text-gray-500 pt-3 border-t border-gray-100">
                                <div class="flex items-center gap-1.5">
                                    @svg('heroicon-o-building-office', 'w-3.5 h-3.5 flex-shrink-0 text-gray-400')
                                    <span class="truncate">{{ $container->ownerEntity?->name ?? 'Kein Knoten' }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    @svg('heroicon-o-link', 'w-3.5 h-3.5 flex-shrink-0 text-gray-400')
                                    <span class="truncate" style="font-family: 'JetBrains Mono', monospace;">
                                        {{ $container->external_id ? \Illuminate\Support\Str::limit($container->external_id, 20) : 'nicht verbunden' }}
                                    </span>
                                </div>
                                @if($container->last_synced_at)
                                    <div class="flex items-center gap-1.5">
                                        @svg('heroicon-o-clock', 'w-3.5 h-3.5 flex-shrink-0 text-gray-400')
                                        <span style="font-family: 'JetBrains Mono', monospace;">{{ $container->last_synced_at->format('d.m.Y H:i') }}</span>
                                    </div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </x-ui-page-container>

    {{-- Create Modal --}}
    <x-ui-modal wire:model="modalShow" title="Neuer Container">
        <form wire:submit="store" class="space-y-4">
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

            <x-ui-input-select
                name="form.link_mode"
                wire:model.live="form.link_mode"
                label="Modus"
                :options="['create' => 'Neues FLYNK-Project anlegen', 'link' => 'Bestehendes verknüpfen']"
            />

            @if($form['link_mode'] === 'link')
                <div class="rounded-lg border border-black/5 bg-black/[0.02] p-3 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-600">FLYNK-Project</span>
                        <x-ui-button variant="secondary" size="xs" wire:click="loadProjects" type="button">
                            @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5')
                            Aus FLYNK laden
                        </x-ui-button>
                    </div>

                    @if(!empty($availableProjects))
                        <x-ui-input-select
                            name="form.external_id"
                            wire:model="form.external_id"
                            label="Verfügbare Projects"
                            :options="collect($availableProjects)->pluck('name', 'id')->toArray()"
                            :nullable="true"
                            nullLabel="— wählen —"
                        />
                    @endif

                    <x-ui-input-text wire:model="form.external_id" label="Project-UUID" placeholder="oder UUID direkt einfügen" />
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <x-ui-button variant="secondary" size="sm" wire:click="$set('modalShow', false)" type="button">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" type="submit">Erstellen</x-ui-button>
            </div>
        </form>
    </x-ui-modal>
</x-ui-page>
