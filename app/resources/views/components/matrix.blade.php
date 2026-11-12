@props([
    'columns' => 13,
    'gap' => '1px',
    'items' => [],
    'cellComponent' => 'matrix-cell'
])

<div class="grid" style="grid-template-columns: repeat({{ $columns }}, 1fr); gap: {{ $gap }}; min-width: fit-content;">
    @foreach($items as $item)
        <x-dynamic-component
            :component="$cellComponent"
            :item="$item"
        />
    @endforeach

    {{ $slot }}
</div>
