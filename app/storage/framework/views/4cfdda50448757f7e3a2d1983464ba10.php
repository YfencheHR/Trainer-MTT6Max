<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['hand' => null, 'currentUser' => null, 'noHudData' => false]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['hand' => null, 'currentUser' => null, 'noHudData' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
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
?>

<div x-data='gameTable(<?php echo e($hand->id ?? 0); ?>, <?php echo json_encode(auth()->id(), 15, 512) ?>, <?php echo json_encode($tableData, 15, 512) ?>)' @switch-game-table.window="switchTable($event.detail)" style="color: #e5e7eb;">

  
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

  
  <div style="padding: 5.5rem clamp(1.5rem, 5vw, 5rem) 5.5rem;">
    <div style="position: relative; width: 100%; aspect-ratio: 16 / 9; min-height: 470px; background: radial-gradient(ellipse at center, #0d6b45 0%, #0a3d2a 100%); border: 10px solid #2a5f4f; border-radius: 50%; box-shadow: 0 0 50px rgba(10, 20, 15, 0.8), inset 0 0 60px rgba(0, 0, 0, 0.5);">

      
      <div style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.75rem; z-index: 1;">
        <div style="display: flex; gap: 0.5rem;">
          <?php for($i = 0; $i < 5; $i++): ?>
            <div style="position: relative; flex: 0 1 52px; width: 52px; aspect-ratio: 52 / 73; border-radius: 0.4rem; overflow: hidden;"
              :style="board.length > <?php echo e($i); ?>

                ? { background: '#fff', color: parseCard(board[<?php echo e($i); ?>]).color, boxShadow: '0 4px 10px rgba(0,0,0,0.5)', border: '1px solid #d1d5db' }
                : 'border: 2px dashed rgba(16,185,129,0.45); background: rgba(0,0,0,0.25);'">
              <template x-if="board.length > <?php echo e($i); ?>">
                <div style="position: absolute; inset: 0;">
                  <span style="position: absolute; top: 3px; left: 4px; font-size: 0.85rem; line-height: 1;" x-text="parseCard(board[<?php echo e($i); ?>]).suit"></span>
                  <span style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 1.65rem; font-weight: 800; line-height: 1;" x-text="parseCard(board[<?php echo e($i); ?>]).value"></span>
                  <span style="position: absolute; bottom: 3px; right: 4px; font-size: 0.85rem; line-height: 1;" x-text="parseCard(board[<?php echo e($i); ?>]).suit"></span>
                </div>
              </template>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      
      <?php if($tableData && $numPlayers > 0): ?>
        <?php $__currentLoopData = $seatSlots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php if (isset($component)) { $__componentOriginal979731ce15c9529e33c610eaaf300278 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal979731ce15c9529e33c610eaaf300278 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.bet-chip','data' => ['seat' => $slot]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('bet-chip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['seat' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($slot)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal979731ce15c9529e33c610eaaf300278)): ?>
<?php $attributes = $__attributesOriginal979731ce15c9529e33c610eaaf300278; ?>
<?php unset($__attributesOriginal979731ce15c9529e33c610eaaf300278); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal979731ce15c9529e33c610eaaf300278)): ?>
<?php $component = $__componentOriginal979731ce15c9529e33c610eaaf300278; ?>
<?php unset($__componentOriginal979731ce15c9529e33c610eaaf300278); ?>
<?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      <?php endif; ?>

      
      <?php if($tableData && $numPlayers > 0): ?>
        <?php $__currentLoopData = $seatSlots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php $pl = "getSafePlayer({$slot})"; ?>
          <div x-show="players.length > <?php echo e($slot); ?>" x-cloak :style="getPositionStyle(<?php echo e($slot); ?>)">
            
            <div x-show="lastAction && lastAction.player_id === getSafePlayer(<?php echo e($slot); ?>).id && !gameCompleted"
              style="position: absolute; top: -1.35rem; left: 50%; transform: translateX(-50%); z-index: 4; white-space: nowrap; background: #fbbf24; color: #1f2937; font-size: 0.68rem; font-weight: 800; padding: 0.1rem 0.55rem; border-radius: 999px; box-shadow: 0 2px 6px rgba(0,0,0,0.5);"
              x-text="lastAction ? lastAction.text.replace(/^hizo /, '').toUpperCase() : ''"></div>
            <div style="background: linear-gradient(180deg, #1c2430, #151b24); border-radius: 0.6rem; padding: 0.45rem 0.5rem 0.4rem; transition: opacity .2s, box-shadow .2s;"
              :style="{
                opacity: <?php echo e($pl); ?>.is_folded ? 0.4 : 1,
                border: <?php echo e($pl); ?>.is_turn ? '2px solid #fbbf24' : '1px solid #2e3a48',
                boxShadow: <?php echo e($pl); ?>.is_turn ? '0 0 14px rgba(251,191,36,0.6)' : '0 3px 10px rgba(0,0,0,0.55)'
              }">

              
              <div style="display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.35rem;">
                <span x-text="<?php echo e($pl); ?>.position" style="background: #2b3441; color: #cbd5e1; font-size: 0.6rem; font-weight: 700; padding: 0.05rem 0.3rem; border-radius: 0.3rem;"></span>
                <span style="width: 1.15rem; height: 1.15rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem; flex-shrink: 0; border: 1px solid #3b82f6;"
                  :style="{ background: <?php echo e($pl); ?>.is_current ? '#1d4ed8' : '#1e3a5f' }" x-text="<?php echo e($pl); ?>.is_bot ? '🤖' : '👤'"></span>
                <span style="font-size: 0.78rem; font-weight: 700; color: #f3f4f6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="<?php echo e($pl); ?>.name"></span>
              </div>

              
              <div x-show="<?php echo e($pl); ?>.hud" style="margin-bottom: 0.4rem;" :style="{ opacity: <?php echo e($pl); ?>.hud?.source === 'observed' ? 1 : 0.7 }" :title="<?php echo e($pl); ?>.hud?.source === 'observed' ? ('Observado en ' + <?php echo e($pl); ?>.hud.hands + ' manos') : 'Perfil del bot (aún sin muestra suficiente)'"><div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.25rem;">
                <div style="background: rgba(0,0,0,0.3); border-radius: 0.3rem; text-align: center; padding: 0.1rem 0;">
                  <div style="font-size: 0.85rem; font-weight: 800; line-height: 1.1;" :style="{ color: <?php echo e($pl); ?>.hud?.vpip_color || '#f97316' }" x-text="<?php echo e($pl); ?>.hud?.vpip ?? '–'"></div>
                  <div style="font-size: 0.5rem; color: #9ca3af;">VPIP</div>
                </div>
                <div style="background: rgba(0,0,0,0.3); border-radius: 0.3rem; text-align: center; padding: 0.1rem 0;">
                  <div style="font-size: 0.85rem; font-weight: 800; line-height: 1.1;" :style="{ color: <?php echo e($pl); ?>.hud?.pfr_color || '#60a5fa' }" x-text="<?php echo e($pl); ?>.hud?.pfr ?? '–'"></div>
                  <div style="font-size: 0.5rem; color: #9ca3af;">PFR</div>
                </div>
                <div style="background: rgba(0,0,0,0.3); border-radius: 0.3rem; text-align: center; padding: 0.1rem 0;">
                  <div style="font-size: 0.85rem; font-weight: 800; line-height: 1.1; color: #e5e7eb;" x-text="<?php echo e($pl); ?>.hud?.three_bet ?? '–'"></div>
                  <div style="font-size: 0.5rem; color: #9ca3af;">3B</div>
                </div>
              </div></div>

              
              <div style="display: flex; gap: 0.3rem; justify-content: center; margin-bottom: 0.35rem;">
                <?php for($c = 0; $c < 2; $c++): ?>
                  
                  <div class="carta-reverso" style="position: relative; flex: 1 1 0; max-width: 52px; aspect-ratio: 52 / 73; border-radius: 0.4rem; overflow: hidden;"
                    :style="<?php echo e($pl); ?>.hole_cards?.[<?php echo e($c); ?>] ? { background: '#fff', border: '1px solid #d1d5db', color: parseCard(<?php echo e($pl); ?>.hole_cards[<?php echo e($c); ?>]).color } : {}">
                    <template x-if="<?php echo e($pl); ?>.hole_cards?.[<?php echo e($c); ?>]">
                      <div style="position: absolute; inset: 0;">
                        <span style="position: absolute; top: 3px; left: 4px; font-size: 0.85rem; line-height: 1;" x-text="parseCard(<?php echo e($pl); ?>.hole_cards[<?php echo e($c); ?>]).suit"></span>
                        <span style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 1.65rem; font-weight: 800; line-height: 1;" x-text="parseCard(<?php echo e($pl); ?>.hole_cards[<?php echo e($c); ?>]).value"></span>
                        <span style="position: absolute; bottom: 3px; right: 4px; font-size: 0.85rem; line-height: 1;" x-text="parseCard(<?php echo e($pl); ?>.hole_cards[<?php echo e($c); ?>]).suit"></span>
                      </div>
                    </template>
                  </div>
                <?php endfor; ?>
              </div>
              
              <div style="text-align: center; line-height: 1.2;">
                <div style="font-size: 0.9rem; font-weight: 700; color: #f3f4f6;" x-text="<?php echo e($pl); ?>.is_all_in ? 'ALL-IN' : formatBB(<?php echo e($pl); ?>.stack)" :style="{ color: <?php echo e($pl); ?>.is_all_in ? '#ef4444' : '#f3f4f6' }"></div>
                <div style="font-size: 0.68rem; font-weight: 700; color: #f59e0b;" x-text="<?php echo e($pl); ?>.is_folded ? 'FOLD' : <?php echo e($pl); ?>.position"></div>
              </div>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      <?php endif; ?>
    </div>
  </div>

  
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
      <?php
          $btn = 'min-height: 42px; padding: 0.65rem 1rem; border: 1px solid transparent; border-radius: 0.55rem; color: #fff; font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: background-color .15s, border-color .15s, opacity .15s;';
          $subBtn = 'min-height: 36px; padding: 0.4rem 0.7rem; border: 1px solid #3b4654; border-radius: 0.45rem; background: #202a36; color: #d1d5db; font-size: 0.75rem; font-weight: 600; cursor: pointer;';
      ?>

      <p x-show="!legal" style="margin: 0; font-size: 0.85rem; color: #9ca3af;">Esperando a los demás jugadores…</p>

      <template x-if="legal">
        <div style="display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: center; justify-content: flex-end;">
          <?php if (isset($component)) { $__componentOriginal7ddc98ed96fb9f87e1af5fb6ed55591c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7ddc98ed96fb9f87e1af5fb6ed55591c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.betting-bar','data' => ['xShow' => 'legal.raise','label' => 'Apuesta adicional','id' => 'raise-to-slider','min' => '0','max' => '100','step' => '1','value' => '0','unit' => '','model' => 'raiseAmount','valueExpression' => 'formatBB(raiseAmount)','class' => 'w-full','style' => 'max-width: 280px;','xBind:min' => 'legal.raise?.min','xBind:max' => 'legal.raise?.max','xBind:step' => '1','xBind:disabled' => 'actionInProgress','xEffect' => 'clampRaise()','@change' => 'clampRaise()']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('betting-bar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['x-show' => 'legal.raise','label' => 'Apuesta adicional','id' => 'raise-to-slider','min' => '0','max' => '100','step' => '1','value' => '0','unit' => '','model' => 'raiseAmount','valueExpression' => 'formatBB(raiseAmount)','class' => 'w-full','style' => 'max-width: 280px;','x-bind:min' => 'legal.raise?.min','x-bind:max' => 'legal.raise?.max','x-bind:step' => '1','x-bind:disabled' => 'actionInProgress','x-effect' => 'clampRaise()','@change' => 'clampRaise()']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7ddc98ed96fb9f87e1af5fb6ed55591c)): ?>
