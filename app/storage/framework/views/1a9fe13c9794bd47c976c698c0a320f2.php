<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([]));

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

foreach (array_filter(([]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div x-data="{
    get eliminations() {
      return $store.tournamentInfo.data.elimination_history || [];
    }
  }"
  style="background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 1rem;">
  <p style="display: flex; justify-content: space-between; align-items: center; color: #d1d5db; font-weight: 600; margin: 0 0 0.75rem; font-size: 0.875rem; text-transform: uppercase;">
    <span>☠️ <?php echo e(__('tournaments.elimination_history')); ?></span>
    <span style="color: #9ca3af; font-size: 0.75rem; font-weight: 400;" x-text="eliminations.length"></span>
  </p>

  <div x-show="eliminations.length" style="max-height: 320px; overflow-y: auto;">
    <template x-for="player in eliminations" :key="player.id">
      <div style="display: flex; align-items: center; gap: 0.65rem; padding: 0.45rem 0.15rem; border-bottom: 1px solid #2e3a48;">
        <span style="display: inline-flex; align-items: center; justify-content: center; flex: 0 0 2.5rem; min-height: 1.8rem; background: rgba(239,68,68,0.12); border-radius: 0.35rem; color: #f87171; font-size: 0.78rem; font-weight: 800;"
          x-text="'#' + (player.placement || '—')"></span>
        <div style="min-width: 0; flex: 1;">
          <p style="margin: 0; color: #e5e7eb; font-size: 0.8rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="player.name"></p>
          <p style="margin: 0.1rem 0 0; color: #9ca3af; font-size: 0.68rem;"
            x-text="<?php echo \Illuminate\Support\Js::from(__('tournaments.table'))->toHtml() ?> + ' ' + (player.table_number || '—') + ' · ' + <?php echo \Illuminate\Support\Js::from(__('tournaments.round'))->toHtml() ?> + ' ' + (player.round || '—')"></p>
        </div>
      </div>
    </template>
  </div>

  <p x-show="!eliminations.length" style="margin: 0; color: #9ca3af; font-size: 0.75rem;">
    <?php echo e(__('tournaments.no_eliminations')); ?>

  </p>
</div>
<?php /**PATH /app/resources/views/components/elimination-history.blade.php ENDPATH**/ ?>