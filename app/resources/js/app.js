import './bootstrap'
import Alpine from 'alpinejs'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { gameTable } from './Components/game-table.js'

window.Pusher = Pusher
window.Alpine = Alpine
window.gameTable = gameTable
window.createGameEcho = () => {
  const config = JSON.parse(document.querySelector('meta[name="reverb-config"]')?.content || '{}')
  const key = config.key
  if (!key) throw new Error('Falta configurar REVERB_APP_KEY.')

  const scheme = config.scheme || window.location.protocol.slice(0, -1)
  const port = Number(config.port || (scheme === 'https' ? 443 : 8080))

  return new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: config.host || window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
  })
}

// Log compartido entre la mesa y el panel lateral
Alpine.store('gameLog', {
  history: [], // manos anteriores de la mesa (se cargan al abrir la página)
  entries: [], // mano en curso
  set(list) {
    if (Array.isArray(list)) this.entries = list;
  },
  setHistory(list) {
    if (Array.isArray(list)) this.history = list;
  },
  /** Historial + mano en curso, de más antiguo a más reciente */
  all() {
    return [...this.history, ...this.entries];
  },
  /** "A♠" → { value: 'A', suit: '♠', color } para pintar fichas de carta en el log */
  card(c) {
    const s = String(c || '');
    const suit = s.slice(-1);
    const colors = { '♠': '#111827', '♣': '#15803d', '♥': '#dc2626', '♦': '#2563eb' };
    return { value: s.slice(0, -1), suit, color: colors[suit] || '#111827' };
  },
})

// Datos del torneo para el panel lateral
Alpine.store('tournamentInfo', {
  data: {},
  set(d) {
    if (d) this.data = d;
  },
})

Alpine.store('handStatistics', {
  data: {},
  set(state) {
    if (!state) return;
    this.data = {
      hand_id: state.hand_id ?? null,
      hand_number: state.hand_number ?? null,
      players: Array.isArray(state.players) ? state.players : [],
      pot: Number(state.pot) || 0,
      current_street: state.current_street || '',
      status: state.status || '',
      big_blind: Number(state.big_blind) || 0,
    };
  },
})

// Estado del juego en tiempo real (jugador actual, posición, etc.)
Alpine.store('gameState', {
  currentPlayerId: null,
  currentActorBotId: null,
  currentPosition: null,
  board: [],
  street: 'preflop',
  dynamicRanges: null,
  botsRanges: {},

  set(state) {
    if (!state) return;
    this.currentPlayerId = state.current_player_id ?? null;
    this.currentPosition = state.current_player_position ?? null;
    this.board = Array.isArray(state.board) ? state.board : [];
    this.street = state.current_street || 'preflop';
    this.dynamicRanges = state.dynamic_ranges ?? null;
    this.botsRanges = (state.bots_ranges && !Array.isArray(state.bots_ranges)) ? state.bots_ranges : {};

    // Si el jugador actual es un bot, extraer su ID
    if (Array.isArray(state.players)) {
      const currentPlayer = state.players.find(p => p.id === this.currentPlayerId);
      this.currentActorBotId = currentPlayer?.bot_id ?? null;
    }
  },
})

