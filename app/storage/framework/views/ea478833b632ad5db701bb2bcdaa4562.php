<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'value' => 0, 'min' => 0, 'max' => 100, 'step' => 1, 'label' => '']));

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

foreach (array_filter((['name', 'value' => 0, 'min' => 0, 'max' => 100, 'step' => 1, 'label' => '']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
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
?>

<div class="flex flex-col min-w-0">
  <label for="<?php echo e($name); ?>" class="mb-2"><?php echo e($label); ?></label>
  <div x-data="{
    value: <?php echo e($initialValue); ?>,
    min: <?php echo e($minValue); ?>,
    max: <?php echo e($maxValue); ?>,
    step: <?php echo e($stepValue); ?>,
    increment() {
      this.value = Math.min(this.max, parseFloat((this.value + this.step).toFixed(4)));
    },
    decrement() {
      this.value = Math.max(this.min, parseFloat((this.value - this.step).toFixed(4)));
    }
  }" class="flex items-center gap-1 sm:gap-2 min-w-0">
    <!-- Campo de entrada -->
    <input
        id="<?php echo e($name); ?>"
        type="text"
        x-model.number="value"
        x-on:input="value = isNaN(value) ? 0 : Math.min(max, Math.max(min, value))"
        x-on:keydown.up.prevent="increment()"
        x-on:keydown.down.prevent="decrement()"
        x-on:blur="value = Math.max(min, Math.min(max, parseFloat(Number(value || 0).toFixed(4))))"
        style="background-color: #0f1419; border-color: #2e3a48; color: #ffffff;"
        class="min-w-0 flex-1 px-2 sm:px-4 py-3 rounded border text-center font-bold hover:border-slate-500 transition"
        placeholder="0"
    >

    <!-- Botones a la derecha -->
    <div class="flex gap-1">
      <!-- Botón Decrementar -->
      <button
        type="button"
        @click="decrement()"
        style="background-color: #0f1419; border-color: #2e3a48; color: #9ca3af;"
        class="px-2 sm:px-3 py-3 rounded border hover:text-white hover:border-slate-500 transition text-lg font-semibold"
        title="Restar"
      >
        −
      </button>

      <!-- Botón Incrementar -->
      <button
        type="button"
        @click="increment()"
        style="background-color: #0f1419; border-color: #2e3a48; color: #9ca3af;"
        class="px-2 sm:px-3 py-3 rounded border hover:text-white hover:border-slate-500 transition text-lg font-semibold"
        title="Sumar"
      >
        +
      </button>
    </div>

    <!-- Input oculto para enviar el valor real -->
    <input
      type="hidden"
      name="<?php echo e($name); ?>"
      x-model.number="value"
    >
  </div>

  <!-- Información de rango -->
  <div style="color: #6b7280;" class="text-xs mt-2 text-left">
    Rango: <?php echo e(number_format($minValue, 2)); ?> - <?php echo e(number_format($maxValue, 2)); ?>

  </div>
</div>
<?php /**PATH /app/resources/views/components/number-input.blade.php ENDPATH**/ ?>