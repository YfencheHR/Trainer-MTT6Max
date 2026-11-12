@props(['seat' => 0, 'pos' => ''])

{{-- Apuesta de un jugador (en bb) sobre la mesa. Requiere el scope Alpine de gameTable. --}}
<div class="apuesta" style="display: none;" x-cloak
  x-show="players.length > {{ $seat }} && getSafePlayer({{ $seat }}).show_bet && Number(getSafePlayer({{ $seat }}).bet) > 0 && formatBetBB(getSafePlayer({{ $seat }}).bet) !== '0bb' && !gameCompleted"
  x-effect="syncBetChip($el, {{ $seat }}, getSafePlayer({{ $seat }}))"></div>
