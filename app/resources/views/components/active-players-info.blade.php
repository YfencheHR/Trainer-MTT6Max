@props(['tournament' => null])

{{-- Panel que muestra matrices de rangos dinámicos de rivales en acción durante la mano --}}
<div x-data="{
    get dynamicRanges() {
      return $store?.gameState?.dynamicRanges ?? null;
    },
    init() {
      this.$watch(() => $store?.gameState?.dynamicRanges, (ranges) => {
        if (ranges) {
          console.log('✅ Rangos dinámicos actualizados:', ranges);
        }
      });
    }
  }"
  style="background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 0.75rem;">

  <p style="color: #d1d5db; font-weight: 600; margin: 0 0 0.75rem; font-size: 0.8rem; text-transform: uppercase;">
    🎯 Rivales en Acción - Rangos
  </p>

  {{-- Mostrar matriz de rangos dinámicos del bot actual si existe --}}
  <template x-if="dynamicRanges && Object.keys(dynamicRanges.ranges).length > 0">
    <div style="background: rgba(0,0,0,0.3); border-radius: 0.4rem; padding: 0.6rem; margin-bottom: 0.75rem;">
      <p style="margin: 0 0 0.4rem 0; font-size: 0.75rem; color: #9ca3af; text-transform: uppercase;">
        📊 Rango dinámico de <strong x-text="dynamicRanges.player_name"></strong>
      </p>

      {{-- Grid de manos que juega en el rango dinámico --}}
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(32px, 1fr)); gap: 0.3rem; font-size: 0.7rem;">
        <template x-for="(stats, hand) in (dynamicRanges.ranges || {})" :key="hand">
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
          :title="hand + ' - Open: ' + Math.round(stats.open || 0) + '%, Call: ' + Math.round(stats.call || 0) + '%, Raise: ' + Math.round(stats.raise || 0) + '%'"
          x-show="(stats.open || 0) > 0 || (stats.call || 0) > 0 || (stats.raise || 0) > 0">
            <div x-text="hand"></div>
            <div style="font-size: 0.6rem; color: #9ca3af;">
              <span x-text="Math.max(stats.open || 0, stats.call || 0, stats.raise || 0).toFixed(0) + '%'"></span>
            </div>
          </div>
        </template>
      </div>
    </div>
  </template>

</div>
