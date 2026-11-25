@props(['hand' => null, 'currentUser' => null, 'noHudData' => false])

@php
    $gameTableManager = app(\App\Services\GameTable\GameTableManager::class);
    $positionService = app(\App\Services\GameTable\TablePositionService::class);

    $tableData = null;
    $numPlayers = 0;
    $seatSlots = range(0, 5);

    if ($hand) {
        try {
            $tableData = $gameTableManager->getTableData($hand);
            $numPlayers = count($tableData['players'] ?? []);
        } catch (\Throwable $e) {
            report($e);
        }
    }
@endphp

<div x-data='gameTable({{ $hand->id ?? 0 }}, @json(auth()->id()), @json($tableData))' @@switch-game-table.window="switchTable($event.detail)" style="color: #e5e7eb;">

  <x-poker-table :tableData="$tableData" :numPlayers="$numPlayers" />

  {{-- Barra de acciones --}}
  <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: stretch; padding: 1rem; background: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem;">
    <div style="min-width: 180px;">
      <p style="margin: 0; font-size: 0.7rem; color: #9ca3af; letter-spacing: 0.05em;">TU STACK</p>
      <p style="margin: 0; font-size: 1.4rem; font-weight: 700; color: #60a5fa;" x-text="formatBB(heroStack)"></p>
      <p style="margin: 0.35rem 0 0; font-size: 0.8rem; color: #9ca3af;" x-text="gameCompleted ? 'Mano terminada' : (botWaiting ? '🤖 Bot pensando...' : (actionInProgress ? '⏳ Procesando...' : (canPlayerAction() ? '🟡 Tu turno' : '⏸️ Esperando turno')))"></p>
    </div>

    <button
      x-show="!isSpectatorMode()"
      @click="toggleAutoAdvance()"
      :aria-pressed="autoAdvance"
      :style="{
        background: autoAdvance ? '#047857' : '#374151',
        borderColor: autoAdvance ? '#10b981' : '#6b7280'
      }"
      style="align-self: center; padding: 0.65rem 0.9rem; border: 1px solid; border-radius: 0.4rem; color: #fff; font-weight: 700; cursor: pointer; white-space: nowrap;"
      x-text="autoAdvance ? '⏩ Automático' : '⏸ Manual'"
      :title="autoAdvance ? 'La siguiente mano comenzará automáticamente' : 'Pulsa Siguiente mano al terminar cada mano'">
    </button>

    <div x-show="!gameCompleted" style="flex: 1; display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; justify-content: flex-end;">
      @php
          $btn = 'min-height: 42px; padding: 0.65rem 1rem; border: 1px solid transparent; border-radius: 0.55rem; color: #fff; font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: background-color .15s, border-color .15s, opacity .15s;';
          $subBtn = 'min-height: 36px; padding: 0.4rem 0.7rem; border: 1px solid #3b4654; border-radius: 0.45rem; background: #202a36; color: #d1d5db; font-size: 0.75rem; font-weight: 600; cursor: pointer;';
      @endphp

      <p x-show="!legal" style="margin: 0; font-size: 0.85rem; color: #9ca3af;">Esperando a los demás jugadores…</p>

      <template x-if="legal">
        <div style="display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: center; justify-content: flex-end;">
          <x-betting-bar
            x-show="legal.raise"
            label="Apuesta adicional"
            id="raise-to-slider"
            min="0"
            max="100"
            step="1"
            value="0"
            unit=""
            model="raiseAmount"
            valueExpression="formatBB(raiseAmount)"
            class="w-full"
            style="max-width: 280px;"
            x-bind:min="legal.raise?.min"
            x-bind:max="legal.raise?.max"
            x-bind:step="1"
            x-bind:disabled="actionInProgress"
            x-effect="clampRaise()"
            @change="clampRaise()"
          />

          <button type="button" x-show="legal.fold" @click="playerFold()" :disabled="actionInProgress" style="{{ $btn }} background: #7f1d1d; border-color: #b91c1c;" :style="{ opacity: actionInProgress ? 0.45 : 1 }">Fold</button>

          <button type="button" x-show="legal.check" @click="playerCheck()" :disabled="actionInProgress" style="{{ $btn }} background: #1d4ed8; border-color: #2563eb;" :style="{ opacity: actionInProgress ? 0.45 : 1 }">Check</button>

          <button type="button" x-show="legal.call !== null" @click="playerCall()" :disabled="actionInProgress" style="{{ $btn }} background: #047857; border-color: #059669;" :style="{ opacity: actionInProgress ? 0.45 : 1 }">
            <span x-text="'Call ' + formatBB(legal.call)"></span>
          </button>

          <div x-show="legal.raise" style="display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center;">
            <button type="button" @click="setRaiseSize(0)" style="{{ $subBtn }}">Mínimo</button>
            <button type="button" @click="setRaiseSize(0.5)" style="{{ $subBtn }}">½ bote</button>
            <button type="button" @click="setRaiseSize(1)" style="{{ $subBtn }}">Bote</button>
            <button type="button" @click="playerRaise()" :disabled="actionInProgress" style="{{ $btn }} background: #6d28d9; border-color: #7c3aed;" :style="{ opacity: actionInProgress ? 0.45 : 1 }">
              <span x-text="(legal.is_bet ? 'Bet ' : 'Raise a ') + formatBB(raiseTarget())"></span>
            </button>
          </div>

          <button type="button" x-show="legal.allin !== null" @click="playerAllIn()" :disabled="actionInProgress" style="{{ $btn }} background: #b45309; border-color: #d97706;" :style="{ opacity: actionInProgress ? 0.45 : 1 }">
            <span x-text="'All-in ' + formatBB(legal.allin)"></span>
          </button>

          <p x-show="legal.raise" style="margin: 0; width: 100%; text-align: right; font-size: 0.72rem; color: #9ca3af;"
            x-text="'Aportar mín. ' + formatBB(legal.raise?.min) + ' · Máx. ' + formatBB(legal.raise?.max)"></p>
          <p x-show="!legal.raise && legal.call !== null" style="margin: 0; width: 100%; text-align: right; font-size: 0.72rem; color: #9ca3af;">No puedes subir: solo igualar, retirarte o ir all-in por lo que tienes.</p>
        </div>
      </template>
    </div>
    <div x-show="gameCompleted" style="flex: 1; display: flex; gap: 1rem; align-items: center; justify-content: flex-end;">
      <p style="margin: 0; font-size: 0.85rem; color: #d1d5db;" x-text="error || ''"></p>
      <p x-show="shouldAutoAdvance() && !error" style="margin: 0; font-size: 0.85rem; color: #9ca3af;">⏩ La siguiente mano comenzará automáticamente</p>
      <button x-show="shouldShowNextHandButton()" @click="nextHand()" :disabled="nextHandLoading" :style="{ opacity: nextHandLoading ? 0.7 : 1, cursor: nextHandLoading ? 'wait' : 'pointer' }" style="min-width: 190px; padding: 0.9rem 1.4rem; border: none; border-radius: 0.4rem; background: #059669; color: #fff; font-weight: 700; cursor: pointer;"><span x-text="nextHandLoading ? ('⏳ ' + (nextHandProgress || 'Cargando…')) : '▶ Siguiente mano'"></span></button>
    </div>
  </div>
</div>
