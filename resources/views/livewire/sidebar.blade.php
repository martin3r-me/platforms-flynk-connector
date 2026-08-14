<div>
    <div x-show="!collapsed" class="px-3 pt-3 pb-2 border-b border-[#2C3135] mb-2">
        <span class="text-[10px] uppercase tracking-widest text-gray-500 font-medium">FLYNK Connector</span>
    </div>

    {{-- Expanded: Navigation --}}
    <div x-show="!collapsed" class="px-2 mb-1">
        <a href="{{ route('flynk-connector.containers.index') }}" wire:navigate
           class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
            @svg('heroicon-o-squares-2x2', 'w-4 h-4')
            <span>Container</span>
        </a>
        <a href="{{ route('flynk-connector.questions.index') }}" wire:navigate
           class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
            @svg('heroicon-o-chat-bubble-left-right', 'w-4 h-4')
            <span>Rückfragen</span>
            @if($openQuestions > 0)
                <span class="ml-auto text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-[rgb(var(--ui-warning-rgb))]/20 text-[rgb(var(--ui-warning-rgb))]">{{ $openQuestions }}</span>
            @endif
        </a>
    </div>

    {{-- Collapsed: Icons --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[#2C3135]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('flynk-connector.containers.index') }}" wire:navigate
               class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="Container">
                @svg('heroicon-o-squares-2x2', 'w-5 h-5')
            </a>
            <a href="{{ route('flynk-connector.questions.index') }}" wire:navigate
               class="relative flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="Rückfragen">
                @svg('heroicon-o-chat-bubble-left-right', 'w-5 h-5')
                @if($openQuestions > 0)
                    <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-[rgb(var(--ui-warning-rgb))]"></span>
                @endif
            </a>
        </div>
    </div>

    {{-- Baum: Org-Knoten (nach Entity-Typ gruppiert). Klick filtert die Container-Liste. --}}
    <div x-show="!collapsed" class="mt-2 px-2">
        <div class="px-2 mb-1 text-[9px] uppercase tracking-widest text-gray-600 font-medium">Nach Knoten</div>

        @foreach($entityTypeGroups as $typeGroup)
            <div wire:key="type-group-{{ $typeGroup['type_id'] }}" class="mb-2">
                <div class="px-2 mb-0.5 text-[9px] uppercase tracking-widest text-gray-600 font-medium">{{ $typeGroup['type_name'] }}</div>
                @foreach($typeGroup['entities'] as $node)
                    @include('flynk-connector::livewire.partials.sidebar-entity-node', ['node' => $node])
                @endforeach
            </div>
        @endforeach

        {{-- Nicht verortet — als Filter, nicht als Container-Liste --}}
        @if($unassignedContainers->isNotEmpty())
            <a href="{{ route('flynk-connector.containers.index', ['node' => 'none']) }}" wire:navigate
               class="flex items-center gap-1 py-1 px-2 rounded-md hover:bg-[#2C3135] transition-colors">
                <span class="w-3 h-3 flex-shrink-0"></span>
                <span class="flex-1 min-w-0 truncate text-xs font-medium text-gray-400">Nicht verortet</span>
                <span class="text-[10px] tabular-nums text-gray-600">{{ $unassignedContainers->count() }}</span>
            </a>
        @endif

        @if($entityTypeGroups->isEmpty() && $unassignedContainers->isEmpty())
            <div class="px-3 py-2 text-xs text-gray-500">Noch keine Container.</div>
        @endif
    </div>
</div>
