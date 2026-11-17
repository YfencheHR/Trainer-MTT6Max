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

// Componente para mostrar rangos dinámicos de bots
window.botsActionRanges = function(handId) {
  return {
    handId: handId,
    activeBots: [],

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

    // Rejilla 13x13: pares en la diagonal, suited encima, offsuit debajo. Cada celda es la mano en texto con el fondo
    // dividido en los colores de sus acciones (fold gris, call azul, raise rojo, check verde), como las tablas de los rivales.
    cells(bot) {
      const R = ["A","K","Q","J","T","9","8","7","6","5","4","3","2"];
      const colors = ["#4b5563", "#1d4ed8", "#b91c1c", "#15803d"];
      const out = [];
      for (let i = 0; i < 13; i++) {
        for (let j = 0; j < 13; j++) {
          const hand = i === j ? R[i] + R[i] : (i < j ? R[i] + R[j] + "s" : R[j] + R[i] + "o");
          const f = (bot.ranges || {})[hand];
          const total = f ? f.reduce((a, b) => a + b, 0) : 0;
          let bg = "#0f1419";
          if (total > 0) {
            let pos = 0;
            const stops = [];
            f.forEach((v, k) => {
              if (v <= 0) return;
              const end = pos + v * 100 / total;
              stops.push(colors[k] + " " + pos.toFixed(2) + "% " + end.toFixed(2) + "%");
              pos = end;
            });
            bg = "linear-gradient(90deg, " + stops.join(", ") + ")";
          }
          out.push({ hand, bg, active: total > 0, title: f ? hand + " · F" + f[0] + " C" + f[1] + " R" + f[2] + " K" + f[3] : hand });
        }
      }
      return out;
    },

    // Solo se listan los bots que han entrado en la mano (backend: bots_ranges).
    // Si luego foldean se mantiene el rango de su última acción antes del fold.
    updateBots() {
      const players = this.$store.handStatistics?.data?.players || [];
      const botsRanges = this.$store.gameState?.botsRanges || {};

      this.activeBots = players
        .filter(player => player.is_bot && botsRanges[player.id])
        .map(player => ({
          id: player.id,
          name: player.name,
          position: player.position,
          is_folded: player.is_folded,
          is_all_in: player.is_all_in,
          action: botsRanges[player.id].action ?? null,
          ranges: botsRanges[player.id].ranges ?? null,
        }));
    }
  };
}

Alpine.start()

