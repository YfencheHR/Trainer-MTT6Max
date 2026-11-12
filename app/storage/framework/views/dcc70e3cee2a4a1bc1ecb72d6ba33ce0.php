<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['title', 'description', 'cancelUrl']));

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

foreach (array_filter((['title', 'description', 'cancelUrl']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<!-- Encabezado del Formulario -->
<div class="flex justify-between items-center mb-8">
  <div>
    <h1 class="text-4xl font-bold text-white mb-2"><?php echo e($title); ?></h1>
    <p style="color: #9ca3af;" class="text-lg"><?php echo e($description); ?></p>
  </div>
  <div class="flex gap-2">
    <a href="<?php echo e($cancelUrl); ?>" style="background-color: #6b7280;" class="hover:opacity-80 text-white px-6 py-2 rounded font-semibold transition">Cancelar</a>
  </div>
</div>
<?php /**PATH /app/resources/views/components/form-header.blade.php ENDPATH**/ ?>