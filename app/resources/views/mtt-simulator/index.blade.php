@extends('app')

@section('content')
  <div style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-4xl font-bold" style="color: #d1d5db;">{{ __('tournaments.title') }}</h1>
        <p style="color: #a1a5b0;" class="text-lg mt-2">{{ __('tournaments.description') }}</p>
      </div>
      <a href="{{ route('mtt-simulator.create') }}" style="background-color: #047857; color: white;" class="px-6 py-3 rounded font-semibold hover:opacity-80 transition">
        {{ __('tournaments.create_new') }}
      </a>
    </div>

    @if (session('success'))
      <div class="mb-6 p-4 rounded" style="background-color: #1e3a1f; color: #10b981;">
        {{ session('success') }}
      </div>
    @endif

    <!-- Torneos Activos -->
    <div class="mb-12">
      <h2 class="text-2xl font-bold mb-4" style="color: #d1d5db;">{{ __('tournaments.active') }}</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($tournaments->where('status', 'in_progress') as $tournament)
          <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 hover:border-blue-500 transition flex flex-col">
            <div class="mb-4">
              <h3 style="color: #10b981;" class="text-lg font-bold">{{ $tournament->title }}</h3>
              <p style="color: #9ca3af;" class="text-sm mt-1">{{ $tournament->getConfig('basic_settings.num_players') }} {{ __('tournaments.players') }}</p>
              <p style="color: #9ca3af;" class="text-sm">{{ __('tournaments.level') }}: {{ $tournament->current_level }}</p>
            </div>
            <div class="mb-4">
              <p style="color: #a78bfa;">{{ $tournament->hand_count }} {{ __('tournaments.hands_played') }}</p>
            </div>
            <div class="flex gap-3">
              <a href="{{ route('mtt-simulator.play', $tournament) }}" style="background-color: #047857; color: white;" class="flex-1 px-4 py-2 rounded text-center font-semibold hover:opacity-80 transition">
                {{ __('tournaments.continue') }}
              </a>
              <a href="{{ route('mtt-simulator.show', $tournament) }}" style="background-color: #2563eb; color: white;" class="flex-1 px-4 py-2 rounded text-center font-semibold hover:opacity-80 transition">
                {{ __('tournaments.view') }}
              </a>
            </div>
          </div>
        @empty
          <div class="col-span-full text-center text-slate-400 py-12">
            {{ __('tournaments.no_active') }}
          </div>
        @endforelse
      </div>
    </div>

    <!-- Torneos Completados -->
    <div>
      <h2 class="text-2xl font-bold mb-4" style="color: #d1d5db;">{{ __('tournaments.completed') }}</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($tournaments->where('status', 'completed') as $tournament)
          <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 hover:border-purple-500 transition flex flex-col">
            <div class="mb-4">
              <h3 style="color: #d8b4fe;" class="text-lg font-bold">{{ $tournament->title }}</h3>
              <p style="color: #9ca3af;" class="text-sm mt-1">{{ $tournament->getConfig('basic_settings.num_players') }} {{ __('tournaments.players') }}</p>
              <p style="color: #9ca3af;" class="text-sm">{{ $tournament->ended_at?->format('d/m/Y H:i') }}</p>
            </div>
            <div class="mb-4">
              <p style="color: #a78bfa;">{{ $tournament->hand_count }} {{ __('tournaments.hands_played') }}</p>
            </div>
            <a href="{{ route('mtt-simulator.show', $tournament) }}" style="background-color: #9333ea; color: white;" class="px-4 py-2 rounded text-center font-semibold hover:opacity-80 transition">
              {{ __('tournaments.view_details') }}
            </a>
          </div>
        @empty
          <div class="col-span-full text-center text-slate-400 py-12">
            {{ __('tournaments.no_completed') }}
          </div>
        @endforelse
      </div>
    </div>
  </div>
@endsection
