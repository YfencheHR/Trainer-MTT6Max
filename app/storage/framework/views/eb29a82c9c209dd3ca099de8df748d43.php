<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['submitText', 'submitColor', 'cancelUrl']));

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

foreach (array_filter((['submitText', 'submitColor', 'cancelUrl']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<!-- Botones de Acción -->
<div class="flex gap-4 pt-8" style="border-top: 1px solid #2e3a48;">
  <button type="submit" style="background-color: <?php echo e($submitColor); ?>;" class="hover:opacity-80 text-white px-6 py-3 rounded font-semibold transition">
    <?php echo e($submitText); ?>

  </button>
  <a href="<?php echo e($cancelUrl); ?>" style="background-color: #6b7280;" class="hover:opacity-80 text-white px-6 py-3 rounded font-semibold transition">Cancelar</a>
</div>
<?php /**PATH /app/resources/views/components/form-actions.blade.php ENDPATH**/ ?>