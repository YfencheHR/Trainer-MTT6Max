<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['seat' => 0, 'pos' => '']));

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

foreach (array_filter((['seat' => 0, 'pos' => '']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>


<div class="apuesta" style="display: none;" x-cloak
  x-show="players.length > <?php echo e($seat); ?> && getSafePlayer(<?php echo e($seat); ?>).show_bet && Number(getSafePlayer(<?php echo e($seat); ?>).bet) > 0 && formatBetBB(getSafePlayer(<?php echo e($seat); ?>).bet) !== '0bb' && !gameCompleted"
  x-effect="syncBetChip($el, <?php echo e($seat); ?>, getSafePlayer(<?php echo e($seat); ?>))"></div>
<?php /**PATH /app/resources/views/components/bet-chip.blade.php ENDPATH**/ ?>