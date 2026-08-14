<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="FLYNK Container" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[['label' => 'FLYNK Container']]">
            <x-slot name="left">
                <x-nx-input-select
                    wire:key="filter-status"
                    name="statusFilter"
                    :options="collect(\Platform\FlynkConnector\Enums\FlynkContainerStatus::cases())->mapWithKeys(fn($s) => [$s->value => $s->label()])->toArray()"
                    wire:model.live="statusFilter"
                    nullable
                    nullLabel="Alle Status"
                    size="sm"
                />
            </x-slot>

            <x-nx-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Container</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Suche" width="w-72" :defaultOpen="true" side="left">
            <div class="p-4">
                <x-nx-input-text name="search" wire:model.live.debounce.300ms="search" placeholder="Name, Beschreibung..." size="sm" />
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Activity Sidebar (right) --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivität" icon="heroicon-o-bolt" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-3">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-[color:var(--nx-muted)]">Letzte Ereignisse</div>
                @forelse($this->recentActivity as $event)
                    <a href="{{ route('flynk-connector.containers.show', $event->flynk_container_id) }}" wire:navigate class="flex gap-2.5 text-xs group">
                        <div class="flex-shrink-0 mt-0.5">
                            @svg($event->icon(), 'w-4 h-4 text-[rgb(var(--ui-' . $event->color() . '-rgb))]')
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-[color:var(--nx-text)] truncate group-hover:text-[color:var(--nx-accent)] transition-colors">{{ $event->title }}</p>
                            @if($event->container)
                                <p class="text-[10px] text-[color:var(--nx-muted)] truncate">{{ $event->container->name }}</p>
                            @endif
                            <div class="flex items-center gap-2 text-[color:var(--nx-faint)] mt-0.5 tabular-nums">
                                <span>{{ $event->created_at->format('d.m. H:i') }}</span>
                                @if($event->user)<span>&middot; {{ $event->user->name }}</span>@endif
                            </div>
                        </div>
                    </a>
                @empty
                    <p class="text-xs text-[color:var(--nx-faint)] text-center py-4">Noch keine Aktivität.</p>
                @endforelse
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Main content --}}
    <x-ui-page-container>
        <div class="pt-6">
            <x-nx-card flush>
                <x-nx-table>
                    <x-nx-table-header>
                        <x-nx-table-header-cell>Name</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Knoten</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Status</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="center">Aufgaben</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Füllgrad</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">Letzter Sync</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right"></x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @forelse($this->containers as $container)
                            @php
                                $m = $container->metadata['flynk'] ?? [];
                                $nodes = implode(', ', $this->entityNamesByContainer[$container->id] ?? []);
                                $cc = $m['context_completeness'] ?? null;
                                $ccPct = $cc !== null ? (int) round((float) $cc * (($cc <= 1) ? 100 : 1)) : null;
                                $openTasks = $m['open_tasks'] ?? null;
                                $totalTasks = $m['total_tasks'] ?? null;
                                $statusColor = $container->status->color();
                                $statusVariant = in_array($statusColor, ['success', 'danger', 'warning', 'info']) ? $statusColor : 'neutral';
                            @endphp
                            <x-nx-table-row>
                                {{-- Name + FLYNK-Referenz --}}
                                <x-nx-table-cell>
                                    <a href="{{ route('flynk-connector.containers.show', $container) }}" wire:navigate
                                       class="font-medium text-[color:var(--nx-text)] hover:text-[color:var(--nx-accent)] hover:underline">
                                        {{ $container->name }}
                                    </a>
                                    <div class="text-[11px] text-[color:var(--nx-faint)] tabular-nums truncate">
                                        {{ $container->external_id ? \Illuminate\Support\Str::limit($container->external_id, 20) : 'nicht verbunden' }}
                                    </div>
                                </x-nx-table-cell>

                                {{-- Verortung --}}
                                <x-nx-table-cell>
                                    @if($nodes)
                                        <span class="inline-flex items-center gap-1.5 text-[color:var(--nx-muted)]">
                                            @svg('heroicon-o-building-office', 'w-3.5 h-3.5 flex-shrink-0 opacity-60')
                                            <span class="truncate">{{ $nodes }}</span>
                                        </span>
                                    @else
                                        <span class="text-[color:var(--nx-faint)]">—</span>
                                    @endif
                                </x-nx-table-cell>

                                {{-- Status --}}
                                <x-nx-table-cell>
                                    <x-nx-badge :variant="$statusVariant" dot>{{ $container->status->label() }}</x-nx-badge>
                                </x-nx-table-cell>

                                {{-- Aufgaben --}}
                                <x-nx-table-cell align="center">
                                    @if($openTasks !== null)
                                        <span class="tabular-nums {{ $openTasks > 0 ? 'text-[color:var(--nx-text)]' : 'text-[color:var(--nx-faint)]' }}">
                                            {{ $openTasks }}@if($totalTasks !== null)<span class="text-[color:var(--nx-faint)]">/{{ $totalTasks }}</span>@endif
                                        </span>
                                    @else
                                        <span class="text-[color:var(--nx-faint)]">—</span>
                                    @endif
                                </x-nx-table-cell>

                                {{-- Füllgrad (FLYNK context_completeness) --}}
                                <x-nx-table-cell>
                                    @if($ccPct !== null)
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 w-16 rounded-full bg-[color:var(--nx-line)] overflow-hidden">
                                                <div class="h-full rounded-full bg-[color:var(--nx-accent)]" style="width: {{ max(3, min(100, $ccPct)) }}%;"></div>
                                            </div>
                                            <span class="text-[11px] tabular-nums text-[color:var(--nx-muted)]">{{ $ccPct }}%</span>
                                        </div>
                                    @else
                                        <span class="text-[color:var(--nx-faint)]">—</span>
                                    @endif
                                </x-nx-table-cell>

                                {{-- Letzter Sync --}}
                                <x-nx-table-cell align="right">
                                    <span class="text-[color:var(--nx-muted)] tabular-nums">{{ $container->last_synced_at?->diffForHumans() ?? '—' }}</span>
                                </x-nx-table-cell>

                                {{-- Aktionen --}}
                                <x-nx-table-cell align="right">
                                    <x-nx-button variant="ghost" size="sm" icon wire:click="delete({{ $container->id }})" wire:confirm="Container '{{ $container->name }}' wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-nx-button>
                                </x-nx-table-cell>
                            </x-nx-table-row>
                        @empty
                            <x-nx-table-row>
                                <x-nx-table-cell colspan="7">
                                    <x-nx-empty icon="heroicon-o-arrows-right-left">
                                        Keine Container — lege einen an und verbinde ihn mit einem FLYNK-Project.
                                        <x-slot name="action">
                                            <x-nx-button variant="primary" size="sm" wire:click="create">
                                                @svg('heroicon-o-plus', 'w-4 h-4') Neuer Container
                                            </x-nx-button>
                                        </x-slot>
                                    </x-nx-empty>
                                </x-nx-table-cell>
                            </x-nx-table-row>
                        @endforelse
                    </x-nx-table-body>
                </x-nx-table>
            </x-nx-card>
        </div>
    </x-ui-page-container>

    {{-- Create Modal --}}
    <x-nx-modal size="md" wire:model="modalShow">
        <x-slot name="header">Neuer Container</x-slot>
        <form wire:submit="store" class="space-y-4">
            <x-nx-input-text name="form.name" wire:model="form.name" label="Name" required size="sm" />
            <x-nx-input-textarea name="form.description" wire:model="form.description" label="Beschreibung" rows="2" size="sm" />

            <p class="text-[11px] text-[color:var(--nx-faint)]">Die Verortung am Organisations-Knoten erfolgt anschließend im Container-Detail.</p>

            <x-nx-input-select
                name="form.integration_connection_id"
                wire:model="form.integration_connection_id"
                label="FLYNK-Verbindung"
                :options="$this->flynkConnections->pluck('name', 'id')->toArray()"
                nullable
                nullLabel="Team-Standard verwenden"
                size="sm"
            />

            <x-nx-input-select
                name="form.link_mode"
                wire:model.live="form.link_mode"
                label="Modus"
                :options="['create' => 'Neues FLYNK-Project anlegen', 'link' => 'Bestehendes verknüpfen']"
                size="sm"
            />

            @if($form['link_mode'] === 'link')
                <div class="rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] p-3 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-[color:var(--nx-muted)]">FLYNK-Project</span>
                        <x-nx-button variant="secondary" size="sm" wire:click="loadProjects" type="button">
                            @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5')
                            Aus FLYNK laden
                        </x-nx-button>
                    </div>

                    @if(!empty($availableProjects))
                        <x-nx-input-select
                            name="form.external_id"
                            wire:model="form.external_id"
                            label="Verfügbare Projects"
                            :options="collect($availableProjects)->pluck('name', 'id')->toArray()"
                            nullable
                            nullLabel="— wählen —"
                            size="sm"
                        />
                    @endif

                    <x-nx-input-text name="form.external_id" wire:model="form.external_id" label="Project-UUID" placeholder="oder UUID direkt einfügen" size="sm" />
                </div>
            @endif
        </form>

        <x-slot name="footer">
            <x-nx-button variant="ghost" size="sm" wire:click="$set('modalShow', false)" type="button">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" size="sm" wire:click="store">
                @svg('heroicon-o-check', 'w-4 h-4')
                <span>Erstellen</span>
            </x-nx-button>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
