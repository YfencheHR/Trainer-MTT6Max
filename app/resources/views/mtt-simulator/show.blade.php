@extends('app')

@section('content')
  <div style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-bold" style="color: #d1d5db;">{{ $tournament->title }}</h1>
        <div class="flex gap-6 mt-4 text-sm" style="color: #a1a5b0;">
          <span>{{ __('tournaments.status') }}: <span style="color: {{ $tournament->status === 'completed' ? '#10b981' : ($tournament->status === 'in_progress' ? '#06b6d4' : '#f59e0b') }};">{{ ucfirst($tournament->status) }}</span></span>
          <span>{{ __('tournaments.created') }}: {{ $tournament->created_at->format('d/m/Y H:i') }}</span>
          @if ($tournament->ended_at)
            <span>{{ __('tournaments.ended') }}: {{ $tournament->ended_at->format('d/m/Y H:i') }}</span>
          @endif
        </div>
      </div>
      <div style="display: flex; gap: 0.75rem;">
        @if ($tournament->status === 'pending')
          <form method="POST" action="{{ route('mtt-simulator.start', $tournament) }}" style="display: inline;">
            @csrf
            <button type="submit" style="background-color: #047857; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; border: none; cursor: pointer;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
              🎮 Iniciar Torneo
            </button>
          </form>
        @elseif ($tournament->status === 'in_progress')
          <a href="{{ route('mtt-simulator.play', $tournament) }}" style="background-color: #047857; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; text-decoration: none; display: inline-block;">
            ▶️ Jugar
          </a>
        @endif

        @if ($tournament->status === 'in_progress')
          <form method="POST" action="{{ route('mtt-simulator.end', $tournament) }}" style="display: inline;">
            @csrf
            <button type="submit" style="background-color: #ef4444; color: white;" class="px-6 py-3 rounded font-semibold hover:opacity-80 transition" onclick="return confirm('¿Estás seguro de que quieres terminar este torneo?')">
              {{ __('tournaments.end_tournament') }}
            </button>
          </form>
        @endif
      </div>
    </div>

    <!-- Configuración del Torneo -->
    <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 mb-8">
      <h2 class="text-xl font-bold mb-4" style="color: #d1d5db;">{{ __('tournaments.config') }}</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <div>
          <p style="color: #9ca3af;" class="text-sm">{{ __('tournaments.starting_stack') }}</p>
          <p style="color: #10b981;" class="text-lg font-bold">{{ $tournament->getConfig('basic_settings.starting_stack') }}</p>
        </div>
        <div>
          <p style="color: #9ca3af;" class="text-sm">{{ __('tournaments.buy_in') }}</p>
          <p style="color: #10b981;" class="text-lg font-bold">{{ $tournament->getConfig('basic_settings.buy_in') }}€</p>
        </div>
        <div>
          <p style="color: #9ca3af;" class="text-sm">{{ __('tournaments.num_players') }}</p>
          <p style="color: #10b981;" class="text-lg font-bold">{{ $tournament->getConfig('basic_settings.num_players') }}</p>
        </div>
        <div>
          <p style="color: #9ca3af;" class="text-sm">{{ __('tournaments.speed') }}</p>
          <p style="color: #10b981;" class="text-lg font-bold">{{ ucfirst($tournament->getConfig('blind_structure.speed')) }}</p>
        </div>
        <div>
          <p style="color: #9ca3af;" class="text-sm">{{ __('tournaments.hands_per_level') }}</p>
          <p style="color: #10b981;" class="text-lg font-bold">{{ $tournament->getConfig('blind_structure.hands_per_level') ?? 10 }}</p>
        </div>
      </div>
    </div>

    <!-- Estadísticas -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6">
        <p style="color: #9ca3af;" class="text-sm mb-2">{{ __('tournaments.total_hands') }}</p>
        <p style="color: #a78bfa;" class="text-3xl font-bold">{{ $tournament->hand_count }}</p>
      </div>
      <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6">
        <p style="color: #9ca3af;" class="text-sm mb-2">{{ __('tournaments.hands_won') }}</p>
        <p style="color: #10b981;" class="text-3xl font-bold">{{ $tournament->hands()->where('result', 'win')->count() }}</p>
      </div>
      <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6">
        <p style="color: #9ca3af;" class="text-sm mb-2">{{ __('tournaments.hands_lost') }}</p>
        <p style="color: #ef4444;" class="text-3xl font-bold">{{ $tournament->hands()->where('result', 'lose')->count() }}</p>
      </div>
    </div>

    <!-- Historial de Manos -->
    <div>
      <h2 class="text-2xl font-bold mb-4" style="color: #d1d5db;">{{ __('tournaments.hands_history') }}</h2>
      <div class="space-y-3">
        @forelse ($tournament->hands()->latest()->get() as $hand)
          <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-4 hover:border-blue-500 transition cursor-pointer" onclick="document.getElementById('hand-{{ $hand->id }}').scrollIntoView({ behavior: 'smooth' })">
            <div class="flex items-center justify-between">
              <div>
                <p style="color: #ffffff;" class="font-semibold">#{{ $hand->hand_number }} - {{ __('tournaments.level') }} {{ $hand->level }}</p>
                <p style="color: #9ca3af;" class="text-sm mt-1">
                  @if (is_array($hand->hero_cards) && count($hand->hero_cards) >= 2)
                    {{ __('tournaments.hero_cards') }}: <span style="color: #10b981;">{{ $hand->hero_cards[0] }} {{ $hand->hero_cards[1] }}</span>
                  @endif
                  @if ($hand->community_cards && is_array($hand->community_cards) && count($hand->community_cards) > 0)
                    | {{ __('tournaments.community') }}: <span style="color: #60a5fa;">{{ implode(' ', $hand->community_cards) }}</span>
                  @endif
                </p>
              </div>
              <div class="text-right">
                <p style="color: #a78bfa;" class="font-bold">{{ $hand->getTotalPot() }}€</p>
                <p style="color: #{{ $hand->result === 'win' ? '10b981' : ($hand->result === 'fold' ? '6b7280' : 'ef4444') }};" class="text-sm font-semibold">
                  {{ ucfirst($hand->result) }} @if ($hand->getHeroStackChange() > 0)
                    <span style="color: #10b981;">+{{ $hand->getHeroStackChange() }}</span>
                  @elseif ($hand->getHeroStackChange() < 0)
                    <span style="color: #ef4444;">{{ $hand->getHeroStackChange() }}</span>
                  @endif
                </p>
              </div>
            </div>
          </div>
        @empty
          <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-8 text-center" style="color: #9ca3af;">
            {{ __('tournaments.no_hands') }}
          </div>
        @endforelse
      </div>
    </div>
  </div>
@endsection
