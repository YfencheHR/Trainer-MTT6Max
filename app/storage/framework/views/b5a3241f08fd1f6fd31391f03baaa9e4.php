<?php $__env->startSection('content'); ?>
  <main style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
    <!-- Encabezado -->
    <?php if (isset($component)) { $__componentOriginalbb2a9f74a67315de3fb30cbbfabd15a9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbb2a9f74a67315de3fb30cbbfabd15a9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-header','data' => ['title' => __('bots.edit') . ' ' . $bot->nombre,'description' => __('bots.description_edit'),'cancelUrl' => route('bots.show', $bot)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('bots.edit') . ' ' . $bot->nombre),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('bots.description_edit')),'cancelUrl' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('bots.show', $bot))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbb2a9f74a67315de3fb30cbbfabd15a9)): ?>
<?php $attributes = $__attributesOriginalbb2a9f74a67315de3fb30cbbfabd15a9; ?>
<?php unset($__attributesOriginalbb2a9f74a67315de3fb30cbbfabd15a9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbb2a9f74a67315de3fb30cbbfabd15a9)): ?>
<?php $component = $__componentOriginalbb2a9f74a67315de3fb30cbbfabd15a9; ?>
<?php unset($__componentOriginalbb2a9f74a67315de3fb30cbbfabd15a9); ?>
<?php endif; ?>

    <!-- Formulario -->
    <form method="POST" action="<?php echo e(route('bots.update', $bot)); ?>">
      <?php echo csrf_field(); ?>
      <?php echo method_field('PATCH'); ?>

      <!-- Componente de formulario reutilizable -->
      <?php if (isset($component)) { $__componentOriginalbe11ceca17d16942d1035a4629cff236 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe11ceca17d16942d1035a4629cff236 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.bots.bot-form','data' => ['bot' => $bot]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('bots.bot-form'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['bot' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bot)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe11ceca17d16942d1035a4629cff236)): ?>
<?php $attributes = $__attributesOriginalbe11ceca17d16942d1035a4629cff236; ?>
<?php unset($__attributesOriginalbe11ceca17d16942d1035a4629cff236); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe11ceca17d16942d1035a4629cff236)): ?>
<?php $component = $__componentOriginalbe11ceca17d16942d1035a4629cff236; ?>
<?php unset($__componentOriginalbe11ceca17d16942d1035a4629cff236); ?>
<?php endif; ?>

      <!-- Componente de niveles -->
      <?php if (isset($component)) { $__componentOriginal19a6b114b8a210dd65031b8094d0044c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal19a6b114b8a210dd65031b8094d0044c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.bots.bot-levels-form','data' => ['bot' => $bot]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('bots.bot-levels-form'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['bot' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bot)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal19a6b114b8a210dd65031b8094d0044c)): ?>
<?php $attributes = $__attributesOriginal19a6b114b8a210dd65031b8094d0044c; ?>
<?php unset($__attributesOriginal19a6b114b8a210dd65031b8094d0044c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal19a6b114b8a210dd65031b8094d0044c)): ?>
<?php $component = $__componentOriginal19a6b114b8a210dd65031b8094d0044c; ?>
<?php unset($__componentOriginal19a6b114b8a210dd65031b8094d0044c); ?>
<?php endif; ?>

      <!-- Botones de Acción -->
      <?php if (isset($component)) { $__componentOriginal98d29adaa4406bec3dfa33b112d9a220 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal98d29adaa4406bec3dfa33b112d9a220 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-actions','data' => ['submitText' => __('bots.save_changes'),'submitColor' => '#10b981','cancelUrl' => route('bots.show', $bot)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-actions'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['submitText' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('bots.save_changes')),'submitColor' => '#10b981','cancelUrl' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('bots.show', $bot))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal98d29adaa4406bec3dfa33b112d9a220)): ?>
<?php $attributes = $__attributesOriginal98d29adaa4406bec3dfa33b112d9a220; ?>
<?php unset($__attributesOriginal98d29adaa4406bec3dfa33b112d9a220); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal98d29adaa4406bec3dfa33b112d9a220)): ?>
<?php $component = $__componentOriginal98d29adaa4406bec3dfa33b112d9a220; ?>
<?php unset($__componentOriginal98d29adaa4406bec3dfa33b112d9a220); ?>
<?php endif; ?>
    </form>
  </main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /app/resources/views/bots/edit.blade.php ENDPATH**/ ?>