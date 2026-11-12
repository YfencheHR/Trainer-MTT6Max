@php
  $meta = (array) $bot->metadata;
  $history = collect($meta['history'] ?? []);
  $last = $history->last();
  $primary = !empty($meta['primary_id']) ? \App\Models\Bot::find($meta['primary_id']) : null;
  $mine = $bot->generalStats();
  $theirs = $primary?->generalStats();
  $compare = ['vpip' => 'VPIP', 'pfr' => 'PFR', 'three_bet' => '3-Bet', 'fold_to_3bet' => 'Fold a 3-Bet', 'four_bet' => '4-Bet', 'cbet' => 'C-Bet', 'f2cbet' => 'Fold a C-Bet', 'af_flop' => 'AF Flop', 'beturn' => 'Bet Turn', 'wsd' => 'WSD'];
@endphp

<div class="mb-8" style="background-color: #16261f; border: 1px solid #2f5d50;" >
  <div class="p-6 rounded">
    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
      <h2 class="text-2xl font-bold text-white">Datos de PokerTracker</h2>
      @if($primary)
        <p style="color: #9ca3af;" class="text-sm">Copia de <a href="{{ route('bots.show', $primary) }}" style="color: #10b981;" class="font-semibold hover:underline">{{ $primary->icono }} {{ $primary->nombre }}</a>, que conserva sus cifras de referencia</p>
      @else
        <p style="color: #9ca3af;" class="text-sm">Perfil nuevo: agrupa jugadores que no encajaban en ningún primario</p>
      @endif
    </div>

    @if($last)
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div><p style="color: #9ca3af;" class="text-xs uppercase">Jugadores (última importación)</p><p style="color: #10b981;" class="text-3xl font-bold">{{ number_format($last['players'], 0, ',', '.') }}</p></div>
        <div><p style="color: #9ca3af;" class="text-xs uppercase">% del pool</p><p style="color: #e5e7eb;" class="text-3xl font-bold">{{ $last['share'] !== null ? rtrim(rtrim(number_format($last['share'], 1, ',', ''), '0'), ',') . '%' : '—' }}</p></div>
        <div><p style="color: #9ca3af;" class="text-xs uppercase">Manos acumuladas</p><p style="color: #e5e7eb;" class="text-3xl font-bold">{{ number_format($last['hands_total'] ?? 0, 0, ',', '.') }}</p></div>
        <div><p style="color: #9ca3af;" class="text-xs uppercase">Importaciones</p><p style="color: #e5e7eb;" class="text-3xl font-bold">{{ $history->count() }}</p></div>
      </div>
    @endif

    @if($primary && $mine && $theirs)
      <h3 class="text-sm uppercase font-bold mb-2" style="color: #9ca3af;">Primario frente a Pool</h3>
      <div class="overflow-x-auto mb-6">
        <table class="w-full text-sm" style="color: #d1d5db; min-width: 560px;">
          <thead><tr style="color: #9ca3af;" class="text-left text-xs uppercase">
            <th class="pb-2">Estadística</th><th class="pb-2">{{ $primary->nombre }} (referencia)</th><th class="pb-2">Este pool</th><th class="pb-2">Diferencia</th>
          </tr></thead>
          <tbody>
            @foreach($compare as $col => $name)
              @php
                $a = $theirs->{$col}; $b = $mine->{$col};
                $diff = ($a !== null && $b !== null) ? $b - $a : null;
              @endphp
              <tr style="border-top: 1px solid #2f5d50;">
                <td class="py-2 font-semibold text-white">{{ $name }}</td>
                <td>{{ \App\Config\BotStreets::format($col, $a) }}</td>
                <td>{{ \App\Config\BotStreets::format($col, $b) }}</td>
                <td style="color: {{ $diff === null ? '#4b5563' : (abs($diff) < (\App\Config\BotStreets::isRatio($col) ? 0.3 : 3) ? '#9ca3af' : ($diff > 0 ? '#f59e0b' : '#60a5fa')) }};">
                  {{ $diff === null ? '—' : (($diff > 0 ? '+' : '') . (\App\Config\BotStreets::isRatio($col) ? round($diff, 2) : (int) round($diff) . ' pts')) }}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if($history->count())
      <h3 class="text-sm uppercase font-bold mb-2" style="color: #9ca3af;">Cómo ha ido cambiando</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-sm" style="color: #d1d5db; min-width: 560px;">
          <thead><tr style="color: #9ca3af;" class="text-left text-xs uppercase">
            <th class="pb-2">Fecha</th><th class="pb-2">Jugadores</th><th class="pb-2">% del pool</th><th class="pb-2">Manos nuevas</th><th class="pb-2">Manos acumuladas</th><th class="pb-2">VPIP</th><th class="pb-2">PFR</th><th class="pb-2">3-Bet</th>
          </tr></thead>
          <tbody>
            @foreach($history->reverse() as $h)
              <tr style="border-top: 1px solid #2f5d50;">
                <td class="py-2">{{ \Carbon\Carbon::parse($h['at'])->format('d/m/Y H:i') }}</td>
                <td>{{ number_format($h['players'], 0, ',', '.') }}</td>
                <td>{{ $h['share'] !== null ? $h['share'] . '%' : '—' }}</td>
                <td>{{ number_format($h['hands_added'], 0, ',', '.') }}</td>
                <td>{{ number_format($h['hands_total'], 0, ',', '.') }}</td>
                <td>{{ $h['vpip'] !== null ? $h['vpip'] . '%' : '—' }}</td>
                <td>{{ $h['pfr'] !== null ? $h['pfr'] . '%' : '—' }}</td>
                <td>{{ $h['three_bet'] !== null ? $h['three_bet'] . '%' : '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
