<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="FLYNK Rückfragen" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[['label' => 'FLYNK Rückfragen']]">
            <x-slot name="left">
                <div class="flex items-center gap-1">
                    <button wire:click="$set('showAnswered', false)"
                            class="px-2.5 py-1.5 rounded-md text-xs font-medium transition-all {{ !$showAnswered ? 'bg-[rgb(var(--ui-primary-rgb))]/10 text-[rgb(var(--ui-primary-rgb))]' : 'text-[color:var(--ui-secondary)] hover:bg-black/5' }}">
                        Offen ({{ $this->openCount }})
                    </button>
                    <button wire:click="$set('showAnswered', true)"
                            class="px-2.5 py-1.5 rounded-md text-xs font-medium transition-all {{ $showAnswered ? 'bg-[rgb(var(--ui-primary-rgb))]/10 text-[rgb(var(--ui-primary-rgb))]' : 'text-[color:var(--ui-secondary)] hover:bg-black/5' }}">
                        Beantwortet
                    </button>
                </div>
            </x-slot>

            <x-ui-button variant="secondary" size="sm" wire:click="pullNow" wire:loading.attr="disabled">
                @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                Jetzt abrufen
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div class="py-6 max-w-3xl space-y-3">
            @forelse($this->questions as $question)
                <div class="rounded-2xl border border-black/5 bg-white/70 backdrop-blur-sm p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('flynk-connector.containers.show', $question->flynk_container_id) }}" wire:navigate
                               class="text-[11px] text-gray-400 hover:text-[rgb(var(--ui-primary-rgb))] inline-flex items-center gap-1">
                                @svg('heroicon-o-cube', 'w-3 h-3') {{ $question->container?->name ?? 'Container' }}
                            </a>
                            <h3 class="font-semibold text-sm text-gray-900 mt-0.5">{{ $question->title }}</h3>
                        </div>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            @if($question->priority === 'high')
                                <x-ui-badge color="danger" size="xs">Hoch</x-ui-badge>
                            @endif
                            @if($question->answered_at)
                                <x-ui-badge color="success" size="xs">Beantwortet</x-ui-badge>
                            @else
                                <x-ui-badge color="warning" size="xs">Offen</x-ui-badge>
                            @endif
                        </div>
                    </div>

                    @if($question->description)
                        <p class="text-xs text-gray-600 mt-2 whitespace-pre-line leading-relaxed">{{ \Illuminate\Support\Str::limit($question->description, 400) }}</p>
                    @endif

                    <div class="flex items-center gap-3 mt-3 text-[11px] text-gray-400">
                        @if($question->flynk_created_at)
                            <span style="font-family: 'JetBrains Mono', monospace;">{{ $question->flynk_created_at->format('d.m.Y') }}</span>
                        @endif
                        @if($question->target_url)
                            <a href="{{ $question->target_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[rgb(var(--ui-primary-rgb))] hover:underline">
                                @svg('heroicon-o-globe-alt', 'w-3 h-3') Seite
                            </a>
                        @endif
                    </div>

                    @if($question->answered_at)
                        <div class="mt-3 pt-3 border-t border-gray-100">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Unsere Antwort</div>
                            <p class="text-xs text-gray-700 whitespace-pre-line">{{ $question->answer_text }}</p>
                            <div class="text-[10px] text-gray-400 mt-1">{{ $question->answeredBy?->name }} · {{ $question->answered_at->format('d.m.Y H:i') }}</div>
                        </div>
                    @elseif($answeringId === $question->id)
                        <div class="mt-3 pt-3 border-t border-gray-100 space-y-2">
                            <x-ui-input-textarea wire:model="answerText" label="Antwort an FLYNK" rows="3" placeholder="Deine Antwort zur Rückfrage…" />
                            <div class="flex justify-end gap-2">
                                <x-ui-button variant="secondary" size="sm" wire:click="cancelAnswer">Abbrechen</x-ui-button>
                                <x-ui-button variant="primary" size="sm" wire:click="submitAnswer">
                                    @svg('heroicon-o-paper-airplane', 'w-4 h-4')
                                    Senden
                                </x-ui-button>
                            </div>
                        </div>
                    @else
                        <div class="mt-3">
                            <x-ui-button variant="secondary" size="sm" wire:click="startAnswer({{ $question->id }})">
                                @svg('heroicon-o-chat-bubble-left-right', 'w-4 h-4')
                                Antworten
                            </x-ui-button>
                        </div>
                    @endif
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    @svg('heroicon-o-check-circle', 'w-12 h-12 text-gray-300 mb-4')
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">{{ $showAnswered ? 'Keine beantworteten Rückfragen' : 'Keine offenen Rückfragen' }}</h3>
                    <p class="text-xs text-gray-500">{{ $showAnswered ? '' : 'Aktuell sind wir bei keiner FLYNK-Aufgabe am Zug.' }}</p>
                </div>
            @endforelse
        </div>
    </x-ui-page-container>
</x-ui-page>
