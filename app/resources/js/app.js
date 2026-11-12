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

Alpine.start()

console.log('Livewire + Alpine.js initialized')
