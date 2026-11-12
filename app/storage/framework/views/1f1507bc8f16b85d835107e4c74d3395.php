<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['label', 'stat', 'value', 'fieldName']));

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

foreach (array_filter((['label', 'stat', 'value', 'fieldName']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div x-data="{ showTooltip: false }" class="min-w-0">
  <div class="flex items-center gap-2 mb-2">
    <label for="<?php echo e($fieldName); ?>" style="color: #9ca3af;" class="block text-xs uppercase font-bold"><?php echo e($label); ?></label>
    <?php if(!empty(\App\Config\StatSegments::getSegments($stat))): ?>
      <div style="position: relative; display: inline-block;">
        <span style="color: #9ca3af; cursor: help; font-size: 14px;" @mouseenter="showTooltip = true" @mouseleave="showTooltip = false">?</span>
        <div x-show="showTooltip" style="position: absolute; left: 50%; transform: translateX(-50%); top: 100%; margin-top: 8px; width: 350px; background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 6px; padding: 12px; z-index: 50; white-space: normal; font-size: 11px;">
          <div style="display: flex; flex-direction: column; gap: 6px;">
            <?php $__currentLoopData = \App\Config\StatSegments::getSegments($stat); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $segment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #2e3a48;">
                <span style="color: #ffffff; font-weight: 600;"><?php echo e(round($segment['min'], 1)); ?>-<?php echo e(round($segment['max'], 1)); ?>%</span>
                <span style="color: <?php echo e($segment['color']); ?>; font-size: 10px;"><?php echo e($segment['label']); ?></span>
              </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <?php
    // Determinar el step, min y max según el tipo de estadística
    $step = 1;
    $min = 0;
    $max = 100;

    // AF (Aggression Factor) y VB Ratio: 0-10
    if (strpos($stat, 'af_') === 0 || $stat === 'af' || $stat === 'vb_ratio') {
      $step = 0.1;
      $max = 10;
    }
    // Todas las demás estadísticas: porcentajes 0-100
    else {
      $step = 1;
      $max = 100;
    }
  ?>

  <?php if (isset($component)) { $__componentOriginal2c52906b7739e7f8afbd5527ac7177b8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2c52906b7739e7f8afbd5527ac7177b8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.number-input','data' => ['name' => ''.e($fieldName).'','value' => ''.e($value).'','min' => ''.e($min).'','max' => ''.e($max).'','step' => ''.e($step).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('number-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => ''.e($fieldName).'','value' => ''.e($value).'','min' => ''.e($min).'','max' => ''.e($max).'','step' => ''.e($step).'']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2c52906b7739e7f8afbd5527ac7177b8)): ?>
<?php $attributes = $__attributesOriginal2c52906b7739e7f8afbd5527ac7177b8; ?>
<?php unset($__attributesOriginal2c52906b7739e7f8afbd5527ac7177b8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2c52906b7739e7f8afbd5527ac7177b8)): ?>
<?php $component = $__componentOriginal2c52906b7739e7f8afbd5527ac7177b8; ?>
<?php unset($__componentOriginal2c52906b7739e7f8afbd5527ac7177b8); ?>
<?php endif; ?>
</div>
<?php /**PATH /app/resources/views/components/bots/stat-input-with-tooltip.blade.php ENDPATH**/ ?>