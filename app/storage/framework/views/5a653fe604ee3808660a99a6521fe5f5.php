

<div id="<?php echo e($id); ?>" class="matrix-container <?php echo e($class ?? ''); ?>">
  <div class="matrix-wrapper">
    <?php $__currentLoopData = $rows(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rowIndex => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="matrix-row" data-row="<?php echo e($rowIndex); ?>">
        <?php $__currentLoopData = $row; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cellIndex => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
            $globalIndex = $getItemIndex($rowIndex, $cellIndex);
          ?>

          <?php if($item): ?>
            <?php if (isset($component)) { $__componentOriginal511d4862ff04963c3c16115c05a86a9d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal511d4862ff04963c3c16115c05a86a9d = $attributes; } ?>
<?php $component = Illuminate\View\DynamicComponent::resolve(['component' => $cellComponent] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dynamic-component'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\DynamicComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['item' => $item,'rowIndex' => $rowIndex,'cellIndex' => $cellIndex,'globalIndex' => $globalIndex]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal511d4862ff04963c3c16115c05a86a9d)): ?>
<?php $attributes = $__attributesOriginal511d4862ff04963c3c16115c05a86a9d; ?>
<?php unset($__attributesOriginal511d4862ff04963c3c16115c05a86a9d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal511d4862ff04963c3c16115c05a86a9d)): ?>
<?php $component = $__componentOriginal511d4862ff04963c3c16115c05a86a9d; ?>
<?php unset($__componentOriginal511d4862ff04963c3c16115c05a86a9d); ?>
<?php endif; ?>
          <?php else: ?>
            <div class="matrix-cell empty" data-index="<?php echo e($globalIndex); ?>"></div>
          <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>

<style>
  .matrix-container {
    width: 100%;
    overflow-x: auto;
    border-radius: 0.5rem;
  }

  .matrix-wrapper {
    display: inline-block;
    min-width: 100%;
  }

  .matrix-row {
    display: flex;
    gap: 0;
    border-bottom: 1px solid var(--matrix-border, #2e3a48);
  }

  .matrix-row:last-child {
    border-bottom: none;
  }

  .matrix-cell {
    flex: 1;
    aspect-ratio: 1;
    min-width: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-right: 1px solid var(--matrix-border, #2e3a48);
    padding: 0.25rem;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
  }

  .matrix-cell:last-child {
    border-right: none;
  }

  .matrix-cell:hover:not(.empty) {
    transform: scale(1.05);
    z-index: 10;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
  }

  .matrix-cell.empty {
    background-color: rgba(15, 20, 25, 0.3);
    cursor: default;
  }

  .matrix-cell.empty:hover {
    transform: none;
    box-shadow: none;
  }

  @media (max-width: 768px) {
    .matrix-cell {
      min-width: 30px;
      font-size: 0.75rem;
      padding: 0.125rem;
    }
  }

  @media (max-width: 480px) {
    .matrix-cell {
      min-width: 24px;
      font-size: 0.65rem;
      padding: 0;
      aspect-ratio: 1;
    }
  }
</style>
<?php /**PATH /app/resources/views/components/matrix.blade.php ENDPATH**/ ?>