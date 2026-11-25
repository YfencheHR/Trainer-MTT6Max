@props(['tableData' => null, 'numPlayers' => 0])

@php
    $seatSlots = range(0, 5);
@endphp

{{-- La mesa tal como se ve en el juego (barra de mano/bote/calle y el óvalo con asientos, apuestas y board). Pinta el estado de un componente Alpine
     (gameTable en la partida, replayTable al repetir una mano), así que las dos mesas son la misma. --}}
  {{-- Barra superior: mano / bote / calle --}}
  <div style="display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 1rem; padding: 0.75rem 1rem; background: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem;">
    <div>
      <p style="margin: 0; font-size: 0.7rem; color: #9ca3af; letter-spacing: 0.05em;">MANO</p>
      <p style="margin: 0; font-size: 1.1rem; font-weight: 700;" x-text="'#' + ($store.tournamentInfo.data.hand_number ?? handId)"></p>
    </div>
    <div style="text-align: center;">
      <p style="margin: 0; font-size: 0.7rem; color: #9ca3af; letter-spacing: 0.05em;">BOTE</p>
      <p style="margin: 0; font-size: 1.5rem; font-weight: 700; color: #10b981;" x-text="formatBB(pot)"></p>
    </div>
    <div style="text-align: right;">
      <p style="margin: 0; font-size: 0.7rem; color: #9ca3af; letter-spacing: 0.05em;">CALLE</p>
      <p style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #10b981; text-transform: capitalize;" x-text="currentStreet"></p>
    </div>
  </div>
  <div x-show="(nextHandLoading || error) && !gameCompleted" x-cloak
    style="margin-top: 0.5rem; padding: 0.55rem 0.75rem; background: #1f2937; border-radius: 0.4rem; color: #fbbf24; font-size: 0.8rem;"
    x-text="error || nextHandProgress || 'Cargando mesa…'"></div>

  {{-- Mesa: el padding deja sitio a los asientos que sobresalen del óvalo --}}
  <div style="padding: 5.5rem clamp(1.5rem, 5vw, 5rem) 5.5rem;">
    <div style="position: relative; width: 100%; aspect-ratio: 16 / 9; min-height: 470px; background: radial-gradient(ellipse at center, #0d6b45 0%, #0a3d2a 100%); border: 10px solid #2a5f4f; border-radius: 50%; box-shadow: 0 0 50px rgba(10, 20, 15, 0.8), inset 0 0 60px rgba(0, 0, 0, 0.5);">

      {{-- Centro: board --}}
      <div style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.75rem; z-index: 1;">
        <div style="display: flex; gap: 0.5rem;">
          @for ($i = 0; $i < 5; $i++)
            <div style="position: relative; flex: 0 1 52px; width: 52px; aspect-ratio: 52 / 73; border-radius: 0.4rem; overflow: hidden;"
              :style="board.length > {{ $i }}
                ? { background: '#fff', color: parseCard(board[{{ $i }}]).color, boxShadow: '0 4px 10px rgba(0,0,0,0.5)', border: '1px solid #d1d5db' }
                : 'border: 2px dashed rgba(16,185,129,0.45); background: rgba(0,0,0,0.25);'">
              <template x-if="board.length > {{ $i }}">
                <div style="position: absolute; inset: 0;">
                  <span style="position: absolute; top: 3px; left: 4px; font-size: 0.85rem; line-height: 1;" x-text="parseCard(board[{{ $i }}]).suit"></span>
                  <span style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 1.65rem; font-weight: 800; line-height: 1;" x-text="parseCard(board[{{ $i }}]).value"></span>
                  <span style="position: absolute; bottom: 3px; right: 4px; font-size: 0.85rem; line-height: 1;" x-text="parseCard(board[{{ $i }}]).suit"></span>
                </div>
              </template>
            </div>
          @endfor
        </div>

        {{-- Resumen de la mano: quién gana, cuánto y con qué; se queda hasta que empieza la siguiente --}}
        <div x-show="gameCompleted && handResult" x-cloak x-transition.opacity.duration.400ms
          style="min-width: 260px; max-width: 420px; padding: 0.6rem 1rem; background: rgba(17,24,39,0.92); border: 1px solid #fbbf24; border-radius: 0.7rem; box-shadow: 0 0 22px rgba(251,191,36,0.35); text-align: center;">
          <template x-for="winner in (handResult?.winners || [])" :key="winner.id">
            <p style="margin: 0; font-size: 0.95rem; font-weight: 800; color: #fbbf24;">
              🏆 <span x-text="winner.name"></span> {{ __('tournaments.hand_wins') }} <span x-text="formatBB(winner.amount)"></span>
              <span x-show="winner.hand_name" style="color: #e5e7eb; font-weight: 600;"> {{ __('tournaments.hand_with') }} <span x-text="winner.hand_name"></span></span>
            </p>
          </template>
          <template x-if="handResult && handResult.pots.length > 1">
            <div style="margin-top: 0.35rem; font-size: 0.7rem; color: #9ca3af;">
              <template x-for="pot in handResult.pots" :key="pot.label">
                <p style="margin: 0;"><span x-text="pot.label"></span> (<span x-text="formatBB(pot.amount)"></span>): <span x-text="pot.winners.map(w => w.name).join(', ')"></span></p>
              </template>
            </div>
          </template>
          <p x-show="handResult && !handResult.showdown" style="margin: 0.2rem 0 0; font-size: 0.7rem; color: #9ca3af;">{{ __('tournaments.hand_no_showdown') }}</p>
          {{-- Cuenta atrás hasta la siguiente mano (solo si avanza sola) --}}
          <div x-show="advanceTotal > 0 && advanceLeft > 0" style="margin-top: 0.5rem;">
            <div style="height: 3px; background: #374151; border-radius: 2px; overflow: hidden;">
              <div style="height: 100%; background: #fbbf24; transition: width .1s linear;" :style="{ width: (100 * advanceLeft / advanceTotal) + '%' }"></div>
            </div>
            <button type="button" @click="skipResultPause()" style="margin-top: 0.4rem; background: transparent; border: 1px solid #4b5563; color: #d1d5db; font-size: 0.7rem; padding: 0.15rem 0.7rem; border-radius: 999px; cursor: pointer;">{{ __('tournaments.hand_continue') }} ▶</button>
          </div>
        </div>
      </div>

      {{-- Apuestas de los jugadores --}}
      @if($tableData && $numPlayers > 0)
        @foreach ($seatSlots as $slot)
          <x-bet-chip :seat="$slot" />
        @endforeach
      @endif

      {{-- Asientos (slot 0 = héroe, abajo en el centro) --}}
      @if($tableData && $numPlayers > 0)
        @foreach ($seatSlots as $slot)
          @php $pl = "getSafePlayer({$slot})"; @endphp
          <div x-show="players.length > {{ $slot }}" x-cloak :style="getPositionStyle({{ $slot }})">
            {{-- Última decisión del servidor, representada sobre el asiento --}}
            <div x-show="lastAction && lastAction.player_id === getSafePlayer({{ $slot }}).id && !gameCompleted"
              style="position: absolute; top: -1.35rem; left: 50%; transform: translateX(-50%); z-index: 4; white-space: nowrap; background: #fbbf24; color: #1f2937; font-size: 0.68rem; font-weight: 800; padding: 0.1rem 0.55rem; border-radius: 999px; box-shadow: 0 2px 6px rgba(0,0,0,0.5);"
              x-text="lastAction ? lastAction.text.replace(/^hizo /, '').toUpperCase() : ''"></div>
            {{-- Ganador de la mano --}}
            <div x-show="gameCompleted && {{ $pl }}.won > 0" x-cloak
              style="position: absolute; top: -1.35rem; left: 50%; transform: translateX(-50%); z-index: 5; white-space: nowrap; background: #fbbf24; color: #1f2937; font-size: 0.72rem; font-weight: 800; padding: 0.12rem 0.65rem; border-radius: 999px; box-shadow: 0 2px 10px rgba(251,191,36,0.7);"
              x-text="'🏆 +' + formatBB({{ $pl }}.won)"></div>
            <div style="background: linear-gradient(180deg, #1c2430, #151b24); border-radius: 0.6rem; padding: 0.45rem 0.5rem 0.4rem; transition: opacity .2s, box-shadow .2s;"
              :style="{
                opacity: ({{ $pl }}.is_folded || (gameCompleted && handResult && !({{ $pl }}.won > 0) && !{{ $pl }}.hole_cards?.length)) ? 0.4 : 1,
                border: ({{ $pl }}.is_turn || (gameCompleted && {{ $pl }}.won > 0)) ? '2px solid #fbbf24' : '1px solid #2e3a48',
                boxShadow: (gameCompleted && {{ $pl }}.won > 0) ? '0 0 26px rgba(251,191,36,0.95)' : ({{ $pl }}.is_turn ? '0 0 14px rgba(251,191,36,0.6)' : '0 3px 10px rgba(0,0,0,0.55)')
              }">

              {{-- Cabecera: posición + avatar + nombre --}}
              <div style="display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.35rem;">
                <span x-text="{{ $pl }}.position" style="background: #2b3441; color: #cbd5e1; font-size: 0.6rem; font-weight: 700; padding: 0.05rem 0.3rem; border-radius: 0.3rem;"></span>
                <span style="width: 1.15rem; height: 1.15rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem; flex-shrink: 0; border: 1px solid #3b82f6;"
                  :style="{ background: {{ $pl }}.is_current ? '#1d4ed8' : '#1e3a5f' }" x-text="{{ $pl }}.is_bot ? '🤖' : '👤'"></span>
                <span style="font-size: 0.78rem; font-weight: 700; color: #f3f4f6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="{{ $pl }}.name"></span>
              </div>

              {{-- HUD: VPIP / PFR / 3B --}}
              <div x-show="{{ $pl }}.hud" style="margin-bottom: 0.4rem;" :style="{ opacity: {{ $pl }}.hud?.source === 'observed' ? 1 : 0.7 }" :title="{{ $pl }}.hud?.source === 'observed' ? ('Observado en ' + {{ $pl }}.hud.hands + ' manos') : 'Perfil del bot (aún sin muestra suficiente)'"><div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.25rem;">
                <div style="background: rgba(0,0,0,0.3); border-radius: 0.3rem; text-align: center; padding: 0.1rem 0;">
                  <div style="font-size: 0.85rem; font-weight: 800; line-height: 1.1;" :style="{ color: {{ $pl }}.hud?.vpip_color || '#f97316' }" x-text="{{ $pl }}.hud?.vpip ?? '–'"></div>
                  <div style="font-size: 0.5rem; color: #9ca3af;">VPIP</div>
                </div>
                <div style="background: rgba(0,0,0,0.3); border-radius: 0.3rem; text-align: center; padding: 0.1rem 0;">
                  <div style="font-size: 0.85rem; font-weight: 800; line-height: 1.1;" :style="{ color: {{ $pl }}.hud?.pfr_color || '#60a5fa' }" x-text="{{ $pl }}.hud?.pfr ?? '–'"></div>
                  <div style="font-size: 0.5rem; color: #9ca3af;">PFR</div>
                </div>
                <div style="background: rgba(0,0,0,0.3); border-radius: 0.3rem; text-align: center; padding: 0.1rem 0;">
                  <div style="font-size: 0.85rem; font-weight: 800; line-height: 1.1; color: #e5e7eb;" x-text="{{ $pl }}.hud?.three_bet ?? '–'"></div>
                  <div style="font-size: 0.5rem; color: #9ca3af;">3B</div>
                </div>
              </div></div>

              {{-- Cartas --}}
              <div style="display: flex; gap: 0.3rem; justify-content: center; margin-bottom: 0.35rem;">
                @for ($c = 0; $c < 2; $c++)
                  {{-- Carta de 52x73 (≈30% más grande que la original de 40x56): valor al centro, palo en esquinas alternas (arriba izq. / abajo der.) --}}
                  <div class="carta-reverso" style="position: relative; flex: 1 1 0; max-width: 52px; aspect-ratio: 52 / 73; border-radius: 0.4rem; overflow: hidden;"
                    :style="{{ $pl }}.hole_cards?.[{{ $c }}] ? { background: '#fff', border: '1px solid #d1d5db', color: parseCard({{ $pl }}.hole_cards[{{ $c }}]).color } : {}">
                    <template x-if="{{ $pl }}.hole_cards?.[{{ $c }}]">
                      <div style="position: absolute; inset: 0;">
                        <span style="position: absolute; top: 3px; left: 4px; font-size: 0.85rem; line-height: 1;" x-text="parseCard({{ $pl }}.hole_cards[{{ $c }}]).suit"></span>
                        <span style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 1.65rem; font-weight: 800; line-height: 1;" x-text="parseCard({{ $pl }}.hole_cards[{{ $c }}]).value"></span>
                        <span style="position: absolute; bottom: 3px; right: 4px; font-size: 0.85rem; line-height: 1;" x-text="parseCard({{ $pl }}.hole_cards[{{ $c }}]).suit"></span>
                      </div>
                    </template>
                  </div>
                @endfor
              </div>
              {{-- Stack y posición --}}
              <div style="text-align: center; line-height: 1.2;">
                <div style="font-size: 0.9rem; font-weight: 700; color: #f3f4f6;" x-text="{{ $pl }}.is_all_in ? 'ALL-IN' : formatBB({{ $pl }}.stack)" :style="{ color: {{ $pl }}.is_all_in ? '#ef4444' : '#f3f4f6' }"></div>
                <div style="font-size: 0.68rem; font-weight: 700; color: #f59e0b;" x-text="{{ $pl }}.is_folded ? 'FOLD' : {{ $pl }}.position"></div>
              </div>
            </div>
          </div>
        @endforeach
      @endif
    </div>
  </div>
