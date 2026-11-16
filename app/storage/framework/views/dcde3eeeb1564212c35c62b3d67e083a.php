<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'hand',
    'bgColor' => '#047857',
    'hoverBorderColor' => 'emerald-500',
    'nameColor' => '#ffffff',
    'valueColor' => '#10b981',
    'buttonBgColor' => '#10b981',
    'isSpecial' => false,
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
    'hand',
    'bgColor' => '#047857',
    'hoverBorderColor' => 'emerald-500',
    'nameColor' => '#ffffff',
    'valueColor' => '#10b981',
    'buttonBgColor' => '#10b981',
    'isSpecial' => false,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 hover:border-<?php echo e($hoverBorderColor); ?> transition flex flex-col">
  <!-- Header: Name and Value with Combos -->
  <div class="mb-4">
    <div class="flex items-start justify-between mb-2">
      <h3 style="color: <?php echo e($nameColor); ?>;" class="text-lg font-bold flex-1"><?php echo e($hand->name); ?></h3>
      <div class="flex gap-4 ml-2">
        <div class="text-right">
          <p style="color: #9ca3af;" class="text-xs uppercase"><?php echo e(__('made-hands.value')); ?></p>
          <p style="color: <?php echo e($valueColor); ?>;" class="text-sm font-bold"><?php echo e($hand->value); ?></p>
        </div>
        <div class="text-right">
          <p style="color: #9ca3af;" class="text-xs uppercase"><?php echo e(__('made-hands.combos')); ?></p>
          <p style="color: #fbbf24;" class="text-sm font-bold"><?php echo e($hand->combos); ?></p>
        </div>
      </div>
    </div>
    <?php if(!$isSpecial): ?>
      <?php
        $prob = (float)($hand->probability ?? 0);
      ?>
      <?php if($prob > 0.0001): ?>
        <div class="text-right">
          <p style="color: #a78bfa; font-size: 16px; font-weight: 700;"><?php echo e(number_format($prob, 4)); ?>%</p>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Visual Representation -->
  <div class="mb-4 flex flex-wrap gap-2 justify-center" style="min-height: <?php echo e($isSpecial ? '40px' : '60px'); ?>; display: flex; align-items: center;">
    <?php if (isset($component)) { $__componentOriginal3373e9492f270c0fd6dbf8085c364918 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3373e9492f270c0fd6dbf8085c364918 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card-visual','data' => ['visuals' => $hand->visuals,'bgColor' => $bgColor,'isSpecial' => $isSpecial]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card-visual'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['visuals' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($hand->visuals),'bgColor' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bgColor),'isSpecial' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($isSpecial)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3373e9492f270c0fd6dbf8085c364918)): ?>
<?php $attributes = $__attributesOriginal3373e9492f270c0fd6dbf8085c364918; ?>
<?php unset($__attributesOriginal3373e9492f270c0fd6dbf8085c364918); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3373e9492f270c0fd6dbf8085c364918)): ?>
<?php $component = $__componentOriginal3373e9492f270c0fd6dbf8085c364918; ?>
<?php unset($__componentOriginal3373e9492f270c0fd6dbf8085c364918); ?>
<?php endif; ?>
  </div>

  <!-- Action Button -->
  <div class="pt-4" style="border-top: 1px solid #2e3a48;">
    <a href="<?php echo e(route('made-hands.edit', $hand)); ?>" style="background-color: <?php echo e($buttonBgColor); ?>; color: white;" class="block w-full hover:opacity-80 text-center px-3 py-2 rounded text-sm font-semibold transition"><?php echo e(__('made-hands.view')); ?></a>
  </div>
</div>
<?php /**PATH /app/resources/views/components/hand-card.blade.php ENDPATH**/ ?>