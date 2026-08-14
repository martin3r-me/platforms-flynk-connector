{{-- Rekursiver Baum-Knoten: Org-Entity mit ihren Containern + Unterknoten. --}}
@props(['node', 'depth' => 0])

@php
    $hasChildren   = $node['children_by_type']->isNotEmpty();
    $hasContainers = $node['containers']->isNotEmpty();
    $expandable    = $hasChildren || $hasContainers;
@endphp

<div wire:key="entity-{{ $node['entity_id'] }}"
     x-data="{ open: localStorage.getItem('flynk.entity.{{ $node['entity_id'] }}') !== 'false' }"
     class="flex flex-col">

    {{-- Knoten-Zeile --}}
    <div class="flex items-center gap-1 py-1 px-2 rounded-md hover:bg-[#2C3135] transition-colors cursor-pointer select-none"
         @if($expandable) @click="open = !open; localStorage.setItem('flynk.entity.{{ $node['entity_id'] }}', open)" @endif>
        @if($expandable)
            <span class="w-3 h-3 flex-shrink-0 flex items-center justify-center text-gray-500 transition-transform" :class="open ? 'rotate-90' : ''">
                @svg('heroicon-o-chevron-right', 'w-2.5 h-2.5')
            </span>
        @else
            <span class="w-3 h-3 flex-shrink-0"></span>
        @endif
        <span class="flex-1 min-w-0 truncate text-xs font-medium text-gray-300">{{ $node['entity_name'] }}</span>
        <span class="text-[10px] tabular-nums text-gray-600">{{ $node['total_items'] }}</span>
    </div>

    @if($expandable)
        <div x-show="open" x-collapse class="flex flex-col ml-3 border-l border-[#2C3135]">
            {{-- Container direkt an diesem Knoten --}}
            @foreach($node['containers'] as $container)
                <a href="{{ route('flynk-connector.containers.show', $container) }}" wire:navigate
                   class="flex items-center gap-2 pl-3 pr-2 py-1 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 bg-[rgb(var(--ui-{{ $container->status->color() }}-rgb))]"></span>
                    <span class="truncate">{{ $container->name }}</span>
                </a>
            @endforeach

            {{-- Unterknoten, nach Typ gruppiert --}}
            @foreach($node['children_by_type'] as $typeGroup)
                <div wire:key="entity-{{ $node['entity_id'] }}-type-{{ $typeGroup['type_id'] }}" class="flex flex-col">
                    <div class="text-[9px] uppercase tracking-wider text-gray-600 pl-3 mt-1 mb-0.5">{{ $typeGroup['type_name'] }}</div>
                    @foreach($typeGroup['children'] as $child)
                        @include('flynk-connector::livewire.partials.sidebar-entity-node', ['node' => $child, 'depth' => $depth + 1])
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</div>
