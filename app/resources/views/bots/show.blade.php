@extends('app')

@section('content')
  <main style="max-width: 1440px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
    <!-- Encabezado -->
    <div class="flex justify-between items-center mb-8">
      <div>
        <div class="flex items-center gap-3 mb-2">
          <span class="text-4xl">{{ $bot->icono ?? '🤖' }}</span>
          <h1 class="text-4xl font-bold text-white">{{ $bot->nombre }}</h1>
        </div>
        <p style="color: #9ca3af;" class="text-lg">{{ ucfirst($bot->tipo) }} • {{ $bot->perfil ?? 'N/A' }} @if($bot->profesional)<span style="background-color: #10b981; color: white;" class="inline-block ml-2 px-2 py-1 text-xs rounded">PRO</span>@endif</p>
      </div>
      <x-modal-popup
        id="deleteModal"
        :title="__('bots.delete')"
        :message="__('bots.confirm_delete')"
        :primaryBtnText="__('bots.delete')"
        :secondaryBtnText="__('common.cancel')"
        primaryBtnColor="ef4444"
        onConfirm="document.getElementById('deleteForm').submit()">

        <div class="flex gap-2">
          <a href="{{ route('bots.edit', $bot) }}" style="background-color: #f59e0b;" class="hover:opacity-80 text-white px-6 py-2 rounded font-semibold transition">{{ __('bots.edit') }}</a>
          <button type="button" style="background-color: #ef4444;" class="hover:opacity-80 text-white px-6 py-2 rounded font-semibold transition" @click="deleteModal = true">{{ __('bots.delete') }}</button>
          <form method="POST" action="{{ route('bots.duplicate', $bot) }}" style="display: inline;">
            @csrf
            <button type="submit" style="background-color: #8b5cf6;" class="hover:opacity-80 text-white px-6 py-2 rounded font-semibold transition">{{ __('bots.copy') }}</button>
          </form>
        </div>

        <!-- Delete Form (hidden) -->
        <form id="deleteForm" method="POST" action="{{ route('bots.destroy', $bot) }}" style="display: none;">
          @csrf
          @method('DELETE')
        </form>
      </x-modal-popup>
    </div>

    <!-- Descripción -->
    @if($bot->descripcion)
      <div style="background-color: #1a212a; border-color: #2e3a48;" class="mb-8 p-6 rounded border">
        <p style="color: #ffffff;" class="text-base">{{ $bot->descripcion }}</p>
      </div>
    @endif

    @if($bot->isPool())
      @include('bots._pool-panel', ['bot' => $bot])
    @endif

    <!-- Estadísticas Generales -->
    <div class="mb-8">
      <h2 class="text-2xl font-bold text-white mb-4">{{ __('bots.general_stats') }}</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- VPIP -->
        <div style="background-color: #1a212a; border-color: #2e3a48;" class="p-6 rounded border">
          <p style="color: #9ca3af;" class="text-xs uppercase mb-2">{{ __('bots.vpip') }}</p>
          <p style="color: {{ \App\Config\StatColor::getColor('vpip', $bot->vpip) }};" class="text-4xl font-bold">{{ $bot->vpip }}%</p>
        </div>

        <!-- PFR -->
        <div style="background-color: #1a212a; border-color: #2e3a48;" class="p-6 rounded border">
          <p style="color: #9ca3af;" class="text-xs uppercase mb-2">{{ __('bots.pfr') }}</p>
          <p style="color: {{ \App\Config\StatColor::getColor('pfr', $bot->pfr) }};" class="text-4xl font-bold">{{ $bot->pfr }}%</p>
        </div>

        <!-- Tilt Risk -->
        <div style="background-color: #1a212a; border-color: #2e3a48;" class="p-6 rounded border">
          <p style="color: #9ca3af;" class="text-xs uppercase mb-2">{{ __('bots.tilt_risk') }}</p>
          <p style="color: {{ \App\Config\StatColor::getColor('tilt', $bot->tilt_risk) }};" class="text-4xl font-bold">{{ $bot->tilt_risk }}%</p>
        </div>
      </div>
    </div>

    <!-- Estadísticas por calle -->
    @php
      $general = $bot->generalStats();
      $posiciones = $bot->positionStats();
      $roles = $bot->postflopRoles();
      $streets = \App\Config\BotStreets::STREETS;
      $colored = ['vpip', 'pfr', 'three_bet', 'four_bet'];
    @endphp
    <div class="mb-8">
      <div class="flex flex-wrap items-end justify-between gap-2 mb-4">
        <h2 class="text-2xl font-bold text-white">Estadísticas por calle poyas jontas</h2>
        @if($general && $general->sample_size)
          <p style="color: #9ca3af;" class="text-sm">Calculadas con <span style="color: #10b981;" class="font-bold">{{ number_format($general->sample_size, 0, ',', '.') }}</span> manos · confianza {{ round(($general->confidence ?? 0) * 100) }}%</p>
        @elseif($general)
          <p style="color: #f59e0b;" class="text-sm">Cifras provisionales (sin muestra real): importa datos de PokerTracker para sustituirlas</p>
        @endif
      </div>

      @if($general)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          @foreach($streets as $key => $street)
            <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6">
              <h3 class="text-lg font-bold text-white mb-4">{{ $street['nombre'] }}</h3>

              <p style="color: #10b981;" class="text-xs uppercase font-bold mb-2">{{ __('bots.rigurosas') }}</p>
              <div class="grid grid-cols-3 gap-3 mb-5">
                @foreach($street['stats'] as $stat)
                  @php $v = $general->{$stat}; @endphp
                  <div style="background-color: #11161c;" class="rounded p-3">
                    <p style="color: #9ca3af;" class="text-xs mb-1">{{ \App\Config\BotStreets::LABELS[$stat] }}</p>
                    <p style="color: {{ $v !== null && in_array($stat, $colored) ? \App\Config\StatColor::getColor($stat, $v) : ($v === null ? '#4b5563' : '#e5e7eb') }};" class="font-bold text-lg">{{ \App\Config\BotStreets::format($stat, $v) }}</p>
                  </div>
                @endforeach
              </div>

              <p style="color: #f59e0b;" class="text-xs uppercase font-bold mb-2">{{ __('bots.adaptativas') }}</p>
              <div class="grid grid-cols-3 gap-3">
                @foreach($street['adaptativas'] as $stat)
                  @php $v = $general->{$stat}; @endphp
                  <div style="background-color: #11161c;" class="rounded p-3">
                    <p style="color: #9ca3af;" class="text-xs mb-1">{{ \App\Config\BotStreets::LABELS[$stat] }}</p>
                    <p style="color: {{ $v === null ? '#4b5563' : '#e5e7eb' }};" class="font-bold text-lg">{{ \App\Config\BotStreets::format($stat, $v) }}</p>
                  </div>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>
      @else
        <div style="background-color: #1a212a; border-color: #2e3a48; color: #9ca3af;" class="rounded border p-6 text-sm">{{ __('bots.not_available') }}</div>
      @endif
    </div>

    <!-- Estadísticas por posición (datos importados) -->
    @if($posiciones)
      <div class="mb-8">
        <h2 class="text-2xl font-bold text-white mb-1">Por posición</h2>
        <p style="color: #9ca3af;" class="text-sm mb-4">Apertura, 3-bet, call en frío y respuesta a 3-bet según dónde se sienta. Son las cifras que usa el cerebro del bot en cada posición.</p>
        <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 overflow-x-auto">
          <table class="w-full text-sm" style="color: #d1d5db; min-width: 640px;">
            <thead>
              <tr style="color: #9ca3af;" class="text-left text-xs uppercase">
                <th class="pb-2">Posición</th>
                @foreach(['rfi' => 'RFI', 'vpip' => 'VPIP', 'pfr' => 'PFR', 'three_bet' => '3-Bet', 'cold_call' => 'Call en frío', 'fold_to_3bet' => 'Fold a 3-Bet', 'four_bet' => '4-Bet', 'squeeze' => 'Squeeze'] as $col => $name)
                  <th class="pb-2">{{ $name }}</th>
                @endforeach
                <th class="pb-2 text-right">Manos</th>
              </tr>
            </thead>
            <tbody>
              @foreach($posiciones as $pos => $r)
                <tr style="border-top: 1px solid #2e3a48;">
                  <td class="py-2 font-semibold text-white">{{ $pos }}</td>
                  @foreach(['rfi', 'vpip', 'pfr', 'three_bet', 'cold_call', 'fold_to_3bet', 'four_bet', 'squeeze'] as $col)
                    <td style="color: {{ isset($r[$col]) ? '#e5e7eb' : '#4b5563' }};">{{ \App\Config\BotStreets::format($col, $r[$col] ?? null) }}</td>
                  @endforeach
                  <td class="text-right" style="color: #9ca3af;">{{ number_format($r['hands'] ?? 0, 0, ',', '.') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif
    <!-- Agresión por calle: en posición / fuera de posición, como agresor y como defensor -->
    @if($roles)
      <div class="mb-8">
        <h2 class="text-2xl font-bold text-white mb-1">Agresión por calle: IP vs OOP</h2>
        <p style="color: #9ca3af;" class="text-sm mb-4">
          <b style="color:#e5e7eb;">Como agresor</b> (tenía la iniciativa): cuánto apuesta (c-bet y barriles). <b style="color:#e5e7eb;">Como defensor</b>: qué hace ante esa apuesta.
          IP = en posición · OOP = fuera de posición.
        </p>
        <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6 overflow-x-auto">
          <table class="w-full text-sm" style="color: #d1d5db; min-width: 640px;">
            <thead>
              <tr style="color: #9ca3af;" class="text-left text-xs uppercase">
                <th class="pb-2">Calle</th><th class="pb-2">Posición</th>
                <th class="pb-2" style="color:#10b981;">Agresor: apuesta</th>
                <th class="pb-2" style="color:#f59e0b;">Defensor: fold</th><th class="pb-2" style="color:#f59e0b;">call</th><th class="pb-2" style="color:#f59e0b;">raise</th>
                <th class="pb-2 text-right">Ocasiones (agr / def)</th>
              </tr>
            </thead>
            <tbody>
              @foreach(['flop' => 'Flop', 'turn' => 'Turn', 'river' => 'River'] as $street => $streetName)
                @foreach(['ip' => 'IP', 'oop' => 'OOP'] as $ip => $ipName)
                  @php $r = $roles[$street][$ip] ?? null; @endphp
                  @if($r)
                    <tr style="border-top: 1px solid #2e3a48;">
                      <td class="py-2 font-semibold text-white">{{ $ip === 'ip' ? $streetName : '' }}</td>
                      <td>{{ $ipName }}</td>
                      <td style="color: {{ isset($r['agg']['bet']) ? '#e5e7eb' : '#4b5563' }};">{{ \App\Config\BotStreets::format('cbet', $r['agg']['bet'] ?? null) }}</td>
                      @foreach(['fold', 'call', 'raise'] as $k)
                        <td style="color: {{ isset($r['def'][$k]) ? '#e5e7eb' : '#4b5563' }};">{{ \App\Config\BotStreets::format('cbet', $r['def'][$k] ?? null) }}</td>
                      @endforeach
                      <td class="text-right" style="color: #9ca3af;">{{ number_format($r['agg']['n'] ?? 0, 0, ',', '.') }} / {{ number_format($r['def']['n'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                  @endif
                @endforeach
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif

    @if($bot->isPool())
      @include('bots._pool-preflop-tables', ['tables' => $preflopTables])
    @endif
  </main>
@endsection
