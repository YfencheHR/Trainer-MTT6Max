@props([
    'item' => null,
    'clickable' => true,
    'onClick' => null
])

@if($item)
    <button
        type="button"
        class="matrix-cell py-1 px-0.5 rounded text-center transition hover:opacity-80"
        @if($clickable) style="cursor: pointer;" @endif
        @if($onClick) wire:click="{{ $onClick }}" @endif
        data-item-id="{{ $item['id'] ?? '' }}"
        data-item-data="{{ json_encode($item) }}"
        {{ $attributes }}
    >
        {{ $slot }}
    </button>
@else
    <div style="background-color: #0f1419;" class="py-1 px-0.5 rounded"></div>
@endif
