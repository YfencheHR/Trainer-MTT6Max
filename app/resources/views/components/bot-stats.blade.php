@props([
    'stats' => [],
    'no_hud_data' => false,
    'colors' => [
        'vpip' => '#10b981',
        'pfr' => '#06b6d4',
        'threeBet' => '#f59e0b',
        'af' => '#ec4899'
    ]
])

@if ($no_hud_data)
  <div style="text-align: center; padding: 0.5rem; background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 0.25rem;">
    <p style="color: #f59e0b; font-size: 10px; margin: 0; font-weight: 600;">SIN DATOS</p>
    <p style="color: #9ca3af; font-size: 8px; margin: 0; margin-top: 2px;">Tourney Stats</p>
  </div>
@else
  <div style="display: flex; gap: 0.5rem; align-items: flex-start;">
  <!-- VPIP -->
  <div style="text-align: center;">
    <p style="color: #9ca3af; font-size: 9px; margin: 0; line-height: 1;">VPIP</p>
    <p style="color: {{ $colors['vpip'] }}; font-weight: 600; font-size: 11px; margin: 0; line-height: 1.2;">
      {{ $stats['vpip'] ?? '-' }}%
    </p>
  </div>

  <!-- PFR -->
  <div style="text-align: center;">
    <p style="color: #9ca3af; font-size: 9px; margin: 0; line-height: 1;">PFR</p>
    <p style="color: {{ $colors['pfr'] }}; font-weight: 600; font-size: 11px; margin: 0; line-height: 1.2;">
      {{ $stats['pfr'] ?? '-' }}%
    </p>
  </div>

  <!-- 3-BET -->
  <div style="text-align: center;">
    <p style="color: #9ca3af; font-size: 9px; margin: 0; line-height: 1;">3-BET</p>
    <p style="color: {{ $colors['threeBet'] }}; font-weight: 600; font-size: 11px; margin: 0; line-height: 1.2;">
      {{ $stats['three_bet'] ?? '-' }}%
    </p>
  </div>

  <!-- AF -->
  <div style="text-align: center;">
    <p style="color: #9ca3af; font-size: 9px; margin: 0; line-height: 1;">AF</p>
    <p style="color: {{ $colors['af'] }}; font-weight: 600; font-size: 11px; margin: 0; line-height: 1.2;">
      {{ number_format($stats['af'] ?? 0, 1) }}
    </p>
  </div>
  </div>
@endif
