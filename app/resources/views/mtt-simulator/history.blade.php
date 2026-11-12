@extends('app')

@section('content')
@php
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
@endphp

<div style="max-width: 1200px; margin: 0 auto; color: #d1d5db;" class="px-4 sm:px-6 lg:px-8 py-6">

  {{-- Cabecera --}}
  <div style="display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: flex-end; margin-bottom: 1.25rem;">
    <div>
      <p style="margin: 0; font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Historial del torneo</p>
      <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700;">{{ $tournament->title }}</h1>
      <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: #9ca3af;">
        {{ $data['total_rounds'] }} rondas jugadas · {{ $tournament->players()->count() }} jugadores · estado: {{ $tournament->status }}
      </p>
    </div>
    <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
      @if ($tournament->status === 'in_progress')
        <a href="{{ route('mtt-simulator.play', $tournament) }}" style="padding: 0.55rem 1rem; border-radius: 0.4rem; background: #059669; color: #fff; font-weight: 600; font-size: 0.85rem; text-decoration: none;">← Volver a la mesa</a>
      @else
        <a href="{{ route('mtt-simulator.show', $tournament) }}" style="padding: 0.55rem 1rem; border-radius: 0.4rem; background: #374151; color: #fff; font-weight: 600; font-size: 0.85rem; text-decoration: none;">← Torneo</a>
      @endif
      <a href="{{ route('mtt-simulator.history.export', $tournament) }}" style="padding: 0.55rem 1rem; border-radius: 0.4rem; background: #1d4ed8; color: #fff; font-weight: 600; font-size: 0.85rem; text-decoration: none;" title="Todo lo grabado: asientos, eventos, manos, acciones y contexto de cada decisión de bot">⬇ Exportar JSON</a>
    </div>
  </div>

  {{-- Filtros --}}
  <form method="GET" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; background: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 1.25rem;">
    <label style="font-size: 0.8rem; color: #9ca3af;">Mesa
      <select name="table" style="margin-left: 0.35rem; background: #0f1419; color: #e5e7eb; border: 1px solid #374151; border-radius: 0.35rem; padding: 0.3rem 0.5rem;">
        <option value="">Todas</option>
        @foreach ($tables as $t)
          <option value="{{ $t->id }}" @selected(($filters['table'] ?? null) === $t->id)>Mesa {{ $t->table_number }}{{ $t->is_active ? '' : ' (rota)' }}</option>
        @endforeach
      </select>
    </label>
    <label style="font-size: 0.8rem; color: #9ca3af;">Jugador
      <select name="player" style="margin-left: 0.35rem; background: #0f1419; color: #e5e7eb; border: 1px solid #374151; border-radius: 0.35rem; padding: 0.3rem 0.5rem;">
        <option value="">Todos</option>
        @foreach ($players as $p)
          <option value="{{ $p->id }}" @selected(($filters['player'] ?? null) === $p->id)>{{ $p->name }}</option>
        @endforeach
      </select>
    </label>
    <label style="font-size: 0.8rem; color: #9ca3af; display: flex; align-items: center; gap: 0.35rem;">
      <input type="checkbox" name="hero" value="1" @checked($filters['hero'])> Solo mis manos
    </label>
    <button type="submit" style="padding: 0.4rem 0.9rem; border: none; border-radius: 0.35rem; background: #374151; color: #fff; font-size: 0.8rem; cursor: pointer;">Filtrar</button>
    @if ($filters['table'] || $filters['hero'] || $filters['player'])
      <a href="{{ route('mtt-simulator.history', $tournament) }}" style="font-size: 0.8rem; color: #9ca3af;">Quitar filtros</a>
    @endif
  </form>

  @if (empty($data['rounds']))
    <p style="color: #6b7280;">Todavía no hay manos terminadas con estos filtros.</p>
  @endif

  {{-- Rondas --}}
  @foreach ($data['rounds'] as $round)
    <section style="background: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; margin-bottom: 1rem; overflow: hidden;">
      <header style="display: flex; justify-content: space-between; align-items: baseline; padding: 0.6rem 1rem; background: #151b24; border-bottom: 1px solid #2e3a48;">
        <strong style="font-size: 0.95rem;">Ronda {{ $round['round'] }}</strong>
        <span style="font-size: 0.78rem; color: #9ca3af;">
          @if ($round['level']) Nivel {{ $round['level'] }} · ciegas {{ $round['small_blind'] }}/{{ $round['big_blind'] }} @endif
        </span>
      </header>

      @foreach ($round['events'] as $ev)
        @php [$color, $icon] = $eventStyle[$ev->type] ?? ['#9ca3af', '•']; @endphp
        <div style="padding: 0.35rem 1rem; font-size: 0.8rem; border-bottom: 1px solid #232c38; border-left: 3px solid {{ $color }}; color: {{ $color }};">
          {{ $icon }} {{ $ev->message }}
        </div>
      @endforeach

      @foreach ($round['hands'] as $h)
        <div x-data="handDetail({{ $h['id'] }}, {{ $h['bb'] }})" style="border-bottom: 1px solid #232c38;">
          <button type="button" @click="toggle()" style="width: 100%; text-align: left; background: transparent; border: none; color: inherit; padding: 0.6rem 1rem; cursor: pointer; display: flex; flex-wrap: wrap; gap: 0.5rem 1rem; align-items: center;">
            <span style="background: #2b3441; border-radius: 0.3rem; padding: 0.1rem 0.5rem; font-size: 0.75rem; font-weight: 700;">
              {{ $h['table_number'] ? 'Mesa ' . $h['table_number'] : 'Mano #' . $h['id'] }}
            </span>

            @if ($h['structured'])
              <span style="font-size: 0.8rem; color: #9ca3af;">{{ $h['players'] }} jugadores</span>
              <span style="font-size: 0.85rem; color: #fbbf24; font-weight: 600;">
                🏆 {{ $h['winner'] }} +{{ $bbFmt($h['winner_won'], $h['bb']) }}
                @if ($h['winner_hand']) <span style="font-weight: 400; color: #d1d5db;">· {{ $h['winner_hand'] }}</span> @endif
              </span>
              <span style="font-size: 0.78rem; color: #9ca3af;">bote {{ $bbFmt($h['pot'], $h['bb']) }}{{ $h['showdown'] ? ' · showdown' : '' }}</span>
              @if ($h['hero'])
                <span style="margin-left: auto; font-size: 0.8rem; font-weight: 700; color: {{ $h['hero']['net'] > 0 ? '#34d399' : ($h['hero']['net'] < 0 ? '#f87171' : '#9ca3af') }};">
                  Tú ({{ $h['hero']['position'] }}) {{ $h['hero']['net'] > 0 ? '+' : '' }}{{ $bbFmt($h['hero']['net'], $h['bb']) }}
                  @if ($h['hero']['folded_street']) · fold {{ $h['hero']['folded_street'] }} @endif
                </span>
              @endif
            @else
              <span style="font-size: 0.8rem; color: #6b7280;">Mano anterior al historial detallado</span>
            @endif
          </button>

          @if ($h['structured'])
            <div x-show="open" x-cloak style="padding: 0.5rem 1rem 1rem; background: #121820;">
              <p x-show="loading" style="margin: 0; color: #9ca3af; font-size: 0.85rem;">Cargando…</p>
              <p x-show="error" x-text="error" style="margin: 0; color: #f87171; font-size: 0.85rem;"></p>

              <template x-if="data">
                <div>
                  {{-- Board y botes --}}
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

                  {{-- Jugadores --}}
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

                  {{-- Acciones por calle --}}
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
          @endif
        </div>
      @endforeach
    </section>
  @endforeach

  {{-- Inicio del torneo (última página) --}}
  @foreach ($data['start_events'] as $ev)
    <div style="padding: 0.5rem 1rem; font-size: 0.85rem; border: 1px solid #2e3a48; border-left: 3px solid #10b981; border-radius: 0.4rem; color: #10b981; margin-bottom: 1rem;">
      🎰 {{ $ev->message }}
    </div>
  @endforeach

  {{-- Paginación --}}
  @if ($data['pages'] > 1)
    <nav style="display: flex; gap: 0.5rem; align-items: center; justify-content: center; margin-top: 1rem; font-size: 0.85rem;">
      @if ($data['page'] > 1)
        <a href="{{ route('mtt-simulator.history', $tournament) . '?' . http_build_query($query + ['page' => $data['page'] - 1]) }}" style="color: #60a5fa;">← Rondas más recientes</a>
      @endif
      <span style="color: #9ca3af;">Página {{ $data['page'] }} de {{ $data['pages'] }}</span>
      @if ($data['page'] < $data['pages'])
        <a href="{{ route('mtt-simulator.history', $tournament) . '?' . http_build_query($query + ['page' => $data['page'] + 1]) }}" style="color: #60a5fa;">Rondas anteriores →</a>
      @endif
    </nav>
  @endif
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
@endsection
