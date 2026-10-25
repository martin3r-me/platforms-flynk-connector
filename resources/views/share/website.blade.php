{{-- Kunden-Tor · Website / Aufträge: Link zur Website und die Aufträge nach Stand (wie ein Board). Daten: $url, $columns, $synced. --}}
@php
    $toneVar = ['neutral' => 'var(--nx-faint)', 'info' => 'var(--nx-info)', 'warning' => 'var(--nx-warning)', 'success' => 'var(--nx-success)'];
    $review = collect($columns)->firstWhere('key', 'review');
@endphp
<div class="gp-wrap py-6">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        @if ($url)
            <a href="{{ $url }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 rounded-[6px] border border-[color:var(--nx-line-strong)] px-3 py-1.5 text-sm hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4') Website öffnen
            </a>
        @endif
        @if ($review && $review['tasks'])
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[rgba(232,89,12,.1)] px-3 py-1 text-sm text-[color:var(--nx-warning)]">
                @svg('heroicon-s-hand-raised', 'w-4 h-4') {{ count($review['tasks']) }} {{ count($review['tasks']) === 1 ? 'Auftrag wartet' : 'Aufträge warten' }} auf Ihre Abnahme
            </span>
        @endif
        @if ($synced)
            <span class="ml-auto text-xs text-[color:var(--nx-faint)]">Stand {{ $synced->copy()->locale('de')->isoFormat('D. MMM, HH:mm') }} Uhr</span>
        @endif
    </div>
</div>
<div class="gp-bleed overflow-x-auto pb-8">
    <div class="flex min-w-max gap-3">
        @foreach ($columns as $col)
            <div class="flex w-[17.5rem] shrink-0 flex-col rounded-[12px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)]">
                <div class="flex items-center gap-2 px-3 py-2.5">
                    <span class="h-2 w-2 rounded-full" style="background: {{ $toneVar[$col['tone']] ?? 'var(--nx-faint)' }}"></span>
                    <span class="text-sm font-medium">{{ $col['title'] }}</span>
                    <span class="text-xs text-[color:var(--nx-faint)]">{{ count($col['tasks']) }}</span>
                </div>
                <div class="space-y-2 px-2 pb-2">
                    @forelse (array_slice($col['tasks'], 0, 40) as $t)
                        <div class="rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] px-3 py-2.5">
                            <div @class(['text-sm leading-snug', 'text-[color:var(--nx-muted)] line-through decoration-[color:var(--nx-line-strong)]' => $col['key'] === 'done'])>{{ $t['title'] }}</div>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px] text-[color:var(--nx-faint)]">
                                @if ($t['type_label'])<span class="rounded-full bg-[color:var(--nx-hover)] px-1.5 py-px">{{ $t['type_label'] }}</span>@endif
                                @if ($t['created'])<span>{{ $t['created']->copy()->locale('de')->isoFormat('D. MMM YYYY') }}</span>@endif
                            </div>
                        </div>
                    @empty
                        <div class="px-2 py-4 text-center text-xs text-[color:var(--nx-faint)]">Nichts</div>
                    @endforelse
                    @if (count($col['tasks']) > 40)
                        <div class="px-2 text-center text-xs text-[color:var(--nx-faint)]">+ {{ count($col['tasks']) - 40 }} weitere</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
