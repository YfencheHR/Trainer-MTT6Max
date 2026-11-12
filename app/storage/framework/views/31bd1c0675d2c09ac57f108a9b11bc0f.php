<!DOCTYPE html>
<html lang="es" class="dark">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="reverb-config" content="<?php echo e(json_encode([
      'key' => config('broadcasting.connections.reverb.key'),
      'host' => config('services.reverb_client.host'),
      'port' => config('services.reverb_client.port'),
      'scheme' => config('services.reverb_client.scheme'),
    ])); ?>">
    <title>Trainer MTT - 6 Max Poker</title>
    <link rel="icon" type="image/png" href="/images/poker-trainer-icon.png" />
    <link rel="stylesheet" href="<?php echo e(asset('assets/fonts.css')); ?>" />
    <link rel="stylesheet" href="<?php echo e(asset('assets/app.css')); ?>" />
  </head>
  <body style="background-color: #12161c;" class="text-white min-h-screen">
    <?php if (isset($component)) { $__componentOriginala591787d01fe92c5706972626cdf7231 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala591787d01fe92c5706972626cdf7231 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.navbar','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('navbar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala591787d01fe92c5706972626cdf7231)): ?>
<?php $attributes = $__attributesOriginala591787d01fe92c5706972626cdf7231; ?>
<?php unset($__attributesOriginala591787d01fe92c5706972626cdf7231); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala591787d01fe92c5706972626cdf7231)): ?>
<?php $component = $__componentOriginala591787d01fe92c5706972626cdf7231; ?>
<?php unset($__componentOriginala591787d01fe92c5706972626cdf7231); ?>
<?php endif; ?>
    <?php echo $__env->yieldContent('content'); ?>
    <script type="module" src="<?php echo e(asset('assets/app.js')); ?>"></script>
  </body>
</html>
<?php /**PATH /app/resources/views/app.blade.php ENDPATH**/ ?>