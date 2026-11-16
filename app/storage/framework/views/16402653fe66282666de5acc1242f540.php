<?php $__env->startSection('content'); ?>
  <div style="max-width: 1440px; margin: 0 auto;" class="px-2 sm:px-3 py-6">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-4xl font-bold mb-2" style="color: #d1d5db;"><?php echo e(__('made-hands.title')); ?></h1>
      <p style="color: #a1a5b0;" class="text-lg"><?php echo e(__('made-hands.description')); ?></p>
    </div>

    <!-- Messages -->
    <?php if($message = session('success')): ?>
      <div class="mb-6 p-4 rounded" style="background-color: #1e3a1f; color: #10b981;">
        <?php echo e($message); ?>

      </div>
    <?php endif; ?>

    <!-- Made Hands Section -->
    <div class="mb-12" style="padding-bottom: 50px;">
      <h2 class="text-2xl font-bold mb-4" style="color: #d1d5db;"><?php echo e(__('made-hands.normal_hands')); ?></h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php $__empty_1 = true; $__currentLoopData = $madeHandsGrouped['normal']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $hand): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <?php if (isset($component)) { $__componentOriginalcec5ecbaf0f6aa763a3e7effa065b330 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcec5ecbaf0f6aa763a3e7effa065b330 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.hand-card','data' => ['hand' => $hand,'bgColor' => '#047857','hoverBorderColor' => 'emerald-500','nameColor' => '#ffffff','valueColor' => '#10b981','buttonBgColor' => '#10b981','isSpecial' => false]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('hand-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['hand' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($hand),'bgColor' => '#047857','hoverBorderColor' => 'emerald-500','nameColor' => '#ffffff','valueColor' => '#10b981','buttonBgColor' => '#10b981','isSpecial' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcec5ecbaf0f6aa763a3e7effa065b330)): ?>
<?php $attributes = $__attributesOriginalcec5ecbaf0f6aa763a3e7effa065b330; ?>
<?php unset($__attributesOriginalcec5ecbaf0f6aa763a3e7effa065b330); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcec5ecbaf0f6aa763a3e7effa065b330)): ?>
<?php $component = $__componentOriginalcec5ecbaf0f6aa763a3e7effa065b330; ?>
<?php unset($__componentOriginalcec5ecbaf0f6aa763a3e7effa065b330); ?>
<?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <div class="col-span-full text-center text-slate-400 py-12">
            <?php echo e(__('no_data')); ?>

          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Special Projects Section -->
    <div style="padding-bottom: 50px;">
      <h2 class="text-2xl font-bold mb-4" style="color: #d1d5db;"><?php echo e(__('made-hands.special_hands')); ?></h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php $__empty_1 = true; $__currentLoopData = $madeHandsGrouped['special']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $hand): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <?php if (isset($component)) { $__componentOriginalcec5ecbaf0f6aa763a3e7effa065b330 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcec5ecbaf0f6aa763a3e7effa065b330 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.hand-card','data' => ['hand' => $hand,'bgColor' => '#9333ea','hoverBorderColor' => 'purple-500','nameColor' => '#d8b4fe','valueColor' => '#f59e0b','buttonBgColor' => '#9333ea','isSpecial' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('hand-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['hand' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($hand),'bgColor' => '#9333ea','hoverBorderColor' => 'purple-500','nameColor' => '#d8b4fe','valueColor' => '#f59e0b','buttonBgColor' => '#9333ea','isSpecial' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcec5ecbaf0f6aa763a3e7effa065b330)): ?>
<?php $attributes = $__attributesOriginalcec5ecbaf0f6aa763a3e7effa065b330; ?>
<?php unset($__attributesOriginalcec5ecbaf0f6aa763a3e7effa065b330); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcec5ecbaf0f6aa763a3e7effa065b330)): ?>
<?php $component = $__componentOriginalcec5ecbaf0f6aa763a3e7effa065b330; ?>
<?php unset($__componentOriginalcec5ecbaf0f6aa763a3e7effa065b330); ?>
<?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <div class="col-span-full text-center text-slate-400 py-12">
            <?php echo e(__('no_data')); ?>

          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /app/resources/views/made-hands/index.blade.php ENDPATH**/ ?>