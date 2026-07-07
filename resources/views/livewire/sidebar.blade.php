<div>
    <div x-show="!collapsed" class="px-3 pt-3 pb-2 border-b border-[#2C3135] mb-2">
        <span class="text-[10px] uppercase tracking-widest text-gray-500 font-medium">FLYNK Connector</span>
    </div>

    {{-- Expanded --}}
    <div x-show="!collapsed" class="px-2 mb-1">
        <a href="{{ route('flynk-connector.containers.index') }}" wire:navigate
           class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
            @svg('heroicon-o-squares-2x2', 'w-4 h-4')
            <span>Container</span>
        </a>
    </div>

    {{-- Collapsed --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[#2C3135]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('flynk-connector.containers.index') }}" wire:navigate
               class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="Container">
                @svg('heroicon-o-squares-2x2', 'w-5 h-5')
            </a>
        </div>
    </div>

    {{-- Container-Liste --}}
    <div x-show="!collapsed" class="mt-2 px-2 space-y-0.5">
        @foreach($containers as $container)
            <a href="{{ route('flynk-connector.containers.show', $container) }}" wire:navigate
               class="flex items-center gap-2 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 bg-[rgb(var(--ui-{{ $container->status->color() }}-rgb))]"></span>
                <span class="truncate">{{ $container->name }}</span>
            </a>
        @endforeach
    </div>
</div>
