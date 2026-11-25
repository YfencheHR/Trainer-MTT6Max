import { gameTable } from './game-table.js';

/**
 * La mesa de juego (la misma de la partida) para ver una mano repetida paso a paso.
 *
 * Reutiliza gameTable: el componente pinta cualquier estado con el formato del servidor (`HandPlayService::state`). Aquí el estado no llega de la
 * partida sino de una lista de estados ya calculados (uno por cada acción, ver HandReplayService::frames) que se recorren con los controles. No hay
 * sockets, ni turnos de bots, ni acciones del jugador.
 *
 * @param {number} handId   mano original
 * @param {string} framesUrl  ruta que devuelve los estados de la versión pedida (?version=original|<id de repetición>)
 * @param {string} version  versión que se muestra al abrir
 */
export function replayTable(handId, framesUrl, version = 'original') {
  const base = gameTable(handId, null, null);

  return {
    ...base,

    version,
    frames: [],
    index: 0,
    playing: false,
    speedMs: 1400,
    timer: null,
    decision: null,       // la acción que acaba de pasar (y lo que pensó el bot)
    replayLoading: false,
    replayError: null,

    // Sin partida real: nada de sockets ni de turnos de bots
    connectSocket() {},
    disconnectSocket() {},
    runBotTurns() { return Promise.resolve(); },
    destroy() { this.pause(); },

    init() {
      this.loadVersion(this.version);
      window.addEventListener('replay-version', event => this.loadVersion(String(event.detail)));
    },

    async loadVersion(version) {
      this.pause();
      this.version = String(version);
      this.replayLoading = true;
      this.replayError = null;
      try {
        const response = await fetch(`${framesUrl}?version=${encodeURIComponent(this.version)}`, { headers: { Accept: 'application/json' } });
        const json = await response.json();
        if (!response.ok || json.success === false) throw new Error(json.message || `HTTP ${response.status}`);
        this.frames = json.data.frames;
        this.goTo(0);
      } catch (err) {
        this.replayError = err.message;
      } finally {
        this.replayLoading = false;
      }
    },

    goTo(index) {
      if (!this.frames.length) return;
      this.index = Math.max(0, Math.min(this.frames.length - 1, index));
      const frame = this.frames[this.index];
      this.applyState(frame);
      this.decision = frame.decision || null;
      if (this.atEnd()) this.pause();
    },

    atEnd() { return this.index >= this.frames.length - 1; },
    next() { this.goTo(this.index + 1); },
    prev() { this.goTo(this.index - 1); },
    restart() { this.pause(); this.goTo(0); },

    play() {
      if (this.atEnd()) this.goTo(0);
      this.playing = true;
      window.clearInterval(this.timer);
      this.timer = window.setInterval(() => this.next(), this.speedMs);
    },

    pause() {
      this.playing = false;
      window.clearInterval(this.timer);
      this.timer = null;
    },

    toggle() { this.playing ? this.pause() : this.play(); },

    setSpeed(ms) {
      this.speedMs = Number(ms);
      if (this.playing) this.play();
    },

    /** Las 3 acciones más probables que daba el bot, p. ej. «call 68% · bet 24% · fold 7%». */
    decisionOdds() {
      const odds = this.decision?.distribution;
      if (!odds) return '';
      return Object.entries(odds).sort((a, b) => b[1] - a[1]).slice(0, 4).map(([action, p]) => `${action} ${Math.round(p)}%`).join(' · ');
    },
  };
}
