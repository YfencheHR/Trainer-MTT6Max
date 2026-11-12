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


<div x-data='{
    stackInBb(stack, bigBlind) {
      return ((Number(stack) || 0) / (Number(bigBlind) || 1)).toLocaleString(<?php echo json_encode(app()->getLocale(), 15, 512) ?>, { maximumFractionDigits: 1 }) + "bb";
    }
  }' style="background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 1rem;">
  <p style="color: #d1d5db; font-weight: 600; margin: 0 0 0.75rem; font-size: 0.875rem; text-transform: uppercase;">
    🎰 <?php echo e(__('tournaments.active_tables')); ?>

    <span style="color: #9ca3af; font-weight: 400; font-size: 0.75rem;" x-text="'· ' + ($store.tournamentInfo.data.tables || []).length"></span>
  </p>
  <p x-show="!$store.tournamentInfo.data.hero_eliminated" style="margin: -0.35rem 0 0.75rem; color: #9ca3af; font-size: 0.72rem;">
    <?php echo e(__('tournaments.switch_after_elimination')); ?>

  </p>

  <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 0.5rem;">
    <template x-for="t in ($store.tournamentInfo.data.tables || [])" :key="t.id">
      <button type="button" @click="$dispatch('switch-game-table', t)"
        :disabled="!$store.tournamentInfo.data.hero_eliminated || t.is_hero || t.players < 2"
        :title="t.is_hero ? <?php echo \Illuminate\Support\Js::from(__('tournaments.current_table_title'))->toHtml() ?> : (t.players < 2 ? <?php echo \Illuminate\Support\Js::from(__('tournaments.table_not_switchable'))->toHtml() ?> : (!$store.tournamentInfo.data.hero_eliminated ? <?php echo \Illuminate\Support\Js::from(__('tournaments.switch_requires_elimination'))->toHtml() ?> : <?php echo \Illuminate\Support\Js::from(__('tournaments.switch_to_table'))->toHtml() ?>))"
        style="display: block; width: 100%; text-align: left; color: inherit; background-color: #0f1419; border: 1px solid #2e3a48; border-radius: 0.375rem; padding: 0.6rem 0.75rem; border-left: 3px solid #047857;"
        :style="{
          borderLeftColor: t.is_hero ? '#fbbf24' : '#047857',
          cursor: !$store.tournamentInfo.data.hero_eliminated || t.is_hero || t.players < 2 ? 'not-allowed' : 'pointer',
          opacity: !$store.tournamentInfo.data.hero_eliminated && !t.is_hero ? 0.65 : 1
        }">
        <div style="display: flex; justify-content: space-between; align-items: baseline;">
          <span style="color: #d1d5db; font-weight: 600; font-size: 0.85rem;" x-text="<?php echo \Illuminate\Support\Js::from(__('tournaments.table'))->toHtml() ?> + ' ' + t.number"></span>
          <span x-show="t.is_hero" style="color: #fbbf24; font-size: 0.65rem; font-weight: 700;"><?php echo e(__('tournaments.currently_viewed')); ?></span>
          <span x-show="!t.is_hero && $store.tournamentInfo.data.hero_eliminated && t.players >= 2" style="color: #34d399; font-size: 0.65rem; font-weight: 700;"><?php echo e(__('tournaments.switch_table')); ?></span>
        </div>
        <p style="margin: 0.2rem 0 0; color: #9ca3af; font-size: 0.75rem;"
          x-text="t.players + ' ' + (t.players === 1 ? <?php echo \Illuminate\Support\Js::from(__('tournaments.player'))->toHtml() ?> : <?php echo \Illuminate\Support\Js::from(__('tournaments.players'))->toHtml() ?>) + ' · ' + <?php echo \Illuminate\Support\Js::from(__('tournaments.average_stack_short'))->toHtml() ?> + ' ' + stackInBb(t.avg_stack, $store.tournamentInfo.data.big_blind)"></p>
        <div style="margin-top: 0.45rem; border-top: 1px solid #2e3a48; padding-top: 0.35rem;">
          <div style="display: flex; justify-content: space-between; gap: 0.5rem; color: #6b7280; font-size: 0.62rem; text-transform: uppercase; margin-bottom: 0.15rem;">
            <span><?php echo e(__('tournaments.players_at_table')); ?></span>
            <span><?php echo e(__('tournaments.stack')); ?></span>
          </div>
          <template x-for="player in (t.seated_players || [])" :key="player.id">
            <div style="display: flex; justify-content: space-between; gap: 0.5rem; color: #d1d5db; font-size: 0.72rem; line-height: 1.45;">
              <span style="min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="player.name"></span>
              <span style="flex-shrink: 0; color: #a78bfa;" x-text="stackInBb(player.stack, $store.tournamentInfo.data.big_blind)"></span>
            </div>
          </template>
        </div>
      </button>
    </template>
    <p x-show="!($store.tournamentInfo.data.tables || []).length" style="margin: 0; color: #6b7280; font-size: 0.8rem;"><?php echo e(__('tournaments.no_table_information')); ?></p>
  </div>
</div>
<?php /**PATH /app/resources/views/components/active-tables.blade.php ENDPATH**/ ?>