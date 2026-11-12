@php
  $meta = (array) $bot->metadata;
  $isCopy = ($meta['source'] ?? null) === 'pt4_pool';
  $last = collect($meta['history'] ?? [])->last();

  // 3-Bet y factor de agresión (AF) de la fila general; el AF es la media de las calles postflop que tengan dato
  $gs = $bot->hudStats->first(fn($r) => $r->posicion === null && $r->accion === null && $r->rango_ciegas === null);
  $afs = collect([$gs?->af_flop, $gs?->af_turn, $gs?->af_river])->filter(fn($v) => $v !== null);
  $af = $afs->isNotEmpty() ? round($afs->avg(), 1) : null;
  if ($af === null) {
      // Sin cifras postflop propias (perfiles primarios): la tabla documentada del perfil
      $doc = \App\Services\Bots\Brain\BotProfile::DOCUMENTED_POSTFLOP[$bot->templateTipo()] ?? null;
      $af = $doc ? round(collect([$doc['flop']['af'] ?? null, $doc['turn']['af'] ?? null, $doc['river']['af'] ?? null])->filter()->avg(), 1) : null;
  }
  $threeBet = $gs?->three_bet;
@endphp
<div style="background-color: #1a212a; border-color: {{ $bot->isPool() ? '#2f5d50' : '#2e3a48' }};" class="rounded border p-6 hover:border-green-600 transition">
  <!-- Encabezado con Icono y Nombre -->
  <div class="flex items-start gap-4 mb-4">
    <div style="background: linear-gradient(135deg, #10b981, #059669); width: 56px; height: 56px;" class="flex-shrink-0 rounded-lg flex items-center justify-center text-white font-bold text-2xl">
      {{ $bot->icono }}
    </div>
    <div class="flex-1">
      <h3 style="color: #ffffff;" class="text-xl font-bold">{{ $bot->nombre }}</h3>
      <p style="color: #9ca3af;" class="text-sm">
        @if($bot->isPool())
          {{ $isCopy ? 'Copia de ' . ucfirst(str_replace('_', ' ', $bot->templateTipo())) : 'Perfil nuevo' }} · Pool
        @else
          {{ ucfirst($bot->tipo) }}
        @endif
      </p>
      @if($bot->profesional)
        <span style="background-color: #10b981; color: white;" class="inline-block mt-2 text-xs px-2 py-1 rounded">{{ __('bots.pro_badge') }}</span>
      @endif
    </div>
  </div>

  <!-- Estadísticas -->
  <div class="flex flex-wrap gap-x-5 gap-y-3 mb-4 pt-4" style="border-top: 1px solid #2e3a48;">
    <div>
      <p style="color: #9ca3af;" class="text-xs uppercase">VPIP</p>
      <p style="color: {{ \App\Config\StatColor::getColor('vpip', $bot->vpip) }};" class="text-lg font-bold">{{ $bot->vpip }}%</p>
    </div>
    <div>
      <p style="color: #9ca3af;" class="text-xs uppercase">PFR</p>
      <p style="color: {{ \App\Config\StatColor::getColor('pfr', $bot->pfr) }};" class="text-lg font-bold">{{ $bot->pfr }}%</p>
    </div>
    <div>
      <p style="color: #9ca3af;" class="text-xs uppercase">3-Bet</p>
      <p style="color: {{ $threeBet === null ? '#4b5563' : '#e5e7eb' }};" class="text-lg font-bold">{{ $threeBet === null ? '—' : $threeBet . '%' }}</p>
    </div>
    <div title="Factor de agresión postflop: (apuestas + subidas) / calls, media de flop, turn y river">
      <p style="color: #9ca3af;" class="text-xs uppercase">AF</p>
      <p style="color: {{ $af === null ? '#4b5563' : '#e5e7eb' }};" class="text-lg font-bold">{{ $af === null ? '—' : $af }}</p>
    </div>
    <div>
      <p style="color: #9ca3af;" class="text-xs uppercase">TILT</p>
      <p style="color: {{ \App\Config\StatColor::getColor('tilt', $bot->tilt_risk) }};" class="text-lg font-bold">{{ $bot->tilt_risk }}%</p>
    </div>
    @if($bot->isPool() && $last)
      <div>
        <p style="color: #9ca3af;" class="text-xs uppercase">Jugadores</p>
        <p style="color: #10b981;" class="text-lg font-bold">{{ number_format($last['players'], 0, ',', '.') }}</p>
      </div>
      <div>
        <p style="color: #9ca3af;" class="text-xs uppercase">Manos</p>
        <p style="color: #d1d5db;" class="text-lg font-bold">{{ number_format($last['hands_total'] ?? 0, 0, ',', '.') }}</p>
      </div>
      @if(isset($meta['bb100']) && !empty($meta['bb100_n']))
        @php
          // Resultado en fichas (sin ICM) del grupo; con pocas manos es ruido, por eso se muestra su error típico
          $bb = (float) $meta['bb100'];
          $se = 100 * (float) ($meta['bb100_sd'] ?? 0) / sqrt(max(1, (int) $meta['bb100_n']));
          $bbColor = abs($bb) <= $se ? '#9ca3af' : ($bb > 0 ? '#34d399' : '#f87171');
        @endphp
        <div title="Resultado medio del grupo en ciegas grandes por 100 manos (fichas, sin ICM). Error típico ±{{ number_format($se, 1, ',', '') }} con {{ number_format($meta['bb100_n'], 0, ',', '.') }} manos. En gris = no se distingue de cero. Solo cuenta jugadores con 20+ manos (los eliminados pronto, que pierden su stack, quedan fuera): el resultado está inflado por supervivencia, úsalo para comparar grupos entre sí.">
          <p style="color: #9ca3af;" class="text-xs uppercase">bb/100</p>
          <p style="color: {{ $bbColor }};" class="text-lg font-bold">{{ ($bb > 0 ? '+' : '') . number_format($bb, 1, ',', '') }}</p>
        </div>
      @endif
    @endif
  </div>

  <!-- Descripción -->
  @if($bot->descripcion)
    <div style="color: #ffffff;" class="mb-4 p-3 rounded text-sm">
      {{ $bot->descripcion }}
    </div>
  @endif

  @if($bot->perfil)
    <div style="color: #d1d5db;" class="mb-4 text-sm">
      <span style="color: #9ca3af;">{{ __('bots.profile') }}:</span> {{ ucfirst($bot->perfil) }}
    </div>
  @endif

  <!-- Botones de Acción -->
  <div class="flex gap-2 pt-4" style="border-top: 1px solid #2e3a48;">
    <a href="{{ route('bots.show', $bot) }}" style="background-color: #10b981; color: white;" class="w-full hover:opacity-80 text-center px-3 py-2 rounded text-sm font-semibold transition">{{ __('bots.view_button') }}</a>
  </div>
</div>
