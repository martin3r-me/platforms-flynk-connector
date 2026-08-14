{{-- Rekursiver Baum-Knoten (nur Org-Knoten, keine Container). Klick auf den Namen
     filtert die Container-Liste auf diesen Knoten (inkl. Unterknoten); Chevron klappt auf. --}}
@props(['node', 'depth' => 0])

@php $hasChildren = $node['children_by_type']->isNotEmpty(); @endphp

<div wire:key="entity-{{ $node['entity_id'] }}"
     x-data="{ open: localStorage.getItem('flynk.entity.{{ $node['entity_id'] }}') !== 'false' }"
     class="flex flex-col">

    <div class="flex items-center gap-1 py-1 px-2 rounded-md hover:bg-[#2C3135] transition-colors">
        @if($hasChildren)
            <button type="button"
                    @click="open = !open; localStorage.setItem('flynk.entity.{{ $node['entity_id'] }}', open)"
                    class="w-3 h-3 flex-shrink-0 flex items-center justify-center text-gray-500 transition-transform" :class="open ? 'rotate-90' : ''">
                @svg('heroicon-o-chevron-right', 'w-2.5 h-2.5')
            </button>
        @else
            <span class="w-3 h-3 flex-shrink-0"></span>
        @endif
        <a href="{{ route('flynk-connector.containers.index', ['node' => $node['entity_id']]) }}" wire:navigate
           class="flex-1 min-w-0 truncate text-xs font-medium text-gray-300 hover:text-white transition-colors">
            {{ $node['entity_name'] }}
        </a>
        <span class="text-[10px] tabular-nums text-gray-600">{{ $node['total_items'] }}</span>
    </div>

    @if($hasChildren)
        <div x-show="open" x-collapse class="flex flex-col ml-3">
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