<?php $attributes = $__attributesOriginal7ddc98ed96fb9f87e1af5fb6ed55591c; ?>
<?php unset($__attributesOriginal7ddc98ed96fb9f87e1af5fb6ed55591c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7ddc98ed96fb9f87e1af5fb6ed55591c)): ?>
<?php $component = $__componentOriginal7ddc98ed96fb9f87e1af5fb6ed55591c; ?>
<?php unset($__componentOriginal7ddc98ed96fb9f87e1af5fb6ed55591c); ?>
<?php endif; ?>

          <button type="button" x-show="legal.fold" @click="playerFold()" :disabled="actionInProgress" style="<?php echo e($btn); ?> background: #7f1d1d; border-color: #b91c1c;" :style="{ opacity: actionInProgress ? 0.45 : 1 }">Fold</button>

          <button type="button" x-show="legal.check" @click="playerCheck()" :disabled="actionInProgress" style="<?php echo e($btn); ?> background: #1d4ed8; border-color: #2563eb;" :style="{ opacity: actionInProgress ? 0.45 : 1 }">Check</button>

          <button type="button" x-show="legal.call !== null" @click="playerCall()" :disabled="actionInProgress" style="<?php echo e($btn); ?> background: #047857; border-color: #059669;" :style="{ opacity: actionInProgress ? 0.45 : 1 }">
            <span x-text="'Call ' + formatBB(legal.call)"></span>
          </button>

          <div x-show="legal.raise" style="display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center;">
            <button type="button" @click="setRaiseSize(0)" style="<?php echo e($subBtn); ?>">Mínimo</button>
            <button type="button" @click="setRaiseSize(0.5)" style="<?php echo e($subBtn); ?>">½ bote</button>
            <button type="button" @click="setRaiseSize(1)" style="<?php echo e($subBtn); ?>">Bote</button>
            <button type="button" @click="playerRaise()" :disabled="actionInProgress" style="<?php echo e($btn); ?> background: #6d28d9; border-color: #7c3aed;" :style="{ opacity: actionInProgress ? 0.45 : 1 }">
              <span x-text="(legal.is_bet ? 'Bet ' : 'Raise a ') + formatBB(raiseTarget())"></span>
            </button>
          </div>

          <button type="button" x-show="legal.allin !== null" @click="playerAllIn()" :disabled="actionInProgress" style="<?php echo e($btn); ?> background: #b45309; border-color: #d97706;" :style="{ opacity: actionInProgress ? 0.45 : 1 }">
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
<?php /**PATH /app/resources/views/components/game-table.blade.php ENDPATH**/ ?>