@props(['label', 'stat', 'value', 'fieldName'])

<div x-data="{ showTooltip: false }" class="min-w-0">
  <div class="flex items-center gap-2 mb-2">
    <label for="{{ $fieldName }}" style="color: #9ca3af;" class="block text-xs uppercase font-bold">{{ $label }}</label>
    @if(!empty(\App\Config\StatSegments::getSegments($stat)))
      <div style="position: relative; display: inline-block;">
        <span style="color: #9ca3af; cursor: help; font-size: 14px;" @mouseenter="showTooltip = true" @mouseleave="showTooltip = false">?</span>
        <div x-show="showTooltip" style="position: absolute; left: 50%; transform: translateX(-50%); top: 100%; margin-top: 8px; width: 350px; background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 6px; padding: 12px; z-index: 50; white-space: normal; font-size: 11px;">
          <div style="display: flex; flex-direction: column; gap: 6px;">
            @foreach(\App\Config\StatSegments::getSegments($stat) as $segment)
              <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #2e3a48;">
                <span style="color: #ffffff; font-weight: 600;">{{ round($segment['min'], 1) }}-{{ round($segment['max'], 1) }}%</span>
                <span style="color: {{ $segment['color'] }}; font-size: 10px;">{{ $segment['label'] }}</span>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    @endif
  </div>
  @php
    // Determinar el step, min y max según el tipo de estadística
    $step = 1;
    $min = 0;
    $max = 100;

    // AF (Aggression Factor) y VB Ratio: 0-10
    if (strpos($stat, 'af_') === 0 || $stat === 'af' || $stat === 'vb_ratio') {
      $step = 0.1;
      $max = 10;
    }
    // Todas las demás estadísticas: porcentajes 0-100
    else {
      $step = 1;
      $max = 100;
    }
  @endphp

  <x-number-input
    name="{{ $fieldName }}"
    value="{{ $value }}"
    min="{{ $min }}"
    max="{{ $max }}"
    step="{{ $step }}"
  />
</div>
