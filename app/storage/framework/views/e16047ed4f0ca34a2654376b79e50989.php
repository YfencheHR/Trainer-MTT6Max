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


<div x-data style="background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 0.75rem; height: 610px; display: flex; flex-direction: column;">
  <p style="color: #d1d5db; font-weight: 600; margin: 0 0 0.5rem; font-size: 0.8rem; text-transform: uppercase;">
    📝 <?php echo e(__('tournaments.game_log')); ?>

  </p>

  <div class="game-log-scroll" style="flex: 1; overflow-y: auto; min-height: 0;">
    <template x-for="(entry, i) in $store.gameLog.all().reverse()" :key="entry.time + '-' + entry.text + '-' + i">
      <div style="padding: 0.4rem 0.4rem 0.4rem 0.5rem; border-bottom: 1px solid #2e3a48; border-left: 3px solid transparent;"
        :style="{ borderLeftColor: entry.winner ? '#fbbf24' : (entry.type === 'street' ? '#3b82f6' : (entry.type && entry.type.startsWith('ev') ? ({ gain: '#34d399', loss: '#f87171', neutral: '#6b7280', na: '#a78bfa' }[entry.verdict] || 'transparent') : 'transparent')), background: entry.winner ? 'rgba(251,191,36,0.08)' : 'transparent' }">

        
        <div style="display: flex; gap: 0.5rem; align-items: baseline;">
          <span style="font-size: 0.62rem; color: #a78bfa; flex-shrink: 0;" x-text="entry.time"></span>
          <span style="font-size: 0.8rem;"
            :style="{ color: { start: '#10b981', win: '#fbbf24', street: '#60a5fa', deal: '#10b981', showdown: '#e5e7eb', action: '#d1d5db', info: '#9ca3af', ev: '#e5e7eb', ev_header: '#a78bfa', ev_total: '#e5e7eb', ev_allin: '#e5e7eb' }[entry.type] || '#d1d5db', fontWeight: ['win', 'street', 'showdown', 'deal', 'ev_header', 'ev_total', 'ev_allin'].includes(entry.type) ? 700 : 400 }"
            x-text="entry.text"></span>
          
          <span x-show="entry.ev" style="margin-left: auto; font-size: 0.8rem; font-weight: 800; white-space: nowrap;"
            :style="{ color: ({ gain: '#34d399', loss: '#f87171', neutral: '#9ca3af' }[entry.verdict] || '#9ca3af') }" x-text="(entry.verdict === 'gain' ? '🟢 ' : (entry.verdict === 'loss' ? '🔴 ' : '⚪ ')) + entry.ev + ' EV'"></span>
        </div>

        
        <div x-show="entry.cards && entry.cards.length" style="display: flex; gap: 0.25rem; margin-top: 0.3rem; flex-wrap: wrap; align-items: center;">
          <template x-for="(c, k) in (entry.cards || [])" :key="k">
            <span style="background: #fff; border-radius: 0.25rem; padding: 0.05rem 0.35rem; font-size: 0.85rem; font-weight: 800; min-width: 2rem; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.5);"
              :style="{ color: $store.gameLog.card(c).color }" x-text="$store.gameLog.card(c).value + $store.gameLog.card(c).suit"></span>
          </template>
          <span x-show="entry.hand" style="margin-left: 0.35rem; font-size: 0.78rem; font-weight: 700;"
            :style="{ color: entry.winner ? '#fbbf24' : '#9ca3af' }" x-text="entry.hand"></span>
        </div>

        <p x-show="entry.detail" style="margin: 0.2rem 0 0; font-size: 0.72rem; color: #9ca3af;" x-text="entry.detail"></p>

        
        <p x-show="entry.winner" style="margin: 0.25rem 0 0; font-size: 0.75rem; font-weight: 700; color: #fbbf24;">
          🏆 Gana <span x-text="entry.won"></span>
        </p>
      </div>
    </template>
    <p x-show="$store.gameLog.all().length === 0" style="margin: 0; font-size: 0.8rem; color: #6b7280;">Sin movimientos todavía</p>
  </div>

  <style>
    .game-log-scroll::-webkit-scrollbar { width: 6px; }
    .game-log-scroll::-webkit-scrollbar-track { background: #0f1419; border-radius: 3px; }
    .game-log-scroll::-webkit-scrollbar-thumb { background: #2e3a48; border-radius: 3px; }
    .game-log-scroll::-webkit-scrollbar-thumb:hover { background: #3a4556; }
  </style>
</div>
<?php /**PATH /app/resources/views/components/game-log.blade.php ENDPATH**/ ?>