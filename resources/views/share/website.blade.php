{{-- Kunden-Tor · Website / Aufträge: Link zur Website und die Aufträge als Board wie im Planner (Spalten in voller Höhe, Erledigt als Streifen). Daten: $url, $columns, $synced. --}}
@php
    $review = collect($columns)->firstWhere('key', 'review');
    $doneCol = collect($columns)->firstWhere('key', 'done');
    $boardCols = collect($columns)->reject(fn ($c) => $c['key'] === 'done');
@endphp
<div x-data="{
        showDone: (() => { try { return localStorage.getItem('gate-board-done') === '1' } catch (e) { return false } })(),
        setDone(v) {
            this.showDone = v; try { localStorage.setItem('gate-board-done', v ? '1' : '0') } catch (e) {}
            if (v) this.$nextTick(() => this.$refs.board.scrollTo({ left: this.$refs.board.scrollWidth, behavior: 'smooth' }));
        },
        ro: null,
        destroy() { this.ro && this.ro.disconnect() },
        fit() {
            const b = this.$refs.board; if (! b || ! b.offsetParent) return;
            b.style.setProperty('--gp-board-h', Math.max(420, window.innerHeight - (b.getBoundingClientRect().top + window.scrollY)) + 'px');
        },
     }" x-init="ro = new ResizeObserver(() => fit()); ro.observe($refs.board); ro.observe(document.body)" @resize.window.debounce.100ms="fit()">
    <div class="gp-wrap pt-5">
        <div class="flex flex-wrap items-center gap-3">
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

    <div x-ref="board" class="gp-board gp-bleed">
        @foreach ($boardCols as $col)
            @include('flynk-connector::share._column', ['col' => $col])
        @endforeach
        @if ($doneCol)
            <div class="contents" x-show="showDone" style="display: none">@include('flynk-connector::share._column', ['col' => $doneCol, 'collapsible' => true])</div>
            <button type="button" x-show="! showDone" class="gp-col-strip" @click="setDone(true)" title="Erledigte anzeigen ({{ count($doneCol['tasks']) }})">
                @svg('heroicon-o-chevron-double-left', 'w-4 h-4 mt-1')
                <span class="gp-vert">Erledigt</span>
                <span class="inline-flex h-5 min-w-[1.5rem] items-center justify-center rounded-full bg-[rgba(47,158,68,0.16)] px-1 text-[10px] font-semibold tabular-nums">{{ count($doneCol['tasks']) }}</span>
            </button>
        @endif
    </div>
</div>
