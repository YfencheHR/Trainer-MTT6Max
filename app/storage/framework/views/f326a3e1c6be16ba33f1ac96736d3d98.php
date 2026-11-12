<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['id', 'title', 'message', 'primaryBtnText' => 'Confirmar', 'secondaryBtnText' => 'Cancelar', 'primaryBtnColor' => 'ef4444', 'onConfirm' => null]));

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

foreach (array_filter((['id', 'title', 'message', 'primaryBtnText' => 'Confirmar', 'secondaryBtnText' => 'Cancelar', 'primaryBtnColor' => 'ef4444', 'onConfirm' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div x-data="{ <?php echo e($id); ?>: false }">
  <!-- Contenido del slot (botones y contenido) -->
  <?php echo e($slot); ?>


  <!-- Modal Flotante -->
    <dialog
      x-show="<?php echo e($id); ?>"
      @click.self="<?php echo e($id); ?> = false"
      @keyup.escape.window="<?php echo e($id); ?> = false"
      x-transition:enter="transition ease-out duration-300"
      x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100"
      x-transition:leave="transition ease-in duration-200"
      x-transition:leave-start="opacity-100"
      x-transition:leave-end="opacity-0"
      class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50"
      style="display: none; z-index: 9999;"
      aria-modal="true"
      open>

    <div
        @click.stop
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="rounded-lg shadow-xl max-w-md w-full p-6 border"
        style="background-color: #1a212a; border-color: #2e3a48;">

      <h2 class="text-xl font-bold text-white mb-2"><?php echo e($title); ?></h2>
      <p class="text-sm text-gray-400 mb-6"><?php echo e($message); ?></p>

      <div class="flex justify-center gap-3">
        <button
            type="button"
            @click="<?php echo e($id); ?> = false"
            class="text-white px-4 py-2 rounded font-semibold transition hover:opacity-80"
            style="background-color: #6b7280;">
          <?php echo e($secondaryBtnText); ?>

        </button>
        <button
            type="button"
            @click="<?php echo e($onConfirm ?? ($id . ' = false')); ?>"
            class="text-white px-4 py-2 rounded font-semibold transition hover:opacity-80"
            style="background-color: #<?php echo e($primaryBtnColor); ?>;">
          <?php echo e($primaryBtnText); ?>

        </button>
      </div>
    </div>
  </dialog>
</div>
<?php /**PATH /app/resources/views/components/modal-popup.blade.php ENDPATH**/ ?>