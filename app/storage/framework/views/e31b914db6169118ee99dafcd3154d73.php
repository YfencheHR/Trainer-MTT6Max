<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['tournament' => null]));

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

foreach (array_filter((['tournament' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>


<div x-data="{
    get t() { return $store.tournamentInfo.data; },
    bb(v) { const b = this.t.big_blind || 1; return ((Number(v) || 0) / b).toLocaleString('es-ES', { maximumFractionDigits: 1 }); },
  }"
  style="background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 0.75rem;">

  <p style="color: #d1d5db; font-weight: 600; margin: 0 0 0.5rem; font-size: 0.8rem; text-transform: uppercase;">
    📊 <?php echo e(__('tournaments.tournament_info')); ?>

  </p>

  <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.4rem;">
    <?php
        $cell = 'background: rgba(0,0,0,0.25); border-radius: 0.35rem; padding: 0.35rem 0.4rem; min-width: 0;';
        $lab = 'margin: 0; font-size: 0.58rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.03em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;';
        $val = 'margin: 0; font-size: 0.95rem; font-weight: 700; line-height: 1.25; white-space: nowrap;';
    ?>

    <div style="<?php echo e($cell); ?>"><p style="<?php echo e($lab); ?>">Nivel</p><p style="<?php echo e($val); ?> color: #10b981;" x-text="t.level ?? '–'"></p></div>
    <div style="<?php echo e($cell); ?>"><p style="<?php echo e($lab); ?>">Ciegas</p><p style="<?php echo e($val); ?> color: #06b6d4;" x-text="t.big_blind ? (t.small_blind + '/' + t.big_blind) : '–'"></p></div>
    <div style="<?php echo e($cell); ?>"><p style="<?php echo e($lab); ?>">Mano</p><p style="<?php echo e($val); ?> color: #f59e0b;" x-text="'#' + (t.hand_number ?? '–')"></p></div>

    <div style="<?php echo e($cell); ?>"><p style="<?php echo e($lab); ?>">Quedan</p><p style="<?php echo e($val); ?> color: #60a5fa;" x-text="(t.players_alive ?? '–') + '/' + (t.players_total ?? '–')"></p></div>
    <div style="<?php echo e($cell); ?>"><p style="<?php echo e($lab); ?>">Mi puesto</p><p style="<?php echo e($val); ?> color: #e5e7eb;" x-text="t.hero_rank ? (t.hero_rank + 'º') : '–'"></p></div>
    <div style="<?php echo e($cell); ?>"><p style="<?php echo e($lab); ?>">Mi stack</p><p style="<?php echo e($val); ?> color: #60a5fa;" x-text="bb(t.hero_stack) + 'bb'"></p></div>

    <div style="<?php echo e($cell); ?>"><p style="<?php echo e($lab); ?>">Medio</p><p style="<?php echo e($val); ?> color: #a78bfa;" x-text="bb(t.avg_stack) + 'bb'"></p></div>
    <div style="<?php echo e($cell); ?>"><p style="<?php echo e($lab); ?>">Mayor</p><p style="<?php echo e($val); ?> color: #a78bfa;" x-text="bb(t.max_stack) + 'bb'"></p></div>
    <div style="<?php echo e($cell); ?>"><p style="<?php echo e($lab); ?>">Mi M</p><p style="<?php echo e($val); ?> color: #e5e7eb;" x-text="t.big_blind ? ((t.hero_stack / (t.small_blind + t.big_blind)).toLocaleString('es-ES', { maximumFractionDigits: 1 })) : '–'"></p></div>
  </div>
</div>
<?php /**PATH /app/resources/views/components/tournament-info.blade.php ENDPATH**/ ?>