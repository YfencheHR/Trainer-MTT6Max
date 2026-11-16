

<?php
  $handName = $item['name'] ?? '';
  $backgroundColor = $item['bgColor'] ?? '#1e40af';

  // Determinar color por tipo
  if (str_ends_with($handName, 'o')) {
    $backgroundColor = '#92400e'; // Offsuit
  } elseif (str_ends_with($handName, 's')) {
    $backgroundColor = '#1e40af'; // Suited
  } elseif (strlen($handName) == 2) {
    $backgroundColor = '#047857'; // Pair
  }
?>

<div
  class="matrix-cell hand-cell"
  data-hand="<?php echo e($handName); ?>"
  data-index="<?php echo e($globalIndex); ?>"
  style="background: linear-gradient(135deg, <?php echo e($backgroundColor); ?> 0%, <?php echo e($backgroundColor); ?> 100%);"
>
  <div class="cell-content">
    <div class="hand-name"><?php echo e($handName); ?></div>
    <?php if(isset($item['ev'])): ?>
      <div class="hand-value"><?php echo e(number_format($item['ev'], 2)); ?></div>
    <?php endif; ?>
  </div>
</div>

<style>
  .cell-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    color: white;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
  }

  .hand-name {
    font-size: 0.875rem;
    font-weight: bold;
    line-height: 1;
  }

  .hand-value {
    font-size: 0.65rem;
    margin-top: 0.15rem;
    opacity: 0.9;
  }
</style>
<?php /**PATH /app/resources/views/components/matrix/hand-cell.blade.php ENDPATH**/ ?>