<?php $__env->startSection('content'); ?>
  <main style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-end mb-8">
      <a href="<?php echo e(route('bots.create')); ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded"><?php echo e(__('bots.new_bot_button')); ?></a>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
      <div style="background-color: #1a212a;" class="p-4 rounded border border-slate-700">
        <p class="text-slate-400 text-sm"><?php echo e(__('bots.total_bots')); ?></p>
        <p class="text-3xl font-bold text-white"><?php echo e(count($bots)); ?></p>
      </div>
      <div style="background-color: #1a212a;" class="p-4 rounded border border-slate-700">
        <p class="text-slate-400 text-sm"><?php echo e(__('bots.professional')); ?></p>
        <p class="text-3xl font-bold text-white"><?php echo e($bots->where('profesional', true)->count()); ?></p>
      </div>
      <div style="background-color: #1a212a;" class="p-4 rounded border border-slate-700">
        <p class="text-slate-400 text-sm"><?php echo e(__('bots.recreational')); ?></p>
        <p class="text-3xl font-bold text-white"><?php echo e($bots->where('profesional', false)->count()); ?></p>
      </div>
      <div style="background-color: #1a212a;" class="p-4 rounded border border-slate-700">
        <p class="text-slate-400 text-sm"><?php echo e(__('bots.avg_vpip')); ?></p>
        <p class="text-3xl font-bold text-white"><?php echo e(round($bots->avg('vpip') ?? 0)); ?>%</p>
      </div>
    </div>

    
    <?php
      $secciones = [
        ['titulo' => 'Pool', 'nota' => 'Perfiles que se actualizan con jugadores reales de PokerTracker: una copia por cada primario y los perfiles nuevos que no encajan en ninguno.', 'bots' => $pool],
        ['titulo' => 'Perfiles primarios', 'nota' => 'Los arquetipos de referencia: la importación no los modifica.', 'bots' => $primarios],
      ];
    ?>

    <?php $__empty_1 = true; $__currentLoopData = $bots->isEmpty() ? [] : $secciones; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sec): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php if(count($sec['bots'])): ?>
        <section class="mb-10">
          <div class="flex flex-wrap items-baseline gap-3 mb-1">
            <h2 class="text-2xl font-bold text-white"><?php echo e($sec['titulo']); ?></h2>
            <span style="color: #10b981;" class="text-sm font-semibold"><?php echo e(count($sec['bots'])); ?></span>
          </div>
          <p style="color: #9ca3af;" class="text-sm mb-4"><?php echo e($sec['nota']); ?></p>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php $__currentLoopData = $sec['bots']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php echo $__env->make('bots._card', ['bot' => $bot], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>
        </section>
      <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="text-center text-slate-400 py-12">
        <?php echo e(__('bots.no_bots')); ?>. <a href="<?php echo e(route('bots.create')); ?>" class="text-blue-400 hover:text-blue-300"><?php echo e(__('bots.create_one')); ?></a>
      </div>
    <?php endif; ?>
  </main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /app/resources/views/bots/index.blade.php ENDPATH**/ ?>