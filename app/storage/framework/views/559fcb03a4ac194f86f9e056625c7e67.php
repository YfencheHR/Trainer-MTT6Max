<?php $__env->startSection('content'); ?>
<?php
    $bbFmt = fn($chips, $bb) => rtrim(rtrim(number_format($chips / max(1, $bb), 1, ',', ''), '0'), ',') . 'bb';
    $eventStyle = [
        'elimination' => ['#ef4444', '☠️'],
        'hero_eliminated' => ['#ef4444', '💀'],
        'table_break' => ['#60a5fa', '🪑'],
        'player_moved' => ['#60a5fa', '➡️'],
        'level_up' => ['#f59e0b', '📈'],
        'tournament_started' => ['#10b981', '🎰'],
        'tournament_finished' => ['#fbbf24', '🏆'],
    ];
    $query = request()->except('page');
?>

<div style="max-width: 1200px; margin: 0 auto; color: #d1d5db;" class="px-4 sm:px-6 lg:px-8 py-6">

  
  <div style="display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: flex-end; margin-bottom: 1.25rem;">
    <div>
      <p style="margin: 0; font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Historial del torneo</p>
      <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700;"><?php echo e($tournament->title); ?></h1>
      <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: #9ca3af;">
        <?php echo e($data['total_rounds']); ?> rondas jugadas · <?php echo e($tournament->players()->count()); ?> jugadores · estado: <?php echo e($tournament->status); ?>

      </p>
    </div>
    <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
      <?php if($tournament->status === 'in_progress'): ?>
        <a href="<?php echo e(route('mtt-simulator.play', $tournament)); ?>" style="padding: 0.55rem 1rem; border-radius: 0.4rem; background: #059669; color: #fff; font-weight: 600; font-size: 0.85rem; text-decoration: none;">← Volver a la mesa</a>
      <?php else: ?>
        <a href="<?php echo e(route('mtt-simulator.show', $tournament)); ?>" style="padding: 0.55rem 1rem; border-radius: 0.4rem; background: #374151; color: #fff; font-weight: 600; font-size: 0.85rem; text-decoration: none;">← Torneo</a>
      <?php endif; ?>
      <a href="<?php echo e(route('mtt-simulator.history.export', $tournament)); ?>" style="padding: 0.55rem 1rem; border-radius: 0.4rem; background: #1d4ed8; color: #fff; font-weight: 600; font-size: 0.85rem; text-decoration: none;" title="Todo lo grabado: asientos, eventos, manos, acciones y contexto de cada decisión de bot">⬇ Exportar JSON</a>
    </div>
  </div>

  
  <form method="GET" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; background: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 1.25rem;">
    <label style="font-size: 0.8rem; color: #9ca3af;">Mesa
      <select name="table" style="margin-left: 0.35rem; background: #0f1419; color: #e5e7eb; border: 1px solid #374151; border-radius: 0.35rem; padding: 0.3rem 0.5rem;">
        <option value="">Todas</option>
        <?php $__currentLoopData = $tables; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($t->id); ?>" <?php if(($filters['table'] ?? null) === $t->id): echo 'selected'; endif; ?>>Mesa <?php echo e($t->table_number); ?><?php echo e($t->is_active ? '' : ' (rota)'); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
    </label>
    <label style="font-size: 0.8rem; color: #9ca3af;">Jugador
      <select name="player" style="margin-left: 0.35rem; background: #0f1419; color: #e5e7eb; border: 1px solid #374151; border-radius: 0.35rem; padding: 0.3rem 0.5rem;">
        <option value="">Todos</option>
        <?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($p->id); ?>" <?php if(($filters['player'] ?? null) === $p->id): echo 'selected'; endif; ?>><?php echo e($p->name); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
    </label>
    <label style="font-size: 0.8rem; color: #9ca3af; display: flex; align-items: center; gap: 0.35rem;">
      <input type="checkbox" name="hero" value="1" <?php if($filters['hero']): echo 'checked'; endif; ?>> Solo mis manos
    </label>
    <button type="submit" style="padding: 0.4rem 0.9rem; border: none; border-radius: 0.35rem; background: #374151; color: #fff; font-size: 0.8rem; cursor: pointer;">Filtrar</button>
    <?php if($filters['table'] || $filters['hero'] || $filters['player']): ?>
      <a href="<?php echo e(route('mtt-simulator.history', $tournament)); ?>" style="font-size: 0.8rem; color: #9ca3af;">Quitar filtros</a>
    <?php endif; ?>
  </form>

  <?php if(empty($data['rounds'])): ?>
    <p style="color: #6b7280;">Todavía no hay manos terminadas con estos filtros.</p>
  <?php endif; ?>

  
  <?php $__currentLoopData = $data['rounds']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $round): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <section style="background: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; margin-bottom: 1rem; overflow: hidden;">
      <header style="display: flex; justify-content: space-between; align-items: baseline; padding: 0.6rem 1rem; background: #151b24; border-bottom: 1px solid #2e3a48;">
        <strong style="font-size: 0.95rem;">Ronda <?php echo e($round['round']); ?></strong>
        <span style="font-size: 0.78rem; color: #9ca3af;">
          <?php if($round['level']): ?> Nivel <?php echo e($round['level']); ?> · ciegas <?php echo e($round['small_blind']); ?>/<?php echo e($round['big_blind']); ?> <?php endif; ?>
        </span>
      </header>

      <?php $__currentLoopData = $round['events']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ev): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php [$color, $icon] = $eventStyle[$ev->type] ?? ['#9ca3af', '•']; ?>
        <div style="padding: 0.35rem 1rem; font-size: 0.8rem; border-bottom: 1px solid #232c38; border-left: 3px solid <?php echo e($color); ?>; color: <?php echo e($color); ?>;">
          <?php echo e($icon); ?> <?php echo e($ev->message); ?>

        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

      <?php $__currentLoopData = $round['hands']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div x-data="handDetail(<?php echo e($h['id']); ?>, <?php echo e($h['bb']); ?>)" style="border-bottom: 1px solid #232c38;">
          <button type="button" @click="toggle()" style="width: 100%; text-align: left; background: transparent; border: none; color: inherit; padding: 0.6rem 1rem; cursor: pointer; display: flex; flex-wrap: wrap; gap: 0.5rem 1rem; align-items: center;">
            <span style="background: #2b3441; border-radius: 0.3rem; padding: 0.1rem 0.5rem; font-size: 0.75rem; font-weight: 700;">
              <?php echo e($h['table_number'] ? 'Mesa ' . $h['table_number'] : 'Mano #' . $h['id']); ?>

            </span>

            <?php if($h['structured']): ?>
              <span style="font-size: 0.8rem; color: #9ca3af;"><?php echo e($h['players']); ?> jugadores</span>
              <span style="font-size: 0.85rem; color: #fbbf24; font-weight: 600;">
                🏆 <?php echo e($h['winner']); ?> +<?php echo e($bbFmt($h['winner_won'], $h['bb'])); ?>

                <?php if($h['winner_hand']): ?> <span style="font-weight: 400; color: #d1d5db;">· <?php echo e($h['winner_hand']); ?></span> <?php endif; ?>
              </span>
              <span style="font-size: 0.78rem; color: #9ca3af;">bote <?php echo e($bbFmt($h['pot'], $h['bb'])); ?><?php echo e($h['showdown'] ? ' · showdown' : ''); ?></span>
              <?php if($h['hero']): ?>
                <span style="margin-left: auto; font-size: 0.8rem; font-weight: 700; color: <?php echo e($h['hero']['net'] > 0 ? '#34d399' : ($h['hero']['net'] < 0 ? '#f87171' : '#9ca3af')); ?>;">
                  Tú (<?php echo e($h['hero']['position']); ?>) <?php echo e($h['hero']['net'] > 0 ? '+' : ''); ?><?php echo e($bbFmt($h['hero']['net'], $h['bb'])); ?>

                  <?php if($h['hero']['folded_street']): ?> · fold <?php echo e($h['hero']['folded_street']); ?> <?php endif; ?>
                </span>
              <?php endif; ?>
            <?php else: ?>
              <span style="font-size: 0.8rem; color: #6b7280;">Mano anterior al historial detallado</span>
            <?php endif; ?>
          </button>

          <?php if($h['structured']): ?>
            <div x-show="open" x-cloak style="padding: 0.5rem 1rem 1rem; background: #121820;">
              <p x-show="loading" style="margin: 0; color: #9ca3af; font-size: 0.85rem;">Cargando…</p>
              <p x-show="error" x-text="error" style="margin: 0; color: #f87171; font-size: 0.85rem;"></p>

              <template x-if="data">
                <div>
                  
                  <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; margin-bottom: 0.75rem;">
                    <div style="display: flex; gap: 0.3rem; align-items: center;">
                      <span style="font-size: 0.7rem; color: #9ca3af; text-transform: uppercase; margin-right: 0.25rem;">Board</span>
                      <template x-for="(c, i) in data.hand.board" :key="i">
                        <span class="hist-card" :style="{ color: card(c).color }" x-text="card(c).value + card(c).suit"></span>
                      </template>
                    </div>
                    <template x-for="(p, i) in data.hand.pots" :key="i">
                      <span style="font-size: 0.78rem; color: #fbbf24;" x-text="p.label + ' ' + fmt(p.amount) + (p.hand_name ? ' · ' + p.hand_name : '')"></span>
                    </template>
                  </div>

                  
                  <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem; margin-bottom: 0.9rem;">
                    <thead>
                      <tr style="color: #9ca3af; text-align: left; font-size: 0.68rem; text-transform: uppercase;">
                        <th style="padding: 0.25rem 0.4rem;">As.</th><th style="padding: 0.25rem 0.4rem;">Pos</th><th style="padding: 0.25rem 0.4rem;">Jugador</th>
                        <th style="padding: 0.25rem 0.4rem;">Stack</th><th style="padding: 0.25rem 0.4rem;">Cartas</th><th style="padding: 0.25rem 0.4rem;">Resultado</th><th style="padding: 0.25rem 0.4rem;">Detalle</th>
                      </tr>
                    </thead>
                    <tbody>
                      <template x-for="p in data.players" :key="p.id">
                        <tr style="border-top: 1px solid #232c38;" :style="{ background: p.won > 0 ? 'rgba(251,191,36,0.07)' : 'transparent' }">
                          <td style="padding: 0.3rem 0.4rem; color: #9ca3af;" x-text="p.seat"></td>
                          <td style="padding: 0.3rem 0.4rem; color: #cbd5e1;" x-text="p.position"></td>
                          <td style="padding: 0.3rem 0.4rem; font-weight: 600;" :style="{ color: p.is_hero ? '#60a5fa' : '#e5e7eb' }" x-text="p.name"></td>
                          <td style="padding: 0.3rem 0.4rem; color: #9ca3af;" x-text="fmt(p.stack_start)"></td>
                          <td style="padding: 0.3rem 0.4rem;">
                            <template x-for="(c, i) in p.cards" :key="i">
                              <span class="hist-card" :style="{ color: card(c).color }" x-text="card(c).value + card(c).suit"></span>
                            </template>
                          </td>
                          <td style="padding: 0.3rem 0.4rem; font-weight: 700;" :style="{ color: (p.stack_end - p.stack_start) > 0 ? '#34d399' : ((p.stack_end - p.stack_start) < 0 ? '#f87171' : '#9ca3af') }"
                            x-text="((p.stack_end - p.stack_start) > 0 ? '+' : '') + fmt(p.stack_end - p.stack_start)"></td>
                          <td style="padding: 0.3rem 0.4rem; color: #9ca3af;"
                            >
                            <span x-text="p.went_to_showdown ? ('Showdown · ' + p.hand_name) : (p.folded_street ? 'Fold ' + p.folded_street : '')"></span>
                            <div x-show="p.allin_equity !== null && p.ev_net !== null" style="font-size: 0.72rem; margin-top: 0.1rem;">
                              🎲 equity <span x-text="Math.round(p.allin_equity * 100) + '%'"></span> ·
                              EV <span x-text="signed(p.ev_net)"></span> ·
                              <b :style="{ color: p.ev_luck > 0 ? '#34d399' : (p.ev_luck < 0 ? '#f87171' : '#9ca3af') }"
                                x-text="p.ev_luck === 0 ? 'sin suerte' : ((p.ev_luck > 0 ? 'suerte ' : 'mala suerte ') + signed(p.ev_luck))"></b>
                            </div>
                          </td>
                        </tr>
                      </template>
                    </tbody>
                  </table>

                  
                  <template x-for="s in streets()" :key="s.name">
                    <div style="margin-bottom: 0.6rem;">
                      <div style="display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.2rem;">
                        <span style="font-size: 0.72rem; font-weight: 700; color: #60a5fa; text-transform: uppercase;" x-text="s.name"></span>
                        <template x-for="(c, i) in s.deal" :key="i">
                          <span class="hist-card" :style="{ color: card(c).color }" x-text="card(c).value + card(c).suit"></span>
                        </template>
                      </div>
                      <template x-for="a in s.actions" :key="a.seq">
                        <div style="font-size: 0.8rem; padding: 0.1rem 0 0.1rem 0.75rem; border-left: 2px solid #2e3a48;">
                          <span style="font-weight: 600;" x-text="a.player"></span>
                          <span x-text="text(a)"></span>
                          <span x-show="a.ev_verdict && a.ev_verdict !== 'na'" :title="evTitle(a)"
                            style="margin-left: 0.35rem; font-size: 0.72rem; font-weight: 800; padding: 0 0.35rem; border-radius: 0.25rem; cursor: help;"
                            :style="{ color: evColor(a.ev_verdict), background: evBg(a.ev_verdict) }" x-text="evText(a)"></span>
                          <span x-show="a.equity !== null && (!a.ev_verdict || a.ev_verdict === 'na')" style="color: #6b7280; font-size: 0.7rem;" x-text="'· eq ' + Math.round(a.equity * 100) + '%'"></span>
                          <span style="color: #6b7280; font-size: 0.72rem;" x-text="'· bote ' + fmt(a.pot_after)"></span>
                          <span x-show="a.reason" style="color: #6b7280; font-size: 0.7rem; font-style: italic;" x-text="'· 🤖 ' + a.reason"></span>
                        </div>
                      </template>
                    </div>
                  </template>
                </div>
              </template>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </section>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

  
  <?php $__currentLoopData = $data['start_events']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ev): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div style="padding: 0.5rem 1rem; font-size: 0.85rem; border: 1px solid #2e3a48; border-left: 3px solid #10b981; border-radius: 0.4rem; color: #10b981; margin-bottom: 1rem;">
      🎰 <?php echo e($ev->message); ?>

    </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

  
  <?php if($data['pages'] > 1): ?>
    <nav style="display: flex; gap: 0.5rem; align-items: center; justify-content: center; margin-top: 1rem; font-size: 0.85rem;">
      <?php if($data['page'] > 1): ?>
        <a href="<?php echo e(route('mtt-simulator.history', $tournament) . '?' . http_build_query($query + ['page' => $data['page'] - 1])); ?>" style="color: #60a5fa;">← Rondas más recientes</a>
      <?php endif; ?>
      <span style="color: #9ca3af;">Página <?php echo e($data['page']); ?> de <?php echo e($data['pages']); ?></span>
      <?php if($data['page'] < $data['pages']): ?>
        <a href="<?php echo e(route('mtt-simulator.history', $tournament) . '?' . http_build_query($query + ['page' => $data['page'] + 1])); ?>" style="color: #60a5fa;">Rondas anteriores →</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>

