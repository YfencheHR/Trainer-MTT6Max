<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'value' => 0, 'min' => 0, 'max' => 100, 'step' => 1, 'size' => 'normal']));

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

foreach (array_filter((['name', 'value' => 0, 'min' => 0, 'max' => 100, 'step' => 1, 'size' => 'normal']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
  $initialValue = (float)($value ?? 0);
  $stepValue = (float)$step;
  $minValue = (float)$min;
  $maxValue = (float)$max;

  $isSmall = $size === 'small';
  $gapClass = $isSmall ? 'gap-0.5' : 'gap-1';
  $inputClass = $isSmall ? 'px-1 py-0.5 text-xs' : 'px-2 py-1 text-sm';
?>

<div x-data="{
    value: <?php echo e($initialValue); ?>,
    min: <?php echo e($minValue); ?>,
    max: <?php echo e($maxValue); ?>,
    step: <?php echo e($stepValue); ?>,
    increment() {
      this.value = Math.min(this.max, parseFloat((this.value + this.step).toFixed(4)));
      this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
    },
    decrement() {
      this.value = Math.max(this.min, parseFloat((this.value - this.step).toFixed(4)));
      this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }" class="flex items-center <?php echo e($gapClass); ?>" style="min-width: 0;">
    <!-- Input -->
    <label for="hand-cell-input-<?php echo e($name); ?>" class="sr-only">Hand value</label>
    <input
      id="hand-cell-input-<?php echo e($name); ?>"
      x-ref="input"
      type="text"
      x-model.number="value"
      @input="value = isNaN(value) ? 0 : Math.min(max, Math.max(min, value))"
      @keydown.arrow-up.prevent="increment()"
      @keydown.arrow-down.prevent="decrement()"
      @blur="value = parseFloat(value.toFixed(4)); value = Math.max(min, Math.min(max, value)); $refs.input.dispatchEvent(new Event('change', { bubbles: true }));"
      style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff; width: 50px; flex: 0 0 auto;"
      class="rounded border text-center font-bold hover:border-slate-500 transition <?php echo e($inputClass); ?>"
      placeholder="0"
    >

    <!-- Botones a la derecha -->
    <?php if (isset($component)) { $__componentOriginalc6b244a87eaa905dd47fd1d0ecd9c023 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc6b244a87eaa905dd47fd1d0ecd9c023 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.increment-decrement-buttons','data' => ['size' => ''.e($size).'','onIncrement' => 'increment()','onDecrement' => 'decrement()']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('increment-decrement-buttons'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['size' => ''.e($size).'','onIncrement' => 'increment()','onDecrement' => 'decrement()']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc6b244a87eaa905dd47fd1d0ecd9c023)): ?>
<?php $attributes = $__attributesOriginalc6b244a87eaa905dd47fd1d0ecd9c023; ?>
<?php unset($__attributesOriginalc6b244a87eaa905dd47fd1d0ecd9c023); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc6b244a87eaa905dd47fd1d0ecd9c023)): ?>
<?php $component = $__componentOriginalc6b244a87eaa905dd47fd1d0ecd9c023; ?>
<?php unset($__componentOriginalc6b244a87eaa905dd47fd1d0ecd9c023); ?>
<?php endif; ?>

    <!-- Input oculto para enviar el valor real -->
    <input
      type="hidden"
      name="<?php echo e($name); ?>"
      x-model.number="value"
    >
  </div>
<?php /**PATH /app/resources/views/components/hand-cell-input.blade.php ENDPATH**/ ?>