@extends('app')

@section('content')
  <div x-data="gameContainer()" style="background-color: transparent; min-height: calc(100vh - 80px);">

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

      <!-- Columna Derecha: Info + Log (1/4) -->
      <div class="w-full lg:w-1/4" style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Información del Torneo -->
        <x-tournament-info :tournament="$tournament" />

        <!-- Log del Juego -->
        <x-game-log />
      </div>

      <!-- Mesa de Juego (3/4) -->
      <div class="w-full lg:w-3/4" style="min-width: 0;">
        <x-game-table :hand="$hand" :currentUser="auth()->user()" :no-hud-data="$noHudData" />
      </div>
    </div>

    <!-- Módulos Adicionales (debajo) -->
    <div style="max-width: 1400px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Otras Mesas en Juego -->
        <x-active-tables />

        <!-- Estadísticas de Mano -->
        <x-hand-statistics />

        <!-- Historial de eliminaciones por puesto -->
        <x-elimination-history />
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
