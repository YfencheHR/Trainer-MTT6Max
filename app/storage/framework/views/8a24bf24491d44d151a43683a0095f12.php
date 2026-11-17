<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['handId']));

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

foreach (array_filter((['handId']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>


<div x-data="botsActionRanges(<?php echo e($handId); ?>)" style="background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 0.75rem;">
  <p style="color: #d1d5db; font-weight: 600; margin: 0 0 0.5rem; font-size: 0.8rem; text-transform: uppercase;">
    🎯 Rivales en acción · rangos
  </p>

  <div x-show="activeBots.length === 0" style="color: #9ca3af; font-size: 0.8rem;">
    Esperando acción de los rivales...
  </div>

  <div x-show="activeBots.length > 0" style="display: flex; flex-direction: column; gap: 0.75rem;">
    <div style="display: flex; flex-wrap: wrap; gap: 0.6rem; font-size: 0.65rem; color: #9ca3af;">
      <span><span style="display:inline-block;width:0.6rem;height:0.6rem;background:#4b5563;border-radius:2px;"></span> Fold</span>
      <span><span style="display:inline-block;width:0.6rem;height:0.6rem;background:#1d4ed8;border-radius:2px;"></span> Call</span>
      <span><span style="display:inline-block;width:0.6rem;height:0.6rem;background:#b91c1c;border-radius:2px;"></span> Raise</span>
      <span><span style="display:inline-block;width:0.6rem;height:0.6rem;background:#15803d;border-radius:2px;"></span> Check</span>
    </div>

    <template x-for="bot in activeBots" :key="bot.id">
      <div style="border-top: 1px solid #2e3a48; padding-top: 0.5rem;" :style="{ opacity: bot.is_folded ? 0.55 : 1 }">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem; gap: 0.5rem;">
          <div style="display: flex; align-items: center; gap: 0.4rem; min-width: 0;">
            <span style="color: #e5e7eb; font-size: 0.8rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="bot.name"></span>
            <span style="font-size: 0.65rem; background: #2e3a48; color: #d1d5db; padding: 0 0.35rem; border-radius: 0.25rem;" x-text="bot.position"></span>
          </div>
          <div style="display: flex; gap: 0.25rem; flex-shrink: 0;">
            <span x-show="bot.action" style="font-size: 0.65rem; background: #1e3a8a; color: #bfdbfe; padding: 0 0.35rem; border-radius: 0.25rem;" x-text="(bot.action || '').toUpperCase()"></span>
            <span x-show="bot.is_folded" style="font-size: 0.65rem; background: #7f1d1d; color: #fecaca; padding: 0 0.35rem; border-radius: 0.25rem;">FOLD</span>
            <span x-show="bot.is_all_in && !bot.is_folded" style="font-size: 0.65rem; background: #7c2d12; color: #fed7aa; padding: 0 0.35rem; border-radius: 0.25rem;">ALL-IN</span>
          </div>
        </div>

        <div x-show="bot.ranges && Object.keys(bot.ranges).length > 0">
        <div style="display: grid; grid-template-columns: repeat(13, minmax(0, 1fr)); gap: 1px;">
          <template x-for="c in cells(bot)" :key="c.hand">
            <div :title="c.title" :style="{ background: c.bg, opacity: c.active ? 1 : 0.35 }"
              style="aspect-ratio: 1; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.5rem; font-weight: 600; text-shadow: 0 1px 2px #000; border-radius: 2px; overflow: hidden; user-select: none;"
              x-text="c.hand"></div>
          </template>
        </div>
        </div>

        <div x-show="!bot.ranges || Object.keys(bot.ranges).length === 0" style="color: #9ca3af; font-size: 0.75rem;">
          <span x-show="bot.is_folded">Se fue (fold)</span>
          <span x-show="!bot.is_folded">Esperando rango...</span>
        </div>
      </div>
    </template>
  </div>
</div>
<?php /**PATH /app/resources/views/components/bots-action-ranges.blade.php ENDPATH**/ ?>