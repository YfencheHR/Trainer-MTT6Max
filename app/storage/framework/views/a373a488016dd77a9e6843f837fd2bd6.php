<?php $__env->startSection('content'); ?>
  <div style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-4xl font-bold" style="color: #d1d5db;"><?php echo e(__('tournaments.title')); ?></h1>
        <p style="color: #a1a5b0;" class="text-lg mt-2"><?php echo e(__('tournaments.description')); ?></p>
      </div>
      <a href="<?php echo e(route('mtt-simulator.create')); ?>" style="background-color: #047857; color: white;" class="px-6 py-3 rounded font-semibold hover:opacity-80 transition">
        <?php echo e(__('tournaments.create_new')); ?>

      </a>
    </div>

    <?php if(session('success')): ?>
      <div class="mb-6 p-4 rounded" style="background-color: #1e3a1f; color: #10b981;">
        <?php echo e(session('success')); ?>

      </div>
    <?php endif; ?>

    <!-- Torneos Activos -->
    <div class="mb-12">
      <h2 class="text-2xl font-bold mb-4" style="color: #d1d5db;"><?php echo e(__('tournaments.active')); ?></h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php $__empty_1 = true; $__currentLoopData = $tournaments->where('status', 'in_progress'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tournament): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 hover:border-blue-500 transition flex flex-col">
            <div class="mb-4">
              <h3 style="color: #10b981;" class="text-lg font-bold"><?php echo e($tournament->title); ?></h3>
              <p style="color: #9ca3af;" class="text-sm mt-1"><?php echo e($tournament->getConfig('basic_settings.num_players')); ?> <?php echo e(__('tournaments.players')); ?></p>
              <p style="color: #9ca3af;" class="text-sm"><?php echo e(__('tournaments.level')); ?>: <?php echo e($tournament->current_level); ?></p>
            </div>
            <div class="mb-4">
              <p style="color: #a78bfa;"><?php echo e($tournament->hand_count); ?> <?php echo e(__('tournaments.hands_played')); ?></p>
            </div>
            <div class="flex gap-3">
              <a href="<?php echo e(route('mtt-simulator.play', $tournament)); ?>" style="background-color: #047857; color: white;" class="flex-1 px-4 py-2 rounded text-center font-semibold hover:opacity-80 transition">
                <?php echo e(__('tournaments.continue')); ?>

              </a>
              <a href="<?php echo e(route('mtt-simulator.show', $tournament)); ?>" style="background-color: #2563eb; color: white;" class="flex-1 px-4 py-2 rounded text-center font-semibold hover:opacity-80 transition">
                <?php echo e(__('tournaments.view')); ?>

              </a>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <div class="col-span-full text-center text-slate-400 py-12">
            <?php echo e(__('tournaments.no_active')); ?>

          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Torneos Completados -->
    <div>
      <h2 class="text-2xl font-bold mb-4" style="color: #d1d5db;"><?php echo e(__('tournaments.completed')); ?></h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php $__empty_1 = true; $__currentLoopData = $tournaments->where('status', 'completed'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tournament): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 hover:border-purple-500 transition flex flex-col">
            <div class="mb-4">
              <h3 style="color: #d8b4fe;" class="text-lg font-bold"><?php echo e($tournament->title); ?></h3>
              <p style="color: #9ca3af;" class="text-sm mt-1"><?php echo e($tournament->getConfig('basic_settings.num_players')); ?> <?php echo e(__('tournaments.players')); ?></p>
              <p style="color: #9ca3af;" class="text-sm"><?php echo e($tournament->ended_at?->format('d/m/Y H:i')); ?></p>
            </div>
            <div class="mb-4">
              <p style="color: #a78bfa;"><?php echo e($tournament->hand_count); ?> <?php echo e(__('tournaments.hands_played')); ?></p>
            </div>
            <a href="<?php echo e(route('mtt-simulator.show', $tournament)); ?>" style="background-color: #9333ea; color: white;" class="px-4 py-2 rounded text-center font-semibold hover:opacity-80 transition">
              <?php echo e(__('tournaments.view_details')); ?>

            </a>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <div class="col-span-full text-center text-slate-400 py-12">
            <?php echo e(__('tournaments.no_completed')); ?>

          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /app/resources/views/mtt-simulator/index.blade.php ENDPATH**/ ?>