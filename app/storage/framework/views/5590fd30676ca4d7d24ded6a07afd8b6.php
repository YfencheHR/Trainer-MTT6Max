<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'label' => 'Apuesta',
    'id' => 'bettingSlider',
    'min' => 0,
    'max' => 100,
    'step' => 0.1,
    'value' => 10,
    'unit' => 'bb',
    'model' => null,
    'valueExpression' => null,
    'class' => '',
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
    'label' => 'Apuesta',
    'id' => 'bettingSlider',
    'min' => 0,
    'max' => 100,
    'step' => 0.1,
    'value' => 10,
    'unit' => 'bb',
    'model' => null,
    'valueExpression' => null,
    'class' => '',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div <?php echo e($attributes->only(['x-show', 'style'])); ?> class="<?php echo e($class); ?>">
  <label for="<?php echo e($id); ?>" style="color: #a1a5b0;" class="block text-xs uppercase font-semibold mb-2">
    <?php echo e($label); ?>

    <span style="color: #6b7280; font-size: 0.75rem;"><?php echo e($unit); ?></span>
  </label>

  <div style="display: flex; gap: 0.75rem; align-items: center;">
    <input type="range"
      id="<?php echo e($id); ?>"
      min="<?php echo e($min); ?>"
      max="<?php echo e($max); ?>"
      step="<?php echo e($step); ?>"
      value="<?php echo e($value); ?>"
      style="accent-color: #047857; flex: 1;"
      class="h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer"
      <?php if($model): ?> x-model.number="<?php echo e($model); ?>" <?php endif; ?>
      <?php echo e($attributes->except(['x-show', 'style', 'class'])); ?>

    >
    <span id="<?php echo e($id); ?>Value" style="color: #10b981; font-weight: bold; min-width: 70px; text-align: right; font-size: 1rem;" <?php if($valueExpression): ?> x-text="<?php echo e($valueExpression); ?>" <?php endif; ?>>
      <?php echo e($value); ?> <?php echo e($unit); ?>

    </span>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const slider = document.getElementById(<?php echo json_encode($id, 15, 512) ?>);
  const valueDisplay = document.getElementById(<?php echo json_encode($id . 'Value', 15, 512) ?>);

  if (slider && valueDisplay && !slider.hasAttribute('x-model') && !slider.hasAttribute('x-model.number')) {
    slider.addEventListener('input', function() {
      valueDisplay.textContent = this.value + ' <?php echo e($unit); ?>';
    });
  }
});
</script>
<?php /**PATH /app/resources/views/components/betting-bar.blade.php ENDPATH**/ ?>