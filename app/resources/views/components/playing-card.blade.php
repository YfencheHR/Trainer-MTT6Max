@props(['card' => '8c', 'revealed' => true])

@php
  // Parsear la carta: primera letra es rango, segunda es palo
  $cardMap = [
    'A' => 'A', 'K' => 'K', 'Q' => 'Q', 'J' => 'J',
    'T' => '10', '9' => '9', '8' => '8', '7' => '7',
    '6' => '6', '5' => '5', '4' => '4', '3' => '3', '2' => '2'
  ];

  $suitMap = [
    'c' => ['♣', '#22c55e', '#22c55e'],  // Clubs (verde)
    'd' => ['♦', '#3b82f6', '#3b82f6'],  // Diamonds (azul)
    'h' => ['♥', '#dc2626', '#dc2626'],  // Hearts (rojo)
    's' => ['♠', '#000000', '#000000'],  // Spades (negro)
  ];

  $rank = strtoupper($card[0] ?? '');
  $suit = strtolower($card[1] ?? '');

  $displayRank = $cardMap[$rank] ?? '?';
  $suitInfo = $suitMap[$suit] ?? ['?', '#000000', '#000000'];
  $suitSymbol = $suitInfo[0];
  $textColor = $suitInfo[1];
@endphp

<div style="width: 100%; aspect-ratio: 4/5; background: white; border: 2px solid #ccc; border-radius: 0.375rem; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); padding: 0.25rem;">
  @if ($revealed)
    <!-- Esquina superior izquierda -->
    <div style="position: absolute; top: 0.1rem; left: 0.1rem; color: {{ $textColor }}; font-size: 0.75rem; line-height: 0.7;">{{ $suitSymbol }}</div>

    <!-- Centro - Valor grande -->
    <div style="text-align: center; font-size: 1.25rem; color: {{ $textColor }}; font-weight: 900; line-height: 1;">
      {{ $displayRank }}
    </div>

    <!-- Esquina inferior derecha (invertida) -->
    <div style="position: absolute; bottom: 0.1rem; right: 0.1rem; color: {{ $textColor }}; font-size: 0.75rem; line-height: 0.7; transform: rotate(180deg);">{{ $suitSymbol }}</div>
  @else
    <!-- Dorso de la carta -->
    <div style="width: 100%; height: 100%; position: absolute; top: 0; left: 0; background: repeating-linear-gradient(45deg, #1e40af, #1e40af 10px, #1e3a8a 10px, #1e3a8a 20px); border-radius: 0.25rem;"></div>
  @endif
</div>
