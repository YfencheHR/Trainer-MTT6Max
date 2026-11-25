@extends('app')

@section('content')
@php
    $suitColor = ['♠' => '#9ca3af', '♣' => '#4ade80', '♥' => '#f87171', '♦' => '#60a5fa'];
    $cards = fn (array $list) => collect($list)->map(fn ($c) => '<span style="font-weight:700;color:' . ($suitColor[mb_substr($c, -1)] ?? '#e5e7eb') . ';">' . e($c) . '</span>')->implode(' ');
    $text = fn ($a) => $a === null ? __('tournaments.replay_ended') : $a['action'] . ($a['amount'] > 0 && ! in_array($a['action'], ['fold', 'check'], true) ? ' ' . number_format($a['amount'], 0, ',', '.') : '');
    $board = (array) ($hand->actions['board_full'] ?? $hand->board_full ?? []);
    $top = fn ($distribution) => collect((array) $distribution)->sortDesc()->take(3)->map(fn ($p, $a) => $a . ' ' . round($p) . '%')->implode(' · ');
    $winners = fn ($result) => collect($result['winners'] ?? [])->map(fn ($w) => $w['name'] . ' +' . number_format($w['won'], 0, ',', '.'))->implode(', ');
@endphp
<div style="max-width: 1100px; margin: 0 auto; color: #d1d5db;" class="px-4 sm:px-6 lg:px-8 py-6">
  <a href="{{ route('mtt-simulator.history', $hand->tournament) }}" style="font-size: 0.85rem; color: #9ca3af;">← {{ __('tournaments.replay_back') }}</a>
  <h1 style="margin: 0.5rem 0 0.25rem; font-size: 1.5rem; font-weight: 700;">{{ __('tournaments.replay_title') }}</h1>
  <p style="margin: 0 0 1rem; font-size: 0.85rem; color: #9ca3af;">{{ $hand->tournament->title }}{{ $hand->table ? ' · ' . __('tournaments.table') . ' ' . $hand->table->table_number : '' }} · #{{ $hand->hand_number }} · {{ __('tournaments.level') }} {{ $hand->level }}</p>
  <p style="margin: 0 0 1.25rem; font-size: 0.85rem; line-height: 1.5;">{{ __('tournaments.replay_intro') }}</p>

  {{-- La mesa de juego (el mismo componente de la partida) con la mano paso a paso: la jugada o cualquiera de las repeticiones --}}
  <div x-data="replayTable({{ $hand->id }}, @js(route('mtt-simulator.hand.replays.frames', $hand)), 'original')" style="color: #e5e7eb; margin-bottom: 1.25rem;">
    <x-poker-table :tableData="['players' => $players->all()]" :numPlayers="$players->count()" />

    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem 1rem; align-items: center; padding: 0.75rem 1rem; background: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem;">
      <label style="font-size: 0.8rem; color: #9ca3af;">{{ __('tournaments.replay_watching') }}
        <select :value="version" @change="loadVersion($event.target.value)" style="margin-left: 0.35rem; background: #0f1419; color: #e5e7eb; border: 1px solid #374151; border-radius: 0.35rem; padding: 0.3rem 0.5rem;">
          <option value="original">{{ __('tournaments.replay_played_hand') }}</option>
          @foreach ($replays as $r)
            <option value="{{ $r->id }}">{{ __('tournaments.replay_replayed') }} #{{ $r->id }}</option>
          @endforeach
        </select>
      </label>
      <div style="display: flex; gap: 0.4rem; align-items: center;">
        <button type="button" @click="restart()" title="{{ __('tournaments.replay_restart') }}" style="padding: 0.4rem 0.7rem; border: 1px solid #3b4654; border-radius: 0.4rem; background: #202a36; color: #e5e7eb; cursor: pointer;">⏮</button>
        <button type="button" @click="prev()" :disabled="index === 0" style="padding: 0.4rem 0.7rem; border: 1px solid #3b4654; border-radius: 0.4rem; background: #202a36; color: #e5e7eb; cursor: pointer;" :style="{ opacity: index === 0 ? 0.4 : 1 }">◀</button>
        <button type="button" @click="toggle()" style="min-width: 5.5rem; padding: 0.4rem 0.9rem; border: none; border-radius: 0.4rem; background: #059669; color: #fff; font-weight: 700; cursor: pointer;" x-text="playing ? '⏸ {{ __('tournaments.replay_pause') }}' : '▶ {{ __('tournaments.replay_play') }}'"></button>
        <button type="button" @click="next()" :disabled="atEnd()" style="padding: 0.4rem 0.7rem; border: 1px solid #3b4654; border-radius: 0.4rem; background: #202a36; color: #e5e7eb; cursor: pointer;" :style="{ opacity: atEnd() ? 0.4 : 1 }">▶</button>
      </div>
      <label style="font-size: 0.8rem; color: #9ca3af;">{{ __('tournaments.replay_speed') }}
        <select @change="setSpeed($event.target.value)" style="margin-left: 0.35rem; background: #0f1419; color: #e5e7eb; border: 1px solid #374151; border-radius: 0.35rem; padding: 0.3rem 0.5rem;">
          <option value="2600">0.5x</option>
          <option value="1400" selected>1x</option>
          <option value="700">2x</option>
          <option value="350">4x</option>
        </select>
      </label>
      <span style="font-size: 0.8rem; color: #9ca3af;" x-text="frames.length ? ('{{ __('tournaments.replay_step') }} ' + index + ' / ' + (frames.length - 1)) : ''"></span>
      <span x-show="replayLoading" x-cloak style="font-size: 0.8rem; color: #fbbf24;">{{ __('tournaments.replay_loading') }}</span>
      <span x-show="replayError" x-cloak style="font-size: 0.8rem; color: #f87171;" x-text="replayError"></span>
    </div>

    {{-- Lo que acaba de pasar y lo que pensó el bot --}}
    <div style="min-height: 3.2rem; margin-top: 0.5rem; padding: 0.6rem 1rem; background: #151b24; border: 1px solid #232c38; border-radius: 0.5rem; font-size: 0.82rem;">
      <template x-if="decision">
        <div>
          <b x-text="decision.player"></b> · <span style="text-transform: capitalize;" x-text="decision.street"></span> ·
          <b style="color: #fbbf24;" x-text="decision.action + (decision.amount > 0 && decision.action !== 'fold' && decision.action !== 'check' ? ' ' + formatBB(decision.amount) : '')"></b>
          <span x-show="decisionOdds()" style="color: #9ca3af;" x-text="' · ' + decisionOdds()"></span>
          <div x-show="decision.reason" style="margin-top: 0.2rem; font-size: 0.72rem; font-style: italic; color: #6b7280;" x-text="'🤖 ' + decision.reason"></div>
        </div>
      </template>
      <span x-show="!decision" style="color: #6b7280;">{{ __('tournaments.replay_press_play') }}</span>
    </div>
  </div>

  @if (session('status')) <p style="padding: 0.6rem 0.9rem; border-radius: 0.4rem; background: rgba(16,185,129,0.12); color: #34d399; font-size: 0.85rem;">{{ session('status') }}</p> @endif
  @if (session('error')) <p style="padding: 0.6rem 0.9rem; border-radius: 0.4rem; background: rgba(239,68,68,0.12); color: #f87171; font-size: 0.85rem;">{{ session('error') }}</p> @endif

  {{-- La mano jugada --}}
  <section style="background: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem;">
    <h2 style="margin: 0 0 0.6rem; font-size: 1rem; font-weight: 700;">{{ __('tournaments.replay_played_hand') }}</h2>
    <p style="margin: 0 0 0.6rem; font-size: 0.85rem;">{{ __('tournaments.replay_board') }}: {!! $cards($board) !!}</p>
    <table style="width: 100%; border-collapse: collapse; font-size: 0.82rem;">
      @foreach ($players as $p)
        <tr style="border-top: 1px solid #232c38;">
          <td style="padding: 0.3rem 0.4rem; font-weight: 600;">{{ $p['name'] }}</td>
          <td style="padding: 0.3rem 0.4rem;">{!! $cards($p['hole_cards']) !!}</td>
          <td style="padding: 0.3rem 0.4rem; color: #9ca3af;">
            {{ collect($played)->where('player_id', $p['id'])->map(fn ($a) => $a['street'] . ' ' . $text($a))->implode(' · ') }}
          </td>
        </tr>
      @endforeach
    </table>
  </section>

  {{-- Repetir --}}
  <form method="POST" action="{{ route('mtt-simulator.hand.replays.store', $hand) }}" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1.25rem;">
    @csrf
    <label style="font-size: 0.85rem; color: #9ca3af;">{{ __('tournaments.replay_times') }}
      <select name="times" style="margin-left: 0.35rem; background: #0f1419; color: #e5e7eb; border: 1px solid #374151; border-radius: 0.35rem; padding: 0.35rem 0.5rem;">
        @foreach ([1, 5, 10, 20, 50] as $n) <option value="{{ $n }}">{{ $n }}</option> @endforeach
      </select>
    </label>
    <button type="submit" style="padding: 0.5rem 1.1rem; border: none; border-radius: 0.4rem; background: #059669; color: #fff; font-weight: 600; cursor: pointer;">🔁 {{ __('tournaments.replay_run') }}</button>
  </form>

  {{-- Resumen --}}
  @if ($stats['total'] > 0)
    <section style="background: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem;">
      <h2 style="margin: 0 0 0.6rem; font-size: 1rem; font-weight: 700;">{{ __('tournaments.replay_stats') }}</h2>
      <p style="margin: 0 0 0.6rem; font-size: 0.85rem;">
        {{ __('tournaments.replay_total') }}: <b>{{ $stats['total'] }}</b> ·
        {{ __('tournaments.replay_identical') }}: <b>{{ $stats['total'] - $stats['diverged'] }}</b> ·
        {{ __('tournaments.replay_diverged') }}: <b>{{ $stats['diverged'] }}</b> ·
        {{ __('tournaments.replay_same_winner') }}: <b>{{ $stats['same_winner'] }}</b>
      </p>
      <p style="margin: 0 0 0.3rem; font-size: 0.75rem; color: #9ca3af; text-transform: uppercase;">{{ __('tournaments.replay_per_player') }}</p>
      @foreach ($stats['per_player'] as $name => $row)
        <div style="font-size: 0.82rem;">{{ $name }}: <b>{{ __('tournaments.replay_same_n', ['same' => $row['same'], 'total' => $stats['total']]) }}</b></div>
      @endforeach
    </section>
  @endif

  {{-- Repeticiones guardadas --}}
  @forelse ($replays as $replay)
    @php $first = $replay->first_divergence; @endphp
    <details style="background: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; margin-bottom: 0.6rem;">
      <summary style="cursor: pointer; padding: 0.6rem 1rem; font-size: 0.85rem; display: flex; flex-wrap: wrap; gap: 0.4rem 1rem;">
        <b>#{{ $replay->id }}</b>
        <button type="button" style="padding: 0 0.6rem; border: 1px solid #3b4654; border-radius: 0.3rem; background: #202a36; color: #e5e7eb; font-size: 0.75rem; cursor: pointer;"
          onclick="event.preventDefault(); event.stopPropagation(); window.dispatchEvent(new CustomEvent('replay-version', { detail: '{{ $replay->id }}' })); window.scrollTo({ top: 0, behavior: 'smooth' });">▶ {{ __('tournaments.replay_watch') }}</button>
        <span style="color: #9ca3af;">{{ $replay->created_at->format('d/m H:i:s') }}</span>
        @if ($first)
          <span style="color: #fbbf24;">↔ {{ __('tournaments.replay_first_change') }}: {{ $first['street'] }} · {{ $first['player'] }} · {{ $text($first['played']) }} → {{ $text($first['replayed']) }}</span>
        @else
          <span style="color: #34d399;">✓ {{ __('tournaments.replay_no_change') }}</span>
        @endif
        <span style="color: #9ca3af;">{{ __('tournaments.replay_winner') }}: {{ $winners($replay->replay_result) ?: '—' }}{{ $replay->same_winner ? '' : ' (' . $winners($replay->original_result) . ')' }}</span>
      </summary>
      <div style="padding: 0.5rem 1rem 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.8rem;">
        @foreach (['original' => 'replay_played', 'replay' => 'replay_replayed'] as $side => $label)
          <div>
            <div style="font-size: 0.72rem; font-weight: 700; color: #60a5fa; text-transform: uppercase; margin-bottom: 0.3rem;">{{ __('tournaments.' . $label) }}</div>
            @foreach ($replay->actions->where('side', $side) as $a)
              @php $changed = $first && $a->seq >= $first['index']; @endphp
              <div style="padding: 0.1rem 0 0.1rem 0.6rem; border-left: 2px solid {{ $changed ? '#f59e0b' : '#2e3a48' }};">
                {{ $a->street }} · <b>{{ $a->player_name }}</b> {{ $text(['action' => $a->action, 'amount' => $a->amount]) }}
                @if ($a->distribution) <span style="color: #6b7280; font-size: 0.7rem;">· {{ $top($a->distribution) }}</span> @endif
              </div>
            @endforeach
          </div>
        @endforeach
      </div>
    </details>
  @empty
    <p style="color: #6b7280;">{{ __('tournaments.replay_none') }}</p>
  @endforelse
</div>
@endsection
