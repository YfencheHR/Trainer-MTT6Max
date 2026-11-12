<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'stats' => [],
    'no_hud_data' => false,
    'colors' => [
        'vpip' => '#10b981',
        'pfr' => '#06b6d4',
        'threeBet' => '#f59e0b',
        'af' => '#ec4899'
    ]
]));

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

foreach (array_filter(([
    'stats' => [],
    'no_hud_data' => false,
    'colors' => [
        'vpip' => '#10b981',
        'pfr' => '#06b6d4',
        'threeBet' => '#f59e0b',
        'af' => '#ec4899'
    ]
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php if($no_hud_data): ?>
  <div style="text-align: center; padding: 0.5rem; background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 0.25rem;">
    <p style="color: #f59e0b; font-size: 10px; margin: 0; font-weight: 600;">SIN DATOS</p>
    <p style="color: #9ca3af; font-size: 8px; margin: 0; margin-top: 2px;">Tourney Stats</p>
  </div>
<?php else: ?>
  <div style="display: flex; gap: 0.5rem; align-items: flex-start;">
  <!-- VPIP -->
  <div style="text-align: center;">
    <p style="color: #9ca3af; font-size: 9px; margin: 0; line-height: 1;">VPIP</p>
    <p style="color: <?php echo e($colors['vpip']); ?>; font-weight: 600; font-size: 11px; margin: 0; line-height: 1.2;">
      <?php echo e($stats['vpip'] ?? '-'); ?>%
    </p>
  </div>

  <!-- PFR -->
  <div style="text-align: center;">
    <p style="color: #9ca3af; font-size: 9px; margin: 0; line-height: 1;">PFR</p>
    <p style="color: <?php echo e($colors['pfr']); ?>; font-weight: 600; font-size: 11px; margin: 0; line-height: 1.2;">
      <?php echo e($stats['pfr'] ?? '-'); ?>%
    </p>
  </div>

  <!-- 3-BET -->
  <div style="text-align: center;">
    <p style="color: #9ca3af; font-size: 9px; margin: 0; line-height: 1;">3-BET</p>
    <p style="color: <?php echo e($colors['threeBet']); ?>; font-weight: 600; font-size: 11px; margin: 0; line-height: 1.2;">
      <?php echo e($stats['three_bet'] ?? '-'); ?>%
    </p>
  </div>

  <!-- AF -->
  <div style="text-align: center;">
    <p style="color: #9ca3af; font-size: 9px; margin: 0; line-height: 1;">AF</p>
    <p style="color: <?php echo e($colors['af']); ?>; font-weight: 600; font-size: 11px; margin: 0; line-height: 1.2;">
      <?php echo e(number_format($stats['af'] ?? 0, 1)); ?>

    </p>
  </div>
  </div>
<?php endif; ?>
<?php /**PATH /app/resources/views/components/bot-stats.blade.php ENDPATH**/ ?>