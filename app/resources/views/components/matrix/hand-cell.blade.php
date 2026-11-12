@props([
    'item' => null
])

@if($item)
    @php
        $bgColor = $item['bgColor'] ?? '#047857';
        $handName = $item['name'] ?? $item['hand_name'] ?? '';
        $id = $item['id'] ?? null;
        $ev = $item['ev'] ?? $item['gto_ev'] ?? null;
    @endphp

    <div
        class="hand-cell-container rounded text-center transition"
        data-hand-id="{{ $id }}"
        data-hand-name="{{ $handName }}"
        data-hand-ev="{{ $ev }}"
        style="background-color: {{ $bgColor }} !important; color: #fff !important; font-weight: bold !important; min-height: 60px !important; display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; gap: 3px !important; padding: 3px 2px !important; position: relative !important;"
        {{ $attributes }}
    >
        <button
            type="button"
            data-edit-strategy="{{ $id }}"
            style="font-weight: 900; font-size: 13px; line-height: 1.1; color: inherit;"
            title="{{ __('hand-matrix.edit_strategy') }}"
        >
            {{ $handName }}
            <span data-strategy-count style="font-size: 9px; opacity: 0.75;"></span>
        </button>
        <div style="display: flex; justify-content: center; align-self: center;">
            <x-hand-cell-input
                name="ev_{{ $id }}"
                value="0"
                min="0"
                max="200"
                step="0.01"
                size="small"
            />
        </div>
    </div>
@else
    <div style="background-color: #0f1419;" class="py-2 px-1 rounded"></div>
@endif
