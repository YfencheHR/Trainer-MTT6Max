@props([
    'hand',
    'bgColor' => '#047857',
    'hoverBorderColor' => 'emerald-500',
    'nameColor' => '#ffffff',
    'valueColor' => '#10b981',
    'buttonBgColor' => '#10b981',
    'isSpecial' => false,
])

<div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 hover:border-{{ $hoverBorderColor }} transition flex flex-col">
  <!-- Header: Name and Value with Combos -->
  <div class="mb-4">
    <div class="flex items-start justify-between mb-2">
      <h3 style="color: {{ $nameColor }};" class="text-lg font-bold flex-1">{{ $hand->name }}</h3>
      <div class="flex gap-4 ml-2">
        <div class="text-right">
          <p style="color: #9ca3af;" class="text-xs uppercase">{{ __('made-hands.value') }}</p>
          <p style="color: {{ $valueColor }};" class="text-sm font-bold">{{ $hand->value }}</p>
        </div>
        <div class="text-right">
          <p style="color: #9ca3af;" class="text-xs uppercase">{{ __('made-hands.combos') }}</p>
          <p style="color: #fbbf24;" class="text-sm font-bold">{{ $hand->combos }}</p>
        </div>
      </div>
    </div>
    @if (!$isSpecial)
      @php
        $prob = (float)($hand->probability ?? 0);
      @endphp
      @if ($prob > 0.0001)
        <div class="text-right">
          <p style="color: #a78bfa; font-size: 16px; font-weight: 700;">{{ number_format($prob, 4) }}%</p>
        </div>
      @endif
    @endif
  </div>

  <!-- Visual Representation -->
  <div class="mb-4 flex flex-wrap gap-2 justify-center" style="min-height: {{ $isSpecial ? '40px' : '60px' }}; display: flex; align-items: center;">
    <x-card-visual :visuals="$hand->visuals" :bgColor="$bgColor" :isSpecial="$isSpecial" />
  </div>

  <!-- Action Button -->
  <div class="pt-4" style="border-top: 1px solid #2e3a48;">
    <a href="{{ route('made-hands.edit', $hand) }}" style="background-color: {{ $buttonBgColor }}; color: white;" class="block w-full hover:opacity-80 text-center px-3 py-2 rounded text-sm font-semibold transition">{{ __('made-hands.view') }}</a>
  </div>
</div>
