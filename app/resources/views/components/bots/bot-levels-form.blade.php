@props(['bot' => null])

@php
  $streets = \App\Config\BotStreets::STREETS;
  $labels = \App\Config\BotStreets::LABELS;
  // Todas las estadísticas viven en la fila general del bot; se agrupan por calle solo para editarlas
  $general = $bot ? $bot->generalStats() : null;
@endphp

<!-- Estadísticas por calle -->
<div class="mb-2 flex flex-wrap items-end justify-between gap-2">
  <h2 class="text-2xl font-bold text-white">Estadísticas por calle</h2>
  @if($general && $general->sample_size)
    <p style="color: #9ca3af;" class="text-sm">Datos reales de {{ number_format($general->sample_size, 0, ',', '.') }} manos. Si los cambias a mano, los valores se tratan como ajuste manual.</p>
  @endif
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
  @foreach($streets as $key => $street)
    <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-6">
      <h3 class="text-xl font-bold text-white mb-4">{{ $street['nombre'] }}</h3>

      <p style="color: #10b981;" class="text-xs uppercase font-bold mb-3">{{ __('bots.rigurosas') }}</p>
      <div class="grid grid-cols-3 gap-4 mb-6">
        @foreach($street['stats'] as $stat)
          <x-bots.stat-input-with-tooltip
            :label="$labels[$stat] ?? $stat"
            :stat="$stat"
            :value="$general?->{$stat} ?? ''"
            :fieldName="'stats[1][' . $stat . ']'"
          />
        @endforeach
      </div>

      <p style="color: #f59e0b;" class="text-xs uppercase font-bold mb-3">{{ __('bots.adaptativas') }}</p>
      <div class="grid grid-cols-3 gap-4">
        @foreach($street['adaptativas'] as $stat)
          <x-bots.stat-input-with-tooltip
            :label="$labels[$stat] ?? $stat"
            :stat="$stat"
            :value="$general?->{$stat} ?? ''"
            :fieldName="'stats[1][' . $stat . ']'"
          />
        @endforeach
      </div>
    </div>
  @endforeach
</div>
