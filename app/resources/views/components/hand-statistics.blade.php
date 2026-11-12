@props([])

@php
  $streetLabels = [
      'preflop' => __('tournaments.preflop'),
      'flop' => __('tournaments.flop'),
      'turn' => __('tournaments.turn'),
      'river' => __('tournaments.river'),
      'showdown' => __('tournaments.showdown'),
      'completed' => __('tournaments.completed_hand'),
  ];
@endphp

<div x-data='{
    get hand() { return $store.handStatistics.data || {}; },
    get tournament() { return $store.tournamentInfo.data || {}; },
    get hero() { return (this.hand.players || []).find(player => player.is_current) || null; },
    get table() { return (this.tournament.tables || []).find(table => table.is_hero) || null; },
    get tablePlayers() { return this.hand.players || []; },
    get tableAverageStack() {
      if (!this.tablePlayers.length) return Number(this.table?.avg_stack) || 0;
      return this.tablePlayers.reduce((sum, player) => sum + (Number(player.stack) || 0), 0) / this.tablePlayers.length;
    },
    get playerStack() { return this.hero?.stack ?? this.tournament.hero_stack ?? 0; },
    formatBB(value) {
      const bigBlind = Number(this.hand.big_blind || this.tournament.big_blind) || 1;
      return ((Number(value) || 0) / bigBlind).toLocaleString(@json(app()->getLocale()), { maximumFractionDigits: 1 }) + "bb";
    },
    formatPercent(value) { return value === null || value === undefined ? "—" : value + "%"; },
    streetLabels: @json($streetLabels),
    streetLabel(street) { return this.streetLabels[street] || street || "—"; }
  }'
  style="background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 1rem;">
  <p style="color: #d1d5db; font-weight: 600; margin: 0 0 1rem; font-size: 0.875rem; text-transform: uppercase;">
    📈 {{ __('tournaments.hand_statistics') }}
  </p>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 0.75rem;">
    <section style="background: #0f1419; border: 1px solid #2e3a48; border-radius: 0.4rem; padding: 0.75rem;">
      <p style="color: #d1d5db; font-size: 0.75rem; font-weight: 700; margin: 0 0 0.65rem; text-transform: uppercase;">
        {{ __('tournaments.player_statistics') }}
      </p>
      <template x-if="hero">
        <div>
          <div style="display: flex; justify-content: space-between; gap: 0.5rem; align-items: baseline; margin-bottom: 0.6rem;">
            <span style="color: #f3f4f6; font-size: 0.8rem; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="hero.name"></span>
            <span style="color: #60a5fa; font-size: 0.68rem; font-weight: 700;" x-text="hero.position"></span>
          </div>
          <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.4rem;">
            <div style="background: rgba(0,0,0,0.25); border-radius: 0.3rem; padding: 0.4rem;">
              <p style="color: #9ca3af; font-size: 0.62rem; margin: 0;">{{ __('tournaments.current_stack') }}</p>
              <p style="color: #60a5fa; font-size: 0.9rem; font-weight: 700; margin: 0.1rem 0 0;" x-text="formatBB(hero.stack)"></p>
            </div>
            <div style="background: rgba(0,0,0,0.25); border-radius: 0.3rem; padding: 0.4rem;">
              <p style="color: #9ca3af; font-size: 0.62rem; margin: 0;">{{ __('tournaments.hand_contribution') }}</p>
              <p style="color: #fbbf24; font-size: 0.9rem; font-weight: 700; margin: 0.1rem 0 0;" x-text="formatBB(hero.bet)"></p>
            </div>
            <div style="background: rgba(0,0,0,0.25); border-radius: 0.3rem; padding: 0.4rem;">
              <p style="color: #9ca3af; font-size: 0.62rem; margin: 0;">VPIP / PFR</p>
              <p style="color: #a78bfa; font-size: 0.85rem; font-weight: 700; margin: 0.1rem 0 0;" x-text="formatPercent(hero.stats?.vpip) + ' / ' + formatPercent(hero.stats?.pfr)"></p>
            </div>
            <div style="background: rgba(0,0,0,0.25); border-radius: 0.3rem; padding: 0.4rem;">
              <p style="color: #9ca3af; font-size: 0.62rem; margin: 0;">{{ __('tournaments.three_bet') }}</p>
              <p style="color: #a78bfa; font-size: 0.85rem; font-weight: 700; margin: 0.1rem 0 0;" x-text="formatPercent(hero.stats?.three_bet)"></p>
            </div>
          </div>
          <div style="display: flex; justify-content: space-between; gap: 0.5rem; margin-top: 0.5rem; color: #9ca3af; font-size: 0.68rem;">
            <span>{{ __('tournaments.sample_hands') }}</span>
            <span x-text="hero.stats?.hands ?? 0"></span>
          </div>
          <p style="color: #10b981; font-size: 0.7rem; font-weight: 600; margin: 0.5rem 0 0;"
            x-text="hero.is_all_in ? @js(__('tournaments.all_in')) : (hero.is_folded ? @js(__('tournaments.folded')) : (hand.status === 'completed' ? @js(__('tournaments.completed_hand')) : (hero.is_turn ? @js(__('tournaments.your_turn')) : @js(__('tournaments.in_hand')))))"></p>
        </div>
      </template>
      <p x-show="!hero" style="color: #9ca3af; font-size: 0.72rem; margin: 0;">{{ __('tournaments.not_seated_at_table') }}</p>
    </section>

    <section style="background: #0f1419; border: 1px solid #2e3a48; border-radius: 0.4rem; padding: 0.75rem;">
      <p style="color: #d1d5db; font-size: 0.75rem; font-weight: 700; margin: 0 0 0.65rem; text-transform: uppercase;">
        {{ __('tournaments.table_statistics') }}
      </p>
      <template x-if="table">
        <div>
          <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.4rem;">
            <div style="background: rgba(0,0,0,0.25); border-radius: 0.3rem; padding: 0.4rem;">
              <p style="color: #9ca3af; font-size: 0.62rem; margin: 0;">{{ __('tournaments.table') }}</p>
              <p style="color: #d1d5db; font-size: 0.9rem; font-weight: 700; margin: 0.1rem 0 0;" x-text="table.number"></p>
            </div>
            <div style="background: rgba(0,0,0,0.25); border-radius: 0.3rem; padding: 0.4rem;">
              <p style="color: #9ca3af; font-size: 0.62rem; margin: 0;">{{ __('tournaments.players_at_table') }}</p>
              <p style="color: #d1d5db; font-size: 0.9rem; font-weight: 700; margin: 0.1rem 0 0;" x-text="tablePlayers.length"></p>
            </div>
            <div style="background: rgba(0,0,0,0.25); border-radius: 0.3rem; padding: 0.4rem;">
              <p style="color: #9ca3af; font-size: 0.62rem; margin: 0;">{{ __('tournaments.average_stack') }}</p>
              <p style="color: #a78bfa; font-size: 0.9rem; font-weight: 700; margin: 0.1rem 0 0;" x-text="formatBB(tableAverageStack)"></p>
            </div>
            <div style="background: rgba(0,0,0,0.25); border-radius: 0.3rem; padding: 0.4rem;">
              <p style="color: #9ca3af; font-size: 0.62rem; margin: 0;">{{ __('tournaments.current_pot') }}</p>
              <p style="color: #06b6d4; font-size: 0.9rem; font-weight: 700; margin: 0.1rem 0 0;" x-text="formatBB(hand.pot)"></p>
            </div>
          </div>
          <div style="display: flex; justify-content: space-between; gap: 0.5rem; margin: 0.5rem 0; color: #9ca3af; font-size: 0.68rem;">
            <span>{{ __('tournaments.current_hand') }} <span x-text="'#' + (hand.hand_number ?? '—')"></span></span>
            <span x-text="streetLabel(hand.current_street)"></span>
          </div>
          <div style="max-height: 120px; overflow-y: auto; border-top: 1px solid #2e3a48; padding-top: 0.35rem;">
            <template x-for="player in tablePlayers" :key="player.id">
              <div style="display: flex; justify-content: space-between; gap: 0.5rem; color: #d1d5db; font-size: 0.7rem; line-height: 1.5;">
                <span style="min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="player.name + (player.is_current ? ' · ' + @js(__('tournaments.you')) : '')"></span>
                <span style="flex-shrink: 0; color: #a78bfa;" x-text="formatBB(player.stack)"></span>
              </div>
            </template>
          </div>
        </div>
      </template>
      <p x-show="!table" style="color: #9ca3af; font-size: 0.72rem; margin: 0;">{{ __('tournaments.no_table_information') }}</p>
    </section>

    <section style="background: #0f1419; border: 1px solid #2e3a48; border-radius: 0.4rem; padding: 0.75rem;">
      <p style="color: #d1d5db; font-size: 0.75rem; font-weight: 700; margin: 0 0 0.65rem; text-transform: uppercase;">
        {{ __('tournaments.tournament_statistics') }}
      </p>
      <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.4rem;">
        <div style="background: rgba(0,0,0,0.25); border-radius: 0.3rem; padding: 0.4rem;">
          <p style="color: #9ca3af; font-size: 0.62rem; margin: 0;">{{ __('tournaments.level') }} / {{ __('tournaments.blinds') }}</p>
          <p style="color: #06b6d4; font-size: 0.82rem; font-weight: 700; margin: 0.1rem 0 0;" x-text="(tournament.level ?? '—') + ' · ' + (tournament.small_blind ?? '—') + '/' + (tournament.big_blind ?? '—')"></p>
        </div>
        <div style="background: rgba(0,0,0,0.25); border-radius: 0.3rem; padding: 0.4rem;">
          <p style="color: #9ca3af; font-size: 0.62rem; margin: 0;">{{ __('tournaments.tournament_position') }}</p>
          <p style="color: #fbbf24; font-size: 0.9rem; font-weight: 700; margin: 0.1rem 0 0;" x-text="tournament.hero_rank || '—'"></p>
        </div>
        <div style="background: rgba(0,0,0,0.25); border-radius: 0.3rem; padding: 0.4rem;">
          <p style="color: #9ca3af; font-size: 0.62rem; margin: 0;">{{ __('tournaments.players_remaining') }}</p>
          <p style="color: #d1d5db; font-size: 0.9rem; font-weight: 700; margin: 0.1rem 0 0;" x-text="(tournament.players_alive ?? '—') + '/' + (tournament.players_total ?? '—')"></p>
        </div>
        <div style="background: rgba(0,0,0,0.25); border-radius: 0.3rem; padding: 0.4rem;">
          <p style="color: #9ca3af; font-size: 0.62rem; margin: 0;">{{ __('tournaments.my_tournament_stack') }}</p>
          <p style="color: #60a5fa; font-size: 0.9rem; font-weight: 700; margin: 0.1rem 0 0;" x-text="formatBB(playerStack)"></p>
        </div>
        <div style="background: rgba(0,0,0,0.25); border-radius: 0.3rem; padding: 0.4rem;">
          <p style="color: #9ca3af; font-size: 0.62rem; margin: 0;">{{ __('tournaments.average_stack') }}</p>
          <p style="color: #a78bfa; font-size: 0.9rem; font-weight: 700; margin: 0.1rem 0 0;" x-text="formatBB(tournament.avg_stack)"></p>
        </div>
        <div style="background: rgba(0,0,0,0.25); border-radius: 0.3rem; padding: 0.4rem;">
          <p style="color: #9ca3af; font-size: 0.62rem; margin: 0;">{{ __('tournaments.chip_leader') }}</p>
          <p style="color: #a78bfa; font-size: 0.9rem; font-weight: 700; margin: 0.1rem 0 0;" x-text="formatBB(tournament.max_stack)"></p>
        </div>
      </div>
    </section>
  </div>
</div>
