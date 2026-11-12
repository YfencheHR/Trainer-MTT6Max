<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'name' => '',
    'id' => '',
    'label' => '',
    'value' => '',
    'min' => '0',
    'max' => '100',
    'step' => '1',
    'required' => false,
    'suffix' => '',
    'class' => ''
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
    'name' => '',
    'id' => '',
    'label' => '',
    'value' => '',
    'min' => '0',
    'max' => '100',
    'step' => '1',
    'required' => false,
    'suffix' => '',
    'class' => ''
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div <?php echo e($attributes->merge(['class' => $class])); ?>>
  <label for="<?php echo e($id); ?>" style="color: #a1a5b0;" class="block text-xs uppercase font-semibold mb-2">
    <?php echo e($label); ?>

    <?php if($suffix): ?>
      <span style="color: #6b7280; font-size: 0.75rem;"><?php echo e($suffix); ?></span>
    <?php endif; ?>
  </label>

  <input type="number"
    id="<?php echo e($id); ?>"
    name="<?php echo e($name); ?>"
    value="<?php echo e($value); ?>"
    min="<?php echo e($min); ?>"
    max="<?php echo e($max); ?>"
    step="<?php echo e($step); ?>"
    style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;"
    class="w-full px-3 py-2 rounded border focus:outline-none focus:border-blue-500 text-center text-sm mb-2 <?php if($required): ?> required <?php endif; ?>"
    <?php if($required): ?> required <?php endif; ?>
  >

  <input type="range"
    id="<?php echo e($id); ?>Slider"
    min="<?php echo e($min); ?>"
    max="<?php echo e($max); ?>"
    step="<?php echo e($step); ?>"
    value="<?php echo e($value); ?>"
    style="accent-color: #047857;"
    class="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer"
  >
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const input = document.querySelector('#<?php echo e($id); ?>');
    const slider = document.querySelector('#<?php echo e($id); ?>Slider');

    if (input && slider) {
      input.addEventListener('input', function() {
        slider.value = this.value;
      });

      slider.addEventListener('input', function() {
        input.value = this.value;
      });
    }
  });
</script>
<?php /**PATH /app/resources/views/components/number-input-with-slider.blade.php ENDPATH**/ ?>