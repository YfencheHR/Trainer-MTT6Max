@extends('app')

@section('content')
  <main style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-end mb-8">
      <a href="{{ route('bots.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">{{ __('bots.new_bot_button') }}</a>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
      <div style="background-color: #1a212a;" class="p-4 rounded border border-slate-700">
        <p class="text-slate-400 text-sm">{{ __('bots.total_bots') }}</p>
        <p class="text-3xl font-bold text-white">{{ count($bots) }}</p>
      </div>
      <div style="background-color: #1a212a;" class="p-4 rounded border border-slate-700">
        <p class="text-slate-400 text-sm">{{ __('bots.professional') }}</p>
        <p class="text-3xl font-bold text-white">{{ $bots->where('profesional', true)->count() }}</p>
      </div>
      <div style="background-color: #1a212a;" class="p-4 rounded border border-slate-700">
        <p class="text-slate-400 text-sm">{{ __('bots.recreational') }}</p>
        <p class="text-3xl font-bold text-white">{{ $bots->where('profesional', false)->count() }}</p>
      </div>
      <div style="background-color: #1a212a;" class="p-4 rounded border border-slate-700">
        <p class="text-slate-400 text-sm">{{ __('bots.avg_vpip') }}</p>
        <p class="text-3xl font-bold text-white">{{ round($bots->avg('vpip') ?? 0) }}%</p>
      </div>
    </div>

    {{-- Primero los del Pool (datos reales de PokerTracker), después los primarios (arquetipos de referencia) --}}
    @php
      $secciones = [
        ['titulo' => 'Pool', 'nota' => 'Perfiles que se actualizan con jugadores reales de PokerTracker: una copia por cada primario y los perfiles nuevos que no encajan en ninguno.', 'bots' => $pool],
        ['titulo' => 'Perfiles primarios', 'nota' => 'Los arquetipos de referencia: la importación no los modifica.', 'bots' => $primarios],
      ];
    @endphp

    @forelse($bots->isEmpty() ? [] : $secciones as $sec)
      @if(count($sec['bots']))
        <section class="mb-10">
          <div class="flex flex-wrap items-baseline gap-3 mb-1">
            <h2 class="text-2xl font-bold text-white">{{ $sec['titulo'] }}</h2>
            <span style="color: #10b981;" class="text-sm font-semibold">{{ count($sec['bots']) }}</span>
          </div>
          <p style="color: #9ca3af;" class="text-sm mb-4">{{ $sec['nota'] }}</p>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($sec['bots'] as $bot)
              @include('bots._card', ['bot' => $bot])
            @endforeach
          </div>
        </section>
      @endif
    @empty
      <div class="text-center text-slate-400 py-12">
        {{ __('bots.no_bots') }}. <a href="{{ route('bots.create') }}" class="text-blue-400 hover:text-blue-300">{{ __('bots.create_one') }}</a>
      </div>
    @endforelse
  </main>
@endsection
