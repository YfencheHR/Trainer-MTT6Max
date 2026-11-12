<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['size' => 'normal', 'onIncrement' => '', 'onDecrement' => '']));

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

foreach (array_filter((['size' => 'normal', 'onIncrement' => '', 'onDecrement' => '']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
  $isSmall = $size === 'small';

  $buttonClass = $isSmall
    ? 'px-1 py-0.5 text-xs'
    : 'px-1.5 py-1 text-xs';

  $gapClass = $isSmall ? 'gap-0' : 'gap-0.5';
?>

<div class="flex <?php echo e($gapClass); ?>">
  <button
    type="button"
    @click="<?php echo e($onDecrement); ?>"
    style="background-color: #0f1419; border-color: #2e3a48; color: #9ca3af;"
    class="rounded border hover:text-white hover:border-slate-500 transition font-semibold <?php echo e($buttonClass); ?>"
    title="Restar"
  >
    −
  </button>

  <button
    type="button"
    @click="<?php echo e($onIncrement); ?>"
    style="background-color: #0f1419; border-color: #2e3a48; color: #9ca3af;"
    class="rounded border hover:text-white hover:border-slate-500 transition font-semibold <?php echo e($buttonClass); ?>"
    title="Sumar"
  >
    +
  </button>
</div>
<?php /**PATH /app/resources/views/components/increment-decrement-buttons.blade.php ENDPATH**/ ?>