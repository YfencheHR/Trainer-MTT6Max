@props(['hand' => null, 'currentUser' => null, 'noHudData' => false])

@php
    $gameTableManager = app(\App\Services\GameTable\GameTableManager::class);
    $positionService = app(\App\Services\GameTable\TablePositionService::class);

    $tableData = null;
    $numPlayers = 0;
    $visibleSeats = [];

    if ($hand) {
        try {
            $tableData = $gameTableManager->getTableData($hand);
            $numPlayers = count($tableData['players'] ?? []);
            $visibleSeats = $tableData['visible_seats'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }
    }
@endphp

<div x-data='gameTable({{ $hand->id ?? 0 }}, @json(auth()->id()), @json($tableData))' style="color: #e5e7eb;">

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

  {{-- Mesa: el padding deja sitio a los asientos que sobresalen del óvalo --}}
  <div style="padding: 5.5rem clamp(1.5rem, 5vw, 5rem) 5.5rem;">
    <div style="position: relative; width: 100%; aspect-ratio: 16 / 9; min-height: 470px; background: radial-gradient(ellipse at center, #0d6b45 0%, #0a3d2a 100%); border: 10px solid #2a5f4f; border-radius: 50%; box-shadow: 0 0 50px rgba(10, 20, 15, 0.8), inset 0 0 60px rgba(0, 0, 0, 0.5);">

      {{-- Centro: bote + board --}}
      <div style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.75rem; z-index: 1;">
        <div style="background: rgba(0,0,0,0.45); padding: 0.2rem 0.8rem; border-radius: 999px; font-size: 0.85rem; font-weight: 700; color: #fbbf24;" x-show="pot > 0">
          Bote: <span x-text="formatBB(pot)"></span>
        </div>
        <div style="display: flex; gap: 0.5rem;">
          @for ($i = 0; $i < 5; $i++)
            <div style="width: 52px; height: 74px; border-radius: 0.4rem; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.35rem;"
              :style="board.length > {{ $i }}
                ? `background: #fff; color: ${parseCard(board[{{ $i }}]).color}; box-shadow: 0 4px 10px rgba(0,0,0,0.5); border: 1px solid #d1d5db;`
                : 'border: 2px dashed rgba(16,185,129,0.45); background: rgba(0,0,0,0.25);'">
              <span x-show="board.length > {{ $i }}" x-text="(parseCard(board[{{ $i }}]).value) + (parseCard(board[{{ $i }}]).suit)"></span>
            </div>
          @endfor
        </div>
      </div>

      {{-- Apuestas de los jugadores --}}
      @if($tableData && $numPlayers > 0)
        @foreach ($visibleSeats as $slot)
          <x-bet-chip :seat="$slot" :pos="$positionService->getBetStyle($numPlayers, $slot)" />
        @endforeach
      @endif

      {{-- Asientos (slot 0 = héroe, abajo en el centro) --}}
      @if($tableData && $numPlayers > 0)
        @foreach ($visibleSeats as $slot)
          @php $pl = "getSafePlayer({$slot})"; @endphp
          <div x-show="getPlayerAtPosition({{ $slot }})" x-cloak style="{{ $positionService->getPositionStyle($numPlayers, $slot) }} z-index: 3;">
            {{-- Última decisión del servidor, representada sobre el asiento --}}
            <div x-show="lastAction && lastAction.player_id === getSafePlayer({{ $slot }}).id && !gameCompleted"
              style="position: absolute; top: -1.35rem; left: 50%; transform: translateX(-50%); z-index: 4; white-space: nowrap; background: #fbbf24; color: #1f2937; font-size: 0.68rem; font-weight: 800; padding: 0.1rem 0.55rem; border-radius: 999px; box-shadow: 0 2px 6px rgba(0,0,0,0.5);"
              x-text="lastAction ? lastAction.text.replace(/^hizo /, '').toUpperCase() : ''"></div>
            <div style="background: linear-gradient(180deg, #1c2430, #151b24); border-radius: 0.6rem; padding: 0.45rem 0.5rem 0.4rem; transition: opacity .2s, box-shadow .2s;"
              :style="{
                opacity: {{ $pl }}.is_folded ? 0.4 : 1,
                border: {{ $pl }}.is_turn ? '2px solid #fbbf24' : '1px solid #2e3a48',
                boxShadow: {{ $pl }}.is_turn ? '0 0 14px rgba(251,191,36,0.6)' : '0 3px 10px rgba(0,0,0,0.55)'
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

  {{-- Barra de acciones --}}
  <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: stretch; padding: 1rem; background: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem;">
    <div style="min-width: 180px;">
      <p style="margin: 0; font-size: 0.7rem; color: #9ca3af; letter-spacing: 0.05em;">TU STACK</p>
      <p style="margin: 0; font-size: 1.4rem; font-weight: 700; color: #60a5fa;" x-text="formatBB(heroStack)"></p>
      <p style="margin: 0.35rem 0 0; font-size: 0.8rem; color: #9ca3af;" x-text="gameCompleted ? 'Mano terminada' : (botWaiting ? '🤖 Bot pensando...' : (actionInProgress ? '⏳ Procesando...' : (canPlayerAction() ? '🟡 Tu turno' : '⏸️ Esperando turno')))"></p>
    </div>

    <div x-show="!gameCompleted" style="flex: 1; display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center; justify-content: flex-end;">
      @php
          $btn = 'padding: 0.8rem 1.1rem; border: none; border-radius: 0.4rem; color: #fff; font-weight: 700; font-size: 0.85rem; cursor: pointer;';
      @endphp

      <p x-show="!legal" style="margin: 0; font-size: 0.85rem; color: #9ca3af;">Esperando a los demás jugadores…</p>

      <template x-if="legal">
        <div style="display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center; justify-content: flex-end;">
          <button @click="playerFold()" :disabled="actionInProgress" style="{{ $btn }} background: #ef4444;" :style="{ opacity: actionInProgress ? 0.4 : 1 }">Fold</button>

          <button x-show="legal.check" @click="playerCheck()" :disabled="actionInProgress" style="{{ $btn }} background: #3b82f6;" :style="{ opacity: actionInProgress ? 0.4 : 1 }">Check</button>

          <button x-show="legal.call !== null" @click="playerCall()" :disabled="actionInProgress" style="{{ $btn }} background: #10b981;" :style="{ opacity: actionInProgress ? 0.4 : 1 }">
            <span x-text="'Call ' + formatBB(legal.call)"></span>
          </button>

          <div x-show="legal.raise" style="display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: stretch;">
            <button @click="setRaiseSize(0)" style="padding: 0 0.6rem; border: 1px solid #4b5563; border-radius: 0.4rem; background: transparent; color: #d1d5db; font-size: 0.72rem; cursor: pointer;">Mín</button>
            <button @click="setRaiseSize(0.5)" style="padding: 0 0.6rem; border: 1px solid #4b5563; border-radius: 0.4rem; background: transparent; color: #d1d5db; font-size: 0.72rem; cursor: pointer;">½ bote</button>
            <button @click="setRaiseSize(1)" style="padding: 0 0.6rem; border: 1px solid #4b5563; border-radius: 0.4rem; background: transparent; color: #d1d5db; font-size: 0.72rem; cursor: pointer;">Bote</button>
            <label for="raise-to" style="align-self: center; font-size: 0.72rem; color: #d1d5db;">Subir a</label>
            <input id="raise-to" type="number" x-model.number="raiseTo" @change="clampRaise()" :min="legal.raise?.min" :max="legal.raise?.max" :step="bigBlind / 2" :disabled="actionInProgress" style="width: 96px; background: rgba(0,0,0,0.4); color: #e5e7eb; border: 1px solid #4b5563; border-radius: 0.4rem; padding: 0 0.5rem; font-size: 0.85rem;">
            <button @click="playerRaise()" :disabled="actionInProgress" style="{{ $btn }} background: #7c3aed;" :style="{ opacity: actionInProgress ? 0.4 : 1 }">
              <span x-text="(legal.is_bet ? 'Bet ' : 'Raise a ') + formatBB(raiseTo)"></span>
            </button>
          </div>

          <button x-show="legal.allin !== null" @click="playerAllIn()" :disabled="actionInProgress" style="{{ $btn }} background: #f59e0b;" :style="{ opacity: actionInProgress ? 0.4 : 1 }">
            <span x-text="'All-in ' + formatBB(legal.allin)"></span>
          </button>

          <p x-show="legal.raise" style="margin: 0; width: 100%; text-align: right; font-size: 0.72rem; color: #9ca3af;"
            x-text="'Mín. ' + formatBB(legal.raise?.min) + ' · Máx. ' + formatBB(legal.raise?.max)"></p>
          <p x-show="!legal.raise && legal.call !== null" style="margin: 0; width: 100%; text-align: right; font-size: 0.72rem; color: #9ca3af;">No puedes subir: solo igualar, retirarte o ir all-in por lo que tienes.</p>
        </div>
      </template>
    </div>
    <div x-show="gameCompleted" style="flex: 1; display: flex; gap: 1rem; align-items: center; justify-content: flex-end;">
      <p style="margin: 0; font-size: 0.85rem; color: #d1d5db;" x-text="error || ''"></p>
      <button @click="nextHand()" :disabled="nextHandLoading" :style="{ opacity: nextHandLoading ? 0.7 : 1, cursor: nextHandLoading ? 'wait' : 'pointer' }" style="min-width: 190px; padding: 0.9rem 1.4rem; border: none; border-radius: 0.4rem; background: #059669; color: #fff; font-weight: 700; cursor: pointer;"><span x-text="nextHandLoading ? ('⏳ ' + (nextHandProgress || 'Cargando…')) : '▶ Siguiente mano'"></span></button>
    </div>
  </div>
</div>
