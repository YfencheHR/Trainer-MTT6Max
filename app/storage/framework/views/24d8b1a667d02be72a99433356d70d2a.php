<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'visuals' => null,
    'card' => null,
    'bgColor' => '#047857',
    'textColor' => 'white',
    'padding' => '6px 8px',
    'fontSize' => '12px',
    'minWidth' => '38px',
    'borderRadius' => '4px',
    'size' => 'medium'
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
    'visuals' => null,
    'card' => null,
    'bgColor' => '#047857',
    'textColor' => 'white',
    'padding' => '6px 8px',
    'fontSize' => '12px',
    'minWidth' => '38px',
    'borderRadius' => '4px',
    'size' => 'medium'
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    // Configuración de tamaños predefinidos
    $sizeConfig = [
        'small' => [
            'padding' => '2px 6px',
            'fontSize' => '11px',
            'minWidth' => '28px',
        ],
        'medium' => [
            'padding' => '6px 8px',
            'fontSize' => '12px',
            'minWidth' => '38px',
        ],
        'large' => [
            'padding' => '8px 10px',
            'fontSize' => '14px',
            'minWidth' => '42px',
        ]
    ];

    $config = $sizeConfig[$size] ?? $sizeConfig['medium'];
    $finalPadding = $padding ?? $config['padding'];
    $finalFontSize = $fontSize ?? $config['fontSize'];
    $finalMinWidth = $minWidth ?? $config['minWidth'];
?>

<?php if($card): ?>
  
  <span style="background-color: <?php echo e($bgColor); ?>; color: <?php echo e($textColor); ?>; padding: <?php echo e($finalPadding); ?>; border-radius: <?php echo e($borderRadius); ?>; font-size: <?php echo e($finalFontSize); ?>; font-weight: 600; min-width: <?php echo e($finalMinWidth); ?>; text-align: center; display: inline-block;"
    <?php echo e($attributes); ?>>
    <?php echo e($card); ?>

  </span>
<?php elseif($visuals && count($visuals) > 0): ?>
  
  <?php $__currentLoopData = $visuals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $visual): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php $__currentLoopData = $visual['cards']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cardValue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <span style="background-color: <?php echo e($bgColor); ?>; color: <?php echo e($textColor); ?>; padding: <?php echo e($finalPadding); ?>; border-radius: <?php echo e($borderRadius); ?>; font-size: <?php echo e($finalFontSize); ?>; font-weight: 600; min-width: <?php echo e($finalMinWidth); ?>; text-align: center; display: inline-block;"
        <?php echo e($attributes); ?>>
        <?php echo e($cardValue); ?>

      </span>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php if($visual['separator'] ?? false): ?>
      <span style="color: <?php echo e($bgColor); ?>; padding: 0 4px; font-weight: 600;">|</span>
    <?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php else: ?>
  
  <span style="background-color: <?php echo e($bgColor); ?>; color: <?php echo e($textColor); ?>; padding: <?php echo e($finalPadding); ?>; border-radius: <?php echo e($borderRadius); ?>;">—</span>
<?php endif; ?>
<?php /**PATH /app/resources/views/components/card-visual.blade.php ENDPATH**/ ?>