<style>
  .hist-card { display: inline-block; background: #fff; border-radius: 0.25rem; padding: 0 0.3rem; font-weight: 800; font-size: 0.8rem; margin-right: 0.15rem; min-width: 1.8rem; text-align: center; }
</style>

<script>
  /** Detalle de una mano: se pide al servidor la primera vez que se abre. */
  function handDetail(id, bb) {
    return {
      id, bb, open: false, loading: false, error: null, data: null,

      async toggle() {
        this.open = !this.open;
        if (!this.open || this.data || this.loading) return;
        this.loading = true;
        try {
          const r = await fetch(`/mtt-simulator/hand/${this.id}/history`, { headers: { 'Accept': 'application/json' } });
          const j = await r.json();
          if (!j.success) throw new Error(j.message || 'Error');
          this.data = j.data;
        } catch (e) {
          this.error = e.message;
        } finally {
          this.loading = false;
        }
      },

      fmt(chips) {
        return ((Number(chips) || 0) / (this.bb || 1)).toLocaleString('es-ES', { maximumFractionDigits: 1 }) + 'bb';
      },

      card(c) {
        const s = String(c || '');
        const suit = s.slice(-1);
        const colors = { '♠': '#111827', '♣': '#15803d', '♥': '#dc2626', '♦': '#2563eb' };
        return { value: s.slice(0, -1), suit, color: colors[suit] || '#111827' };
      },

      /** Acciones agrupadas por calle, con las cartas repartidas en cada una */
      streets() {
        const order = ['preflop', 'flop', 'turn', 'river'];
        return order.map(name => {
          const rows = this.data.actions.filter(a => a.street === name);
          const deal = rows.filter(a => a.type === 'deal').flatMap(a => a.cards || []);
          return { name, deal, actions: rows.filter(a => a.type !== 'deal') };
        }).filter(s => s.actions.length || s.deal.length);
      },

      signed(chips) {
        const v = Number(chips) || 0;
        return (v > 0 ? '+' : (v < 0 ? '−' : '')) + this.fmt(Math.abs(v));
      },

      evColor(v) { return { gain: '#34d399', loss: '#f87171', neutral: '#9ca3af' }[v] || '#9ca3af'; },
      evBg(v) { return { gain: 'rgba(52,211,153,0.12)', loss: 'rgba(248,113,113,0.12)', neutral: 'rgba(156,163,175,0.12)' }[v] || 'transparent'; },

      evText(a) {
        if (a.ev_verdict === 'neutral') return '≈ 0 EV';
        return this.signed(a.ev_diff) + ' EV';
      },

      /** Explicación al pasar el ratón por el badge de EV */
      evTitle(a) {
        const eq = Math.round(a.equity * 100), req = Math.round(a.required_equity * 100);
        const best = a.ev_best === 'call' ? 'igualar' : 'retirarse';
        return 'Equity ' + eq + '% · necesaria ' + req + '%\n'
          + 'EV de lo que hizo: ' + this.signed(a.ev_taken) + '\n'
          + 'EV de la alternativa: ' + this.signed(a.ev_alternative) + '\n'
          + 'Mejor opción: ' + best + '\n'
          + '(equity contra las cartas reales de los rivales)';
      },

      text(a) {
        const allin = a.stack_after === 0 ? ' (all-in)' : '';
        switch (a.type) {
          case 'post_sb': return 'pone la ciega pequeña ' + this.fmt(a.amount);
          case 'post_bb': return 'pone la ciega grande ' + this.fmt(a.amount);
          case 'fold': return 'hace fold';
          case 'check': return 'hace check';
          case 'call': return 'iguala ' + this.fmt(a.amount) + allin;
          case 'raise': return (a.to_call > 0 ? 'sube a ' : 'apuesta ') + this.fmt(a.to_amount) + allin;
          case 'allin': return 'va all-in ' + this.fmt(a.to_amount);
          default: return a.type;
        }
      },
    };
  }
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /app/resources/views/mtt-simulator/history.blade.php ENDPATH**/ ?>