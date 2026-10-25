{{-- Kunden-Tor · eine Auftrags-Spalte wie x-nx-kanban-column. Daten: $col, $collapsible. --}}
@php
    $slotBg = "color-mix(in srgb, var(--nx-tone-{$col['tone']}) 6%, #ffffff)";
    $pillBg = "color-mix(in srgb, var(--nx-tone-{$col['tone']}) 16%, #ffffff)";
    $isDone = $col['key'] === 'done';
@endphp
<div class="gp-col" style="background-color: {{ $slotBg }}">
    <div @class(['flex items-center justify-between px-1.5 py-1.5', 'opacity-80' => $col['muted'] ?? false])>
        <div class="flex min-w-0 items-center gap-1.5">
            <span class="inline-flex min-w-0 items-center gap-1.5 rounded-[6px] px-2 py-0.5" style="background-color: {{ $pillBg }}">
                <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: var(--nx-tone-{{ $col['tone'] }})"></span>
                <span class="truncate text-xs font-medium">{{ $col['title'] }}</span>
            </span>
            <span class="shrink-0 text-xs text-[color:var(--nx-faint)]">{{ count($col['tasks']) }}</span>
        </div>
        @if ($collapsible ?? false)
            <button type="button" @click="setDone(false)" class="text-[color:var(--nx-faint)] transition-colors hover:text-[color:var(--nx-text)]" title="Einklappen">
                @svg('heroicon-o-chevron-double-right', 'w-4 h-4')
            </button>
        @endif
    </div>
    <div class="gp-col-body">
        @forelse (array_slice($col['tasks'], 0, 60) as $t)
            <div @class(['gp-card-task', 'opacity-60' => $isDone])>
                <span class="gp-edge" style="background-color: {{ $t['edge'] }}"></span>
                <span @class(['block text-[13px] font-semibold leading-snug', 'line-through text-[color:var(--nx-muted)]' => $isDone])>{{ $t['title'] }}</span>
                @if ($t['type_label'] || $t['created'])
                    <span class="mt-2 flex items-center gap-1.5 text-[10px] leading-none text-[color:var(--nx-muted)]">
                        @if ($t['created'])
                            <span class="inline-flex shrink-0 items-center gap-0.5" title="Beauftragt am {{ $t['created']->copy()->locale('de')->isoFormat('D. MMMM YYYY') }}">
                                @svg('heroicon-o-calendar', 'w-3 h-3 opacity-60') {{ $t['created']->copy()->locale('de')->isoFormat('D. MMM YY') }}
                            </span>
                        @endif
                        @if ($t['type_label'])
                            <span class="inline-flex shrink-0 items-center rounded-full bg-[color:var(--nx-accent-soft)] px-2 py-0.5 text-[10px] font-medium text-[color:var(--nx-text)]">{{ $t['type_label'] }}</span>
                        @endif
                    </span>
                @endif
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-8 text-[color:var(--nx-muted)]">
                @svg($isDone ? 'heroicon-o-check-circle' : 'heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                <span class="text-xs">{{ $isDone ? 'Noch nichts erledigt' : 'Keine Aufträge' }}</span>
            </div>
        @endforelse
        @if (count($col['tasks']) > 60)
            <div class="px-2 py-1 text-center text-[11px] text-[color:var(--nx-faint)]">+ {{ count($col['tasks']) - 60 }} weitere</div>
        @endif
    </div>
</div>
