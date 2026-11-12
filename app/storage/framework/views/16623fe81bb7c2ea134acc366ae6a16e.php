<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['bot' => null]));

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

foreach (array_filter((['bot' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
  $streets = \App\Config\BotStreets::STREETS;
  $labels = \App\Config\BotStreets::LABELS;
  // Todas las estadísticas viven en la fila general del bot; se agrupan por calle solo para editarlas
  $general = $bot ? $bot->generalStats() : null;
?>

<!-- Estadísticas por calle -->
<div class="mb-2 flex flex-wrap items-end justify-between gap-2">
  <h2 class="text-2xl font-bold text-white">Estadísticas por calle</h2>
  <?php if($general && $general->sample_size): ?>
    <p style="color: #9ca3af;" class="text-sm">Datos reales de <?php echo e(number_format($general->sample_size, 0, ',', '.')); ?> manos. Si los cambias a mano, los valores se tratan como ajuste manual.</p>
  <?php endif; ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
  <?php $__currentLoopData = $streets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $street): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6">
      <h3 class="text-xl font-bold text-white mb-4"><?php echo e($street['nombre']); ?></h3>

      <p style="color: #10b981;" class="text-xs uppercase font-bold mb-3"><?php echo e(__('bots.rigurosas')); ?></p>
      <div class="grid grid-cols-3 gap-4 mb-6">
        <?php $__currentLoopData = $street['stats']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php if (isset($component)) { $__componentOriginal9b7461aa5faf7e6d76c946a225b4a610 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.bots.stat-input-with-tooltip','data' => ['label' => $labels[$stat] ?? $stat,'stat' => $stat,'value' => $general?->{$stat} ?? '','fieldName' => 'stats[1][' . $stat . ']']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('bots.stat-input-with-tooltip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($labels[$stat] ?? $stat),'stat' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($stat),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($general?->{$stat} ?? ''),'fieldName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('stats[1][' . $stat . ']')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610)): ?>
<?php $attributes = $__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610; ?>
<?php unset($__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9b7461aa5faf7e6d76c946a225b4a610)): ?>
<?php $component = $__componentOriginal9b7461aa5faf7e6d76c946a225b4a610; ?>
<?php unset($__componentOriginal9b7461aa5faf7e6d76c946a225b4a610); ?>
<?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>

      <p style="color: #f59e0b;" class="text-xs uppercase font-bold mb-3"><?php echo e(__('bots.adaptativas')); ?></p>
      <div class="grid grid-cols-3 gap-4">
        <?php $__currentLoopData = $street['adaptativas']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php if (isset($component)) { $__componentOriginal9b7461aa5faf7e6d76c946a225b4a610 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.bots.stat-input-with-tooltip','data' => ['label' => $labels[$stat] ?? $stat,'stat' => $stat,'value' => $general?->{$stat} ?? '','fieldName' => 'stats[1][' . $stat . ']']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('bots.stat-input-with-tooltip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($labels[$stat] ?? $stat),'stat' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($stat),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($general?->{$stat} ?? ''),'fieldName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('stats[1][' . $stat . ']')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610)): ?>
<?php $attributes = $__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610; ?>
<?php unset($__attributesOriginal9b7461aa5faf7e6d76c946a225b4a610); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9b7461aa5faf7e6d76c946a225b4a610)): ?>
<?php $component = $__componentOriginal9b7461aa5faf7e6d76c946a225b4a610; ?>
<?php unset($__componentOriginal9b7461aa5faf7e6d76c946a225b4a610); ?>
<?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php /**PATH /app/resources/views/components/bots/bot-levels-form.blade.php ENDPATH**/ ?>