@props(['tournament' => null])

{{-- Panel compacto del torneo. Se alimenta del store 'tournamentInfo' que actualiza la mesa. --}}
<div x-data="{
    get t() { return $store.tournamentInfo.data; },
    bb(v) { const b = this.t.big_blind || 1; return ((Number(v) || 0) / b).toLocaleString('es-ES', { maximumFractionDigits: 1 }); },
  }"
  style="background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 0.75rem;">

  <p style="color: #d1d5db; font-weight: 600; margin: 0 0 0.5rem; font-size: 0.8rem; text-transform: uppercase;">
    📊 {{ __('tournaments.tournament_info') }}
  </p>

  <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.4rem;">
    @php
        $cell = 'background: rgba(0,0,0,0.25); border-radius: 0.35rem; padding: 0.35rem 0.4rem; min-width: 0;';
        $lab = 'margin: 0; font-size: 0.58rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.03em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;';
        $val = 'margin: 0; font-size: 0.95rem; font-weight: 700; line-height: 1.25; white-space: nowrap;';
    @endphp

    <div style="{{ $cell }}"><p style="{{ $lab }}">Nivel</p><p style="{{ $val }} color: #10b981;" x-text="t.level ?? '–'"></p></div>
    <div style="{{ $cell }}"><p style="{{ $lab }}">Ciegas</p><p style="{{ $val }} color: #06b6d4;" x-text="t.big_blind ? (t.small_blind + '/' + t.big_blind) : '–'"></p></div>
    <div style="{{ $cell }}"><p style="{{ $lab }}">Mano</p><p style="{{ $val }} color: #f59e0b;" x-text="'#' + (t.hand_number ?? '–')"></p></div>

    <div style="{{ $cell }}"><p style="{{ $lab }}">Quedan</p><p style="{{ $val }} color: #60a5fa;" x-text="(t.players_alive ?? '–') + '/' + (t.players_total ?? '–')"></p></div>
    <div style="{{ $cell }}"><p style="{{ $lab }}">Mi puesto</p><p style="{{ $val }} color: #e5e7eb;" x-text="t.hero_rank ? (t.hero_rank + 'º') : '–'"></p></div>
    <div style="{{ $cell }}"><p style="{{ $lab }}">Mi stack</p><p style="{{ $val }} color: #60a5fa;" x-text="bb(t.hero_stack) + 'bb'"></p></div>

    <div style="{{ $cell }}"><p style="{{ $lab }}">Medio</p><p style="{{ $val }} color: #a78bfa;" x-text="bb(t.avg_stack) + 'bb'"></p></div>
    <div style="{{ $cell }}"><p style="{{ $lab }}">Mayor</p><p style="{{ $val }} color: #a78bfa;" x-text="bb(t.max_stack) + 'bb'"></p></div>
    <div style="{{ $cell }}"><p style="{{ $lab }}">Mi M</p><p style="{{ $val }} color: #e5e7eb;" x-text="t.big_blind ? ((t.hero_stack / (t.small_blind + t.big_blind)).toLocaleString('es-ES', { maximumFractionDigits: 1 })) : '–'"></p></div>
  </div>
</div>
