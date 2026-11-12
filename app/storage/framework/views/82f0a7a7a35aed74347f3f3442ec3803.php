

<?php $__env->startSection('content'); ?>
  <main style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
    <?php echo $__env->make('bots._pool-preflop-tables', [
      'bot' => $bot,
      'tables' => $tables,
      'onlyPosition' => $onlyPosition,
      'onlyType' => $onlyType,
      'editorPage' => true,
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /app/resources/views/bots/preflop-table-editor.blade.php ENDPATH**/ ?>