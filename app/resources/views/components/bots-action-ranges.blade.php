@props(['handId'])

{{-- Rangos de los rivales que han entrado en la mano: una fila por rival y UNA matriz, la del seleccionado (por defecto, el que actúa).
     La matriz ocupa siempre el mismo espacio, así el panel no salta cuando entran, foldean o cambian de rival. --}}
<div x-data="botsActionRanges({{ $handId }})" style="background-color: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 0.75rem;">
  <p style="color: #d1d5db; font-weight: 600; margin: 0 0 0.5rem; font-size: 0.8rem; text-transform: uppercase;">
    🎯 Rivales en acción · rangos
  </p>

  <div style="display: flex; flex-direction: column; gap: 0.6rem;">
    {{-- Lista de rivales: pulsa uno para ver su matriz --}}
    <div style="display: flex; flex-direction: column; gap: 0.25rem; height: 7.6rem; overflow-y: auto;">
      <p x-show="activeBots.length === 0" style="margin: 0; color: #9ca3af; font-size: 0.8rem;">Esperando acción de los rivales...</p>
      <template x-for="bot in activeBots" :key="bot.id">
        <button type="button" @click="select(bot)"
          :title="'Ver el rango de ' + bot.name"
          style="display: flex; align-items: center; justify-content: space-between; gap: 0.4rem; width: 100%; text-align: left; padding: 0.3rem 0.45rem; border-radius: 0.35rem; cursor: pointer; color: inherit; font: inherit; transition: background .15s, border-color .15s;"
          :style="{ background: bot.id === selectedId ? '#243244' : '#151b24', border: '1px solid ' + (bot.id === selectedId ? '#3b82f6' : '#2e3a48'), opacity: bot.is_folded ? 0.55 : 1 }">
          <span style="display: flex; align-items: center; gap: 0.35rem; min-width: 0;">
            <span style="color: #e5e7eb; font-size: 0.75rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="bot.name"></span>
            <span style="font-size: 0.6rem; background: #2e3a48; color: #d1d5db; padding: 0 0.3rem; border-radius: 0.25rem; flex-shrink: 0;" x-text="bot.position"></span>
          </span>
          <span style="display: flex; align-items: center; gap: 0.25rem; flex-shrink: 0;">
            <span x-show="bot.is_folded" style="font-size: 0.6rem; background: #7f1d1d; color: #fecaca; padding: 0 0.3rem; border-radius: 0.25rem;">FOLD</span>
            <span x-show="bot.is_all_in && !bot.is_folded" style="font-size: 0.6rem; background: #7c2d12; color: #fed7aa; padding: 0 0.3rem; border-radius: 0.25rem;">ALL-IN</span>
            <span x-show="bot.action && !bot.is_folded" style="font-size: 0.6rem; background: #1e3a8a; color: #bfdbfe; padding: 0 0.3rem; border-radius: 0.25rem;" x-text="(bot.action || '').toUpperCase()"></span>
            <span x-show="bot.ranges && bot.summary.played > 0" style="font-size: 0.65rem; color: #9ca3af; min-width: 2.4rem; text-align: right;" x-text="bot.summary.played + '%'"></span>
          </span>
        </button>
      </template>
    </div>

    {{-- Matriz del rival seleccionado: tamaño fijo, centrada --}}
    <div style="border-top: 1px solid #2e3a48; padding-top: 0.5rem;">
      <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.3rem; min-height: 1.3rem;">
        <span style="color: #e5e7eb; font-size: 0.75rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="selected()?.name || ''"></span>
        <span style="font-size: 0.65rem; color: #9ca3af; flex-shrink: 0;" x-show="selected()?.ranges">
          <span style="color: #fca5a5;" x-text="'R ' + selected().summary.raise + '%'"></span> ·
          <span style="color: #93c5fd;" x-text="'C ' + selected().summary.call + '%'"></span>
          <span x-show="selected().summary.check > 0" style="color: #86efac;" x-text="' · K ' + selected().summary.check + '%'"></span>
        </span>
      </div>

      <div style="position: relative; width: 100%; max-width: 280px; aspect-ratio: 1; margin: 0 auto;">
        <div style="position: absolute; inset: 0; display: grid; grid-template-columns: repeat(13, minmax(0, 1fr)); grid-template-rows: repeat(13, minmax(0, 1fr)); gap: 1px;"
          :style="{ opacity: selected()?.ranges && Object.keys(selected().ranges).length > 0 ? 1 : 0.25 }">
          <template x-for="c in cells(selected())" :key="c.hand">
            <div :title="c.title" :style="{ background: c.bg, opacity: c.active ? 1 : 0.35 }"
              style="display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.5rem; font-weight: 600; text-shadow: 0 1px 2px #000; border-radius: 2px; overflow: hidden; user-select: none;"
              x-text="c.hand"></div>
          </template>
        </div>
        <div x-show="!selected()?.ranges || Object.keys(selected().ranges).length === 0"
          style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; text-align: center; color: #9ca3af; font-size: 0.75rem; padding: 1rem;">
          <span x-show="selected()?.is_folded">Se fue (fold)</span>
          <span x-show="!selected()?.is_folded">Esperando rango...</span>
        </div>
      </div>

      <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 0.6rem; font-size: 0.65rem; color: #9ca3af; margin-top: 0.4rem;">
        <span><span style="display:inline-block;width:0.6rem;height:0.6rem;background:#4b5563;border-radius:2px;"></span> Fold</span>
        <span><span style="display:inline-block;width:0.6rem;height:0.6rem;background:#1d4ed8;border-radius:2px;"></span> Call</span>
        <span><span style="display:inline-block;width:0.6rem;height:0.6rem;background:#b91c1c;border-radius:2px;"></span> Raise</span>
        <span><span style="display:inline-block;width:0.6rem;height:0.6rem;background:#15803d;border-radius:2px;"></span> Check</span>
      </div>
    </div>
  </div>
</div>
