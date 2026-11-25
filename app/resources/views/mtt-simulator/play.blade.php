@extends('app')

@section('content')
  <div x-data="gameContainer()"
       x-init="$store.tournamentWindow.start(@js(route('mtt-simulator.window.claim', $tournament)), @js(route('mtt-simulator.window.release', $tournament)), @js(__('tournaments.window_taken_message')))"
       style="background-color: transparent; min-height: calc(100vh - 80px);">

    <!-- Una sola ventana a la vez: si el torneo está abierto en otra, esta no juega hasta que se elija continuar aquí -->
    <div x-cloak x-show="$store.tournamentWindow.blocked" style="position: fixed; inset: 0; z-index: 1000; background: rgba(8, 12, 18, 0.88);">
      <div style="display: flex; align-items: center; justify-content: center; min-height: 100%; padding: 1rem;">
      <div role="alertdialog" aria-modal="true" style="max-width: 32rem; background: #1a212a; border: 1px solid #f59e0b; border-radius: 0.75rem; padding: 1.5rem; color: #d1d5db;">
        <h2 class="text-xl font-bold" style="color: #fbbf24; margin: 0 0 0.75rem;"
            x-text="$store.tournamentWindow.status === 'lost' ? @js(__('tournaments.window_lost_title')) : @js(__('tournaments.window_other_title'))"></h2>
        <p class="text-sm" style="margin: 0 0 1.25rem; line-height: 1.5;"
           x-text="$store.tournamentWindow.status === 'lost' ? @js(__('tournaments.window_lost_text')) : @js(__('tournaments.window_other_text'))"></p>
        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
          <button type="button" @click="$store.tournamentWindow.takeOver()" class="px-4 py-2 rounded font-semibold" style="background-color: #16a34a; color: white;">{{ __('tournaments.window_continue_here') }}</button>
          <a href="{{ route('mtt-simulator.index') }}" class="px-4 py-2 rounded font-semibold" style="background-color: #374151; color: white; text-decoration: none;">{{ __('tournaments.window_leave') }}</a>
        </div>
      </div>
      </div>
    </div>

    <!-- Header del Torneo -->
    <div style="background-color: #1a212a; border-bottom: 1px solid #2e3a48; padding: 1rem;">
      <div style="max-width: 1400px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold" style="color: #d1d5db;">{{ $tournament->title }}</h1>
          <p style="color: #9ca3af;" class="text-sm mt-1" x-data x-text="$store.tournamentInfo.data.big_blind ? ('Nivel ' + $store.tournamentInfo.data.level + ' · Ciegas ' + $store.tournamentInfo.data.small_blind + '/' + $store.tournamentInfo.data.big_blind + ' · ' + $store.tournamentInfo.data.players_alive + ' jugadores') : ''"></p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
          <button @click="pauseGame()" class="px-4 py-2 rounded font-semibold" style="background-color: #f59e0b; color: white;">
            <span x-show="!isPaused">Pausar</span>
            <span x-show="isPaused">Reanudar</span>
          </button>
          <a href="{{ route('mtt-simulator.history', $tournament) }}" class="px-4 py-2 rounded font-semibold" style="background-color: #1d4ed8; color: white; text-decoration: none;">📜 Historial</a>
          <a href="{{ route('mtt-simulator.index') }}" class="px-4 py-2 rounded font-semibold" style="background-color: #ef4444; color: white; text-decoration: none;">Salir</a>
        </div>
      </div>
    </div>

    <!-- Layout Principal: Mesa (3/4) + Info (1/4) -->
    <div style="max-width: 1400px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-6 flex flex-col-reverse lg:flex-row gap-3">

      <!-- Columna Derecha: Rivales + Rangos + Log (1/4) -->
      <div class="w-full lg:w-1/4" style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Rivales en Acción - Rangos -->
        <x-bots-action-ranges :handId="$hand->id" />

        <!-- Log del Juego -->
        <x-game-log />
      </div>

      <!-- Mesa de Juego (3/4) con la información debajo: así no se desplaza hacia abajo cuando crece la columna de rangos -->
      <div class="w-full lg:w-3/4" style="min-width: 0;">
        <x-game-table :hand="$hand" :currentUser="auth()->user()" :no-hud-data="$noHudData" />

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 420px), 1fr)); gap: 1rem; margin-top: 1rem; align-items: start;">
          <!-- Otras Mesas en Juego -->
          <x-active-tables />

          <!-- Estadísticas de Mano -->
          <x-hand-statistics />

          <!-- Historial de eliminaciones por puesto -->
          <div style="grid-column: 1 / -1;">
            <x-elimination-history />
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function gameContainer() {
      return {
        isPaused: false,

        pauseGame() {
          this.isPaused = !this.isPaused;
        }
      }
    }
  </script>
@endsection