// Componente para mostrar los rangos de los rivales: una lista compacta (una fila por rival) y UNA sola matriz, la del rival seleccionado.
// La lista se actualiza en el sitio (mismos objetos) para que el panel no salte ni se redibuje entero en cada acción.
window.botsActionRanges = function(handId) {
  const RANKS = ["A","K","Q","J","T","9","8","7","6","5","4","3","2"];
  const COLORS = ["#4b5563", "#1d4ed8", "#b91c1c", "#15803d"];   // fold, call, raise, check
  return {
    handId: handId,
    activeBots: [],
    selectedId: null,     // rival cuya matriz se ve
    pinned: false,        // true si el usuario eligió uno: deja de seguir al que actúa

    init() {
      this.$watch(
        () => ({
          players: this.$store.handStatistics?.data?.players,
          botsRanges: this.$store.gameState?.botsRanges,
        }),
        () => this.updateBots(),
        { deep: true }
      );

      setTimeout(() => this.updateBots(), 100);
    },

    selected() {
      return this.activeBots.find(bot => bot.id === this.selectedId) || null;
    },

    select(bot) {
      this.selectedId = bot.id;
      this.pinned = true;
    },

    // Peso de cada mano en combinaciones: par 6, suited 4, offsuit 12 (1326 en total)
    weight(hand) {
      return hand.length === 2 ? 6 : (hand.endsWith("s") ? 4 : 12);
    },

    // % del total de combinaciones con cada acción (fold, call, raise, check) y % de manos que entran al bote
    summary(bot) {
      const share = [0, 0, 0, 0];
      for (const [hand, f] of Object.entries(bot.ranges || {})) {
        const total = f.reduce((a, b) => a + b, 0);
        if (total <= 0) continue;
        const w = this.weight(hand);
        f.forEach((v, k) => { share[k] += w * (v / total); });
      }
      const pct = share.map(v => Math.round(1000 * v / 1326) / 10);
      return { fold: pct[0], call: pct[1], raise: pct[2], check: pct[3], played: Math.round(10 * (pct[1] + pct[2] + pct[3])) / 10 };
    },

    // Rejilla 13x13: pares en la diagonal, suited encima, offsuit debajo. Cada celda es la mano en texto con el fondo
    // dividido en los colores de sus acciones (fold gris, call azul, raise rojo, check verde), como las tablas de los rivales.
    cells(bot) {
      const out = [];
      for (let i = 0; i < 13; i++) {
        for (let j = 0; j < 13; j++) {
          const hand = i === j ? RANKS[i] + RANKS[i] : (i < j ? RANKS[i] + RANKS[j] + "s" : RANKS[j] + RANKS[i] + "o");
          const f = bot ? (bot.ranges || {})[hand] : null;
          const total = f ? f.reduce((a, b) => a + b, 0) : 0;
          let bg = "#0f1419";
          if (total > 0) {
            let pos = 0;
            const stops = [];
            f.forEach((v, k) => {
              if (v <= 0) return;
              const end = pos + v * 100 / total;
              stops.push(COLORS[k] + " " + pos.toFixed(2) + "% " + end.toFixed(2) + "%");
              pos = end;
            });
            bg = "linear-gradient(90deg, " + stops.join(", ") + ")";
          }
          out.push({ hand, bg, active: total > 0, title: f ? hand + " · F" + f[0] + " C" + f[1] + " R" + f[2] + " K" + f[3] : hand });
        }
      }
      return out;
    },

    // Solo se listan los rivales que han entrado en la mano (backend: bots_ranges). Si luego foldean se mantiene el rango de su
    // última acción antes del fold. Los objetos se reutilizan: así la lista no se reconstruye en cada actualización.
    updateBots() {
      const players = this.$store.handStatistics?.data?.players || [];
      const botsRanges = this.$store.gameState?.botsRanges || {};
      const current = this.$store.gameState?.currentPlayerId ?? null;
      const next = players.filter(player => player.is_bot && botsRanges[player.id]);

      const known = new Map(this.activeBots.map(bot => [bot.id, bot]));
      const list = next.map(player => {
        const bot = known.get(player.id) || { id: player.id };
        Object.assign(bot, {
          name: player.name,
          position: player.position,
          is_folded: player.is_folded,
          is_all_in: player.is_all_in,
          action: botsRanges[player.id].action ?? null,
          ranges: botsRanges[player.id].ranges ?? null,
        });
        bot.summary = this.summary(bot);
        return bot;
      });
      const same = list.length === this.activeBots.length && list.every((bot, i) => bot === this.activeBots[i]);
      if (!same) this.activeBots = list;

      // Nueva mano o el seleccionado ya no está: se suelta la selección manual
      if (this.selectedId !== null && !list.some(bot => bot.id === this.selectedId)) {
        this.selectedId = null;
        this.pinned = false;
      }
      // Sin selección manual se sigue al rival al que le toca; si no hay, se queda en el último elegido o en el primero
      if (!this.pinned) {
        const acting = list.find(bot => bot.id === current);
        this.selectedId = (acting || list.find(bot => bot.id === this.selectedId) || list[0] || {}).id ?? null;
      }
    }
  };
}

Alpine.start()

