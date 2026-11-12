@props([
    'visuals' => null,
    'card' => null,
    'bgColor' => '#047857',
    'textColor' => 'white',
    'padding' => '6px 8px',
    'fontSize' => '12px',
    'minWidth' => '38px',
    'borderRadius' => '4px',
    'size' => 'medium'
])

@php
    // Configuración de tamaños predefinidos
    $sizeConfig = [
        'small' => [
            'padding' => '2px 6px',
            'fontSize' => '11px',
            'minWidth' => '28px',
        ],
        'medium' => [
            'padding' => '6px 8px',
            'fontSize' => '12px',
            'minWidth' => '38px',
        ],
        'large' => [
            'padding' => '8px 10px',
            'fontSize' => '14px',
            'minWidth' => '42px',
        ]
    ];

    $config = $sizeConfig[$size] ?? $sizeConfig['medium'];
    $finalPadding = $padding ?? $config['padding'];
    $finalFontSize = $fontSize ?? $config['fontSize'];
    $finalMinWidth = $minWidth ?? $config['minWidth'];
@endphp

@if ($card)
  {{-- Modo tarjeta individual --}}
  <span style="background-color: {{ $bgColor }}; color: {{ $textColor }}; padding: {{ $finalPadding }}; border-radius: {{ $borderRadius }}; font-size: {{ $finalFontSize }}; font-weight: 600; min-width: {{ $finalMinWidth }}; text-align: center; display: inline-block;"
    {{ $attributes }}>
    {{ $card }}
  </span>
@elseif ($visuals && count($visuals) > 0)
  {{-- Modo array de visuales --}}
  @foreach ($visuals as $visual)
    @foreach ($visual['cards'] as $cardValue)
      <span style="background-color: {{ $bgColor }}; color: {{ $textColor }}; padding: {{ $finalPadding }}; border-radius: {{ $borderRadius }}; font-size: {{ $finalFontSize }}; font-weight: 600; min-width: {{ $finalMinWidth }}; text-align: center; display: inline-block;"
        {{ $attributes }}>
        {{ $cardValue }}
      </span>
    @endforeach
    @if ($visual['separator'] ?? false)
      <span style="color: {{ $bgColor }}; padding: 0 4px; font-weight: 600;">|</span>
    @endif
  @endforeach
@else
  {{-- Fallback cuando no hay datos --}}
  <span style="background-color: {{ $bgColor }}; color: {{ $textColor }}; padding: {{ $finalPadding }}; border-radius: {{ $borderRadius }};">—</span>
@endif
