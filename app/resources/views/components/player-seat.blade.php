@props([
    'position' => 'top',
    'seat' => 1,
    'name' => 'Player',
    'vpip' => '0',
    'pfr' => '0',
    'three_bet' => '0',
    'stack' => '0',
    'cards' => '[]',
    'status' => 'inactive',
    'badge' => null,
    'no_hud_data' => false
])

@php
    $positions = [
        'top' => ['top' => '-5%', 'left' => '50%', 'transform' => 'translateX(-50%)'],
        'top-right' => ['top' => '8%', 'right' => '4%'],
        'right' => ['top' => '50%', 'right' => '2%', 'transform' => 'translateY(-50%)'],
        'bottom-right' => ['bottom' => '8%', 'right' => '4%'],
        'bottom-left' => ['bottom' => '8%', 'left' => '4%'],
        'left' => ['top' => '50%', 'left' => '2%', 'transform' => 'translateY(-50%)'],
    ];

    $posStyle = $positions[$position] ?? $positions['top'];
    $posString = '';
    foreach ($posStyle as $key => $value) {
        $posString .= "$key: $value; ";
    }

    $statusColors = [
        'bb' => '#ef4444',
        'sb' => '#f59e0b',
        'action' => '#10b981',
        'fold' => '#6b7280',
        'inactive' => '#4b5563',
    ];

    $statusColor = $statusColors[$status] ?? $statusColors['inactive'];
    $bgGradient = match($status) {
        'bb' => 'linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05))',
        'sb' => 'linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05))',
        'action' => 'linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05))',
        'fold' => 'linear-gradient(135deg, rgba(107, 114, 128, 0.05), rgba(107, 114, 128, 0.02))',
        default => 'linear-gradient(135deg, rgba(75, 85, 99, 0.05), rgba(75, 85, 99, 0.02))',
    };

    $cards = json_decode($cards, true) ?? [];
@endphp

<div style="position: absolute; {{ $posString }} width: 140px; text-align: center;">
  <!-- Card de Jugador - Compacta -->
  <div style="background: {{ $bgGradient }}; border: 2px solid {{ $statusColor }}; border-radius: 0.625rem; padding: 0.625rem; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px);">

    <!-- Nombre y Badge en una sola línea -->
    <div style="display: flex; justify-content: center; align-items: center; margin-bottom: 0.5rem; gap: 0.25rem; min-height: 20px;">
      <h3 style="color: #e5e7eb; font-size: 0.75rem; font-weight: 700; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1;">{{ $name }}</h3>
      @if ($badge)
        <span style="background-color: {{ $statusColor }}; color: white; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.625rem; font-weight: 900; white-space: nowrap;">
          {{ $badge }}
        </span>
      @endif
    </div>

    <!-- Stats - Condensado en una línea -->
    @if (!$no_hud_data)
      <div style="display: flex; justify-content: space-around; gap: 0.25rem; margin-bottom: 0.5rem; border-bottom: 1px solid #2e3a48; padding-bottom: 0.375rem;">
        <div style="text-align: center; flex: 1; min-width: 0;">
          <p style="color: #9ca3af; font-size: 0.5rem; font-weight: 600; margin: 0; line-height: 1;">V</p>
          <p style="color: #10b981; font-size: 0.7rem; font-weight: bold; margin: 0;">{{ $vpip }}%</p>
        </div>
        <div style="text-align: center; flex: 1; min-width: 0;">
          <p style="color: #9ca3af; font-size: 0.5rem; font-weight: 600; margin: 0; line-height: 1;">P</p>
          <p style="color: #06b6d4; font-size: 0.7rem; font-weight: bold; margin: 0;">{{ $pfr }}%</p>
        </div>
        <div style="text-align: center; flex: 1; min-width: 0;">
          <p style="color: #9ca3af; font-size: 0.5rem; font-weight: 600; margin: 0; line-height: 1;">3</p>
          <p style="color: #f59e0b; font-size: 0.7rem; font-weight: bold; margin: 0;">{{ $three_bet }}%</p>
        </div>
      </div>
    @else
      <div style="margin-bottom: 0.5rem; border-bottom: 1px solid #2e3a48; padding-bottom: 0.375rem;">
        <p style="color: #f59e0b; font-size: 0.625rem; font-weight: 700; margin: 0; letter-spacing: 0.02em;">S/D</p>
      </div>
    @endif

    <!-- Cartas - Muy Pequeñas -->
    @if (count($cards) > 0)
      <div style="display: flex; gap: 0.375rem; justify-content: center; margin-bottom: 0.375rem;">
        @foreach ($cards as $card)
          <div style="width: 22px; height: 30px; background: linear-gradient(135deg, #1e3a8a, #1e40af); border: 1px solid #60a5fa; border-radius: 0.25rem; display: flex; align-items: center; justify-content: center; color: #60a5fa; font-size: 0.55rem; font-weight: bold; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);">
            {{ $card }}
          </div>
        @endforeach
      </div>
    @endif

    <!-- Stack -->
    <div style="background: rgba(15, 20, 25, 0.8); padding: 0.375rem; border-radius: 0.25rem; border: 1px solid #2e3a48;">
      <p style="color: #9ca3af; font-size: 0.5rem; font-weight: 600; margin: 0; line-height: 1;">STACK</p>
      <p style="color: {{ $statusColor }}; font-size: 0.8rem; font-weight: bold; margin: 0;">{{ $stack }} bb</p>
    </div>
  </div>
</div>
