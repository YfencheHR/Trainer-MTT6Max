<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['tournament' => null, 'playersStats' => []]));

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

foreach (array_filter((['tournament' => null, 'playersStats' => []]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>


<div x-data="{
    dynamicRanges: null,
    lastPlayerId: null,
    handId: window.location.pathname.split('/').find((_, i, arr) => arr[i-1] === 'hand' && !isNaN(arr[i])),
    async loadDynamicRanges() {
      const currentPlayerId = $store.gameState?.currentPlayerId;
      const handId = this.handId;

      if (!currentPlayerId || !handId || currentPlayerId === this.lastPlayerId) return;

      try {
        const resp = await fetch(`/mtt-simulator/hand/${handId}/ranges/${currentPlayerId}`);
        const data = await resp.json();
        if (data.success) {
          this.dynamicRanges = data.data;
          this.lastPlayerId = currentPlayerId;
        }
      } catch (e) {
        console.error('Error loading dynamic ranges:', e);
      }
    },
    init() {
      this.$watch('$store.gameState.currentPlayerId', () => this.loadDynamicRanges());
    }
  }"
  style="background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 0.75rem;">

  <p style="color: #d1d5db; font-weight: 600; margin: 0 0 0.75rem; font-size: 0.8rem; text-transform: uppercase;">
    🎯 Rivales en Acción - Rangos
  </p>

  
  <template x-if="dynamicRanges && Object.keys(dynamicRanges.ranges).length > 0">
    <div style="background: rgba(0,0,0,0.3); border-radius: 0.4rem; padding: 0.6rem; margin-bottom: 0.75rem;">
      <p style="margin: 0 0 0.4rem 0; font-size: 0.75rem; color: #9ca3af; text-transform: uppercase;">
        📊 Rango dinámico de <strong x-text="dynamicRanges.player_name"></strong>
      </p>

      
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(32px, 1fr)); gap: 0.3rem; font-size: 0.7rem;">
        <template x-for="(stats, hand) in (dynamicRanges.ranges || {})" :key="hand">
          <div style="
            padding: 0.3rem;
            text-align: center;
            border-radius: 0.2rem;
            background: rgba(96, 165, 250, 0.2);
            color: #60a5fa;
            font-weight: 600;
            border: 1px solid rgba(96, 165, 250, 0.4);
            cursor: help;
          "
          :title="hand + ' - Open: ' + Math.round(stats.open || 0) + '%, Call: ' + Math.round(stats.call || 0) + '%, Raise: ' + Math.round(stats.raise || 0) + '%'"
          x-show="(stats.open || 0) > 0 || (stats.call || 0) > 0 || (stats.raise || 0) > 0">
            <div x-text="hand"></div>
            <div style="font-size: 0.6rem; color: #9ca3af;">
              <span x-text="Math.max(stats.open || 0, stats.call || 0, stats.raise || 0).toFixed(0) + '%'"></span>
            </div>
          </div>
        </template>
      </div>
    </div>
  </template>

  
  <div style="max-height: 250px; overflow-y: auto;">
    <?php $__empty_1 = true; $__currentLoopData = $playersStats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $playerId => $stats): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div style="background: rgba(0,0,0,0.3); border-radius: 0.4rem; padding: 0.6rem; margin-bottom: 0.5rem; border-left: 3px solid
        <?php if($stats['is_bot']): ?> #ef4444 <?php else: ?> #10b981 <?php endif; ?>
      ;">

        
        <p style="margin: 0; font-size: 0.85rem; font-weight: 600; color: #d1d5db;">
          <?php echo e($stats['name']); ?>

          <span style="font-size: 0.7rem; color: #9ca3af;">
            <?php if($stats['is_bot']): ?> 🤖 <?php else: ?> 👤 <?php endif; ?>
          </span>
        </p>

        
        <?php
            $statCell = 'margin: 0; font-size: 0.7rem; color: #9ca3af; text-transform: uppercase;';
            $statVal = 'margin: 0.2rem 0 0 0; font-size: 0.9rem; font-weight: 600; color: #60a5fa;';
        ?>

        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.4rem; margin-top: 0.4rem;">
          <div>
            <p style="<?php echo e($statCell); ?>">VPIP</p>
            <p style="<?php echo e($statVal); ?>"><?php echo e($stats['vpip'] ?? '–'); ?>%</p>
          </div>
          <div>
            <p style="<?php echo e($statCell); ?>">PFR</p>
            <p style="<?php echo e($statVal); ?>"><?php echo e($stats['pfr'] ?? '–'); ?>%</p>
          </div>
          <div>
            <p style="<?php echo e($statCell); ?>">AF</p>
            <p style="<?php echo e($statVal); ?>"><?php echo e($stats['af'] ?? '–'); ?></p>
          </div>
          <div>
            <p style="<?php echo e($statCell); ?>">3-BET</p>
            <p style="<?php echo e($statVal); ?>"><?php echo e($stats['three_bet'] ?? '–'); ?>%</p>
          </div>
        </div>

        
        <div style="margin-top: 0.4rem; padding-top: 0.4rem; border-top: 1px solid rgba(255,255,255,0.1);">
          <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.4rem;">
            <div>
              <p style="<?php echo e($statCell); ?>">C-BET</p>
              <p style="<?php echo e($statVal); ?>"><?php echo e($stats['cbet'] ?? '–'); ?>%</p>
            </div>
            <div>
              <p style="<?php echo e($statCell); ?>">DONK</p>
              <p style="<?php echo e($statVal); ?>"><?php echo e($stats['donk'] ?? '–'); ?>%</p>
            </div>
          </div>
        </div>

        
        <?php if(isset($stats['bb100']) && $stats['bb100'] !== null): ?>
          <div style="margin-top: 0.4rem; padding: 0.4rem; background: rgba(255,255,255,0.05); border-radius: 0.3rem;">
            <p style="<?php echo e($statCell); ?>">BB/100</p>
            <p style="margin: 0.2rem 0 0 0; font-size: 0.9rem; font-weight: 600; color:
              <?php if($stats['bb100'] > 0): ?> #10b981 <?php elseif($stats['bb100'] < 0): ?> #ef4444 <?php else: ?> #9ca3af <?php endif; ?>
            ;">
              <?php if($stats['bb100'] > 0): ?> + <?php endif; ?> <?php echo e($stats['bb100']); ?>

            </p>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <p style="margin: 0; font-size: 0.8rem; color: #9ca3af; text-align: center; padding: 1rem;">
        Esperando acción...
      </p>
    <?php endif; ?>
  </div>

  
  <p style="margin: 1rem 0 0 0; font-size: 0.7rem; color: #6b7280; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 0.5rem;">
    <strong>Tip:</strong> Usa estos stats para entender las decisiones del bot. Rivales con VPIP alto juegan más manos; AF alto = agresivos.
  </p>
</div>
<?php /**PATH /app/resources/views/components/active-players-info.blade.php ENDPATH**/ ?>