<nav class="sticky top-0 z-50" style="background-color: #1a212a; border-bottom: 1px solid #2e3a48; margin-bottom: 22px;">
  <div style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">
      <a href="<?php echo e(route('dashboard')); ?>" class="flex items-center gap-3 hover:opacity-80 transition">
        <img src="/images/poker-trainer-icon.png" alt="<?php echo e(__('common.nav_logo')); ?>" style="width: 40px; height: 40px; object-fit: contain;">
        <span class="text-lg font-bold text-white"><?php echo e(__('common.nav_app_name')); ?></span>
      </a>

      <div class="hidden md:flex items-center gap-8">
        <a href="<?php echo e(route('mtt-simulator.index')); ?>" class="text-slate-400 hover:text-blue-400 transition"><?php echo e(__('common.nav_mtt_simulator')); ?></a>
        <a href="<?php echo e(route('bots.index')); ?>" class="text-slate-400 hover:text-blue-400 transition"><?php echo e(__('common.nav_rivals')); ?></a>
        <a href="<?php echo e(route('hand-matrix.index')); ?>" class="text-slate-400 hover:text-blue-400 transition"><?php echo e(__('common.nav_hands')); ?></a>
        <a href="<?php echo e(route('made-hands.index')); ?>" class="text-slate-400 hover:text-blue-400 transition"><?php echo e(__('common.nav_make_hands')); ?></a>
        <a href="<?php echo e(route('import.index')); ?>" class="transition <?php echo e(request()->routeIs('import.*') ? 'text-blue-400' : 'text-slate-400 hover:text-blue-400'); ?>"><?php echo e(__('common.nav_import')); ?></a>
      </div>
      <span class="bg-blue-600 text-white px-3 py-1 rounded text-xs font-bold"><?php echo e(__('common.nav_beta')); ?></span>
    </div>
  </div>
</nav>
<?php /**PATH /app/resources/views/components/navbar.blade.php ENDPATH**/ ?>