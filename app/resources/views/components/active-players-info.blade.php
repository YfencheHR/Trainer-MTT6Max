@props(['tournament' => null, 'playersStats' => []])

{{-- Panel que muestra matrices de rangos de rivales en acción durante la mano --}}
<div x-data="{
    preflopRanges: null,
    lastBotId: null,
    async loadPreflopRanges() {
      const botId = $store.gameState?.currentActorBotId;
      const position = $store.gameState?.currentPosition;

      if (!botId || !position || botId === this.lastBotId) return;

      try {
        const resp = await fetch(`/api/bots/${botId}/preflop-ranges/${position}`);
        if (resp.ok) {
          this.preflopRanges = await resp.json();
          this.lastBotId = botId;
        }
      } catch (e) {
        console.error('Error loading preflop ranges:', e);
      }
    },
    init() {
      this.$watch('$store.gameState.currentActorBotId', () => this.loadPreflopRanges());
    }
  }"
  style="background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 0.75rem;">

  <p style="color: #d1d5db; font-weight: 600; margin: 0 0 0.75rem; font-size: 0.8rem; text-transform: uppercase;">
    🎯 Rivales en Acción - Rangos
  </p>

  {{-- Mostrar matriz de rangos del bot actual si existe --}}
  <template x-if="preflopRanges">
    <div style="background: rgba(0,0,0,0.3); border-radius: 0.4rem; padding: 0.6rem; margin-bottom: 0.75rem;">
      <p style="margin: 0 0 0.4rem 0; font-size: 0.75rem; color: #9ca3af; text-transform: uppercase;">
        📊 Rango de <strong x-text="preflopRanges.bot_name"></strong> en <span x-text="position"></span>
      </p>

      {{-- Grid de manos que juega (mostrando solo las que tienen probabilidad > 0) --}}
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(32px, 1fr)); gap: 0.3rem; font-size: 0.7rem;">
        <template x-for="(range, idx) in (preflopRanges.ranges || [])" :key="idx">
          <div style="
            padding: 0.3rem;
            text-align: center;
            border-radius: 0.2rem;
            background: rgba(96, 165, 250, 0.2);
            color: #60a5fa;
            font-weight: 600;
            border: 1px solid rgba(96, 165, 250, 0.4);
            cursor: help;
          "
          :title="range.hand + ' - Open: ' + Math.round(range.attack.open) + '%, Call: ' + Math.round(range.defense.call) + '%'"
          x-show="range.attack.open > 0 || range.attack.limp > 0 || range.defense.call > 0">
            <div x-text="range.hand"></div>
            <div style="font-size: 0.6rem; color: #9ca3af;">
              <span x-text="Math.round(range.attack.open > 0 ? range.attack.open : (range.attack.limp > 0 ? range.attack.limp : 0)) + '%'"></span>
            </div>
          </div>
        </template>
      </div>
    </div>
  </template>

  {{-- Contenedor scrolleable de stats de todos los jugadores --}}
  <div style="max-height: 250px; overflow-y: auto;">
    @forelse($playersStats as $playerId => $stats)
      <div style="background: rgba(0,0,0,0.3); border-radius: 0.4rem; padding: 0.6rem; margin-bottom: 0.5rem; border-left: 3px solid
        @if($stats['is_bot']) #ef4444 @else #10b981 @endif
      ;">

        {{-- Nombre y rol --}}
        <p style="margin: 0; font-size: 0.85rem; font-weight: 600; color: #d1d5db;">
          {{ $stats['name'] }}
          <span style="font-size: 0.7rem; color: #9ca3af;">
            @if($stats['is_bot']) 🤖 @else 👤 @endif
          </span>
        </p>

        {{-- Stats principales en grid 2x3 --}}
        @php
            $statCell = 'margin: 0; font-size: 0.7rem; color: #9ca3af; text-transform: uppercase;';
            $statVal = 'margin: 0.2rem 0 0 0; font-size: 0.9rem; font-weight: 600; color: #60a5fa;';
        @endphp

        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.4rem; margin-top: 0.4rem;">
          <div>
            <p style="{{ $statCell }}">VPIP</p>
            <p style="{{ $statVal }}">{{ $stats['vpip'] ?? '–' }}%</p>
          </div>
          <div>
            <p style="{{ $statCell }}">PFR</p>
            <p style="{{ $statVal }}">{{ $stats['pfr'] ?? '–' }}%</p>
          </div>
          <div>
            <p style="{{ $statCell }}">AF</p>
            <p style="{{ $statVal }}">{{ $stats['af'] ?? '–' }}</p>
          </div>
          <div>
            <p style="{{ $statCell }}">3-BET</p>
            <p style="{{ $statVal }}">{{ $stats['three_bet'] ?? '–' }}%</p>
          </div>
        </div>

        {{-- Stats postflop --}}
        <div style="margin-top: 0.4rem; padding-top: 0.4rem; border-top: 1px solid rgba(255,255,255,0.1);">
          <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.4rem;">
            <div>
              <p style="{{ $statCell }}">C-BET</p>
              <p style="{{ $statVal }}">{{ $stats['cbet'] ?? '–' }}%</p>
            </div>
            <div>
              <p style="{{ $statCell }}">DONK</p>
              <p style="{{ $statVal }}">{{ $stats['donk'] ?? '–' }}%</p>
            </div>
          </div>
        </div>

        {{-- BB100 si está disponible --}}
        @if(isset($stats['bb100']) && $stats['bb100'] !== null)
          <div style="margin-top: 0.4rem; padding: 0.4rem; background: rgba(255,255,255,0.05); border-radius: 0.3rem;">
            <p style="{{ $statCell }}">BB/100</p>
            <p style="margin: 0.2rem 0 0 0; font-size: 0.9rem; font-weight: 600; color:
              @if($stats['bb100'] > 0) #10b981 @elseif($stats['bb100'] < 0) #ef4444 @else #9ca3af @endif
            ;">
              @if($stats['bb100'] > 0) + @endif {{ $stats['bb100'] }}
            </p>
          </div>
        @endif
      </div>
    @empty
      <p style="margin: 0; font-size: 0.8rem; color: #9ca3af; text-align: center; padding: 1rem;">
        Esperando acción...
      </p>
    @endforelse
  </div>

  {{-- Ayuda --}}
  <p style="margin: 1rem 0 0 0; font-size: 0.7rem; color: #6b7280; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 0.5rem;">
    <strong>Tip:</strong> Usa estos stats para entender las decisiones del bot. Rivales con VPIP alto juegan más manos; AF alto = agresivos.
  </p>
</div>
