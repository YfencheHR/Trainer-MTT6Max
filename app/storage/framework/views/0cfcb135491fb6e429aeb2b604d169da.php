<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'name' => '',
    'id' => '',
    'label' => '',
    'options' => [],
    'value' => '',
    'placeholder' => '',
    'required' => false,
    'disabled' => false,
    'bgColor' => '#0f1419',
    'borderColor' => '#2e3a48'
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
    'options' => [],
    'value' => '',
    'placeholder' => '',
    'required' => false,
    'disabled' => false,
    'bgColor' => '#0f1419',
    'borderColor' => '#2e3a48'
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div <?php echo e($attributes->merge(['class' => 'mb-4'])); ?>>
  <?php if($label): ?>
    <label for="<?php echo e($id); ?>" style="color: #a1a5b0;" class="block text-xs uppercase font-semibold mb-2">
      <?php echo e($label); ?>

    </label>
  <?php endif; ?>

  <select id="<?php echo e($id); ?>"
    name="<?php echo e($name); ?>"
    style="background-color: <?php echo e($bgColor); ?>; border-color: <?php echo e($borderColor); ?>; color: #ffffff;"
    class="w-full px-4 py-3 rounded border focus:outline-none focus:border-blue-500"
    <?php if($required): ?> required <?php endif; ?>
    <?php if($disabled): ?> disabled <?php endif; ?>
  >
    <?php if($placeholder): ?>
      <option value=""><?php echo e($placeholder); ?></option>
    <?php endif; ?>

    <?php $__empty_1 = true; $__currentLoopData = $options; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $optionValue => $optionLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <option value="<?php echo e($optionValue); ?>" <?php if($value == $optionValue): ?> selected <?php endif; ?>>
        <?php echo e($optionLabel); ?>

      </option>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <option value=""><?php echo e(__('common.no_options')); ?></option>
    <?php endif; ?>
  </select>

  <?php $__errorArgs = [$name];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
    <p style="color: #ef4444;" class="text-xs mt-1"><?php echo e($message); ?></p>
  <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
</div>
<?php /**PATH /app/resources/views/components/select.blade.php ENDPATH**/ ?>