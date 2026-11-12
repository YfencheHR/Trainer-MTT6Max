<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'item' => null
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
    'item' => null
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php if($item): ?>
    <?php
        $bgColor = $item['bgColor'] ?? '#047857';
        $handName = $item['name'] ?? $item['hand_name'] ?? '';
        $id = $item['id'] ?? null;
        $ev = $item['ev'] ?? $item['gto_ev'] ?? null;
    ?>

    <div
        class="hand-cell-container rounded text-center transition"
        data-hand-id="<?php echo e($id); ?>"
        data-hand-name="<?php echo e($handName); ?>"
        data-hand-ev="<?php echo e($ev); ?>"
        style="background-color: <?php echo e($bgColor); ?> !important; color: #fff !important; font-weight: bold !important; min-height: 60px !important; display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; gap: 3px !important; padding: 3px 2px !important; position: relative !important;"
        <?php echo e($attributes); ?>

    >
        <button
            type="button"
            data-edit-strategy="<?php echo e($id); ?>"
            style="font-weight: 900; font-size: 13px; line-height: 1.1; color: inherit;"
            title="<?php echo e(__('hand-matrix.edit_strategy')); ?>"
        >
            <?php echo e($handName); ?>

            <span data-strategy-count style="font-size: 9px; opacity: 0.75;"></span>
        </button>
        <div style="display: flex; justify-content: center; align-self: center;">
            <?php if (isset($component)) { $__componentOriginal6c0b65d0c21f77c983a849b3fca6c80c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6c0b65d0c21f77c983a849b3fca6c80c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.hand-cell-input','data' => ['name' => 'ev_'.e($id).'','value' => '0','min' => '0','max' => '200','step' => '0.01','size' => 'small']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('hand-cell-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'ev_'.e($id).'','value' => '0','min' => '0','max' => '200','step' => '0.01','size' => 'small']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6c0b65d0c21f77c983a849b3fca6c80c)): ?>
<?php $attributes = $__attributesOriginal6c0b65d0c21f77c983a849b3fca6c80c; ?>
<?php unset($__attributesOriginal6c0b65d0c21f77c983a849b3fca6c80c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6c0b65d0c21f77c983a849b3fca6c80c)): ?>
<?php $component = $__componentOriginal6c0b65d0c21f77c983a849b3fca6c80c; ?>
<?php unset($__componentOriginal6c0b65d0c21f77c983a849b3fca6c80c); ?>
<?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div style="background-color: #0f1419;" class="py-2 px-1 rounded"></div>
<?php endif; ?>
<?php /**PATH /app/resources/views/components/matrix/hand-cell.blade.php ENDPATH**/ ?>