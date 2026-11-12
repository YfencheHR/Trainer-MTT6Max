import { GameAPI } from './game-api.js';

/**
 * Componente Alpine.js para la mesa de juego
 */
export function gameTable(handId, userId, initialState = null) {
  const s = initialState || {};
  const hero = (s.players || []).find(p => p.is_current === true);
  return {
    handId,
    currentUserId: userId,
    loading: false,
    error: null,
    players: Array.isArray(s.players) ? s.players : [],
    board: Array.isArray(s.board) ? s.board : [],
    pot: Number.parseInt(s.pot) || 0,
    currentStreet: String(s.current_street || 'preflop'),
    heroStack: hero ? Number.parseInt(hero.stack) : 0,
    isAllIn: Boolean(s.is_all_in),
    gameCompleted: s.status === 'completed',
    eliminatedPlayers: [],
    actionInProgress: false,
    botWaiting: false,
    currentPlayerId: null,
    currentBet: 0,
    legal: s.hero_actions || null,
    lastAction: s.last_action || null,
    bigBlind: Number.parseInt(s.big_blind) || 100,
    minRaiseTo: 0,
    raiseTo: 0,
    nextHandLoading: false,
    nextHandProgress: '',
    botDelayMs: 900,
    showAllCards: false,

    /**
     * Inicialización del componente
     */
    init() {
      // El estado inicial llega embebido desde Blade (GameTableManager).
      this.loading = false;
      this.clampRaise(); // importe de subida inicial = mínimo legal
      if (window.Alpine?.store('gameLog')) window.Alpine.store('gameLog').set(s.log);
      if (window.Alpine?.store('tournamentInfo')) window.Alpine.store('tournamentInfo').set(s.tournament);
      // Historial de las manos anteriores de la mesa (para que el log no se pierda al recargar)
      new GameAPI().getLogHistory(this.handId)
        .then(d => window.Alpine?.store('gameLog')?.setHistory(d.log))
        .catch(err => console.error('No se pudo cargar el historial del log:', err));
      // Si al cargar la mesa le toca a un bot, se le pide su acción al servidor.
      this.$nextTick(() => this.runBotTurns());
    },
    /**
     * Aplica un estado recibido del servidor
     */
    applyState(state) {
      if (!state) return;
      this.players = Array.isArray(state.players) ? [...state.players] : [];
      this.board = Array.isArray(state.board) ? [...state.board] : [];
      this.pot = Number.parseInt(state.pot) || 0;
      this.currentStreet = String(state.current_street || 'preflop');
      this.isAllIn = Boolean(state.is_all_in);
      this.gameCompleted = state.status === 'completed';
      this.currentPlayerId = state.current_player_id ?? null;
      if (window.Alpine?.store('gameLog')) window.Alpine.store('gameLog').set(state.log);
      if (window.Alpine?.store('tournamentInfo')) window.Alpine.store('tournamentInfo').set(state.tournament);
      this.currentBet = Number.parseInt(state.current_bet) || 0;
      this.legal = state.hero_actions || null;
      this.lastAction = state.last_action || null;
      this.bigBlind = Number.parseInt(state.big_blind) || this.bigBlind;
      this.minRaiseTo = Number.parseInt(state.min_raise_to) || 0;
      this.clampRaise();
      const hero = this.players.find(p => p.is_current === true);
      this.heroStack = hero ? Number.parseInt(hero.stack) : 0;
    },

    async loadHandState() {
      this.applyState(await new GameAPI().getState(this.handId));
    },

    /** ¿Le toca ahora a un bot según el último estado del servidor? */
    isBotTurn() {
      if (this.gameCompleted) return false;
      const turn = this.players.find(p => p.is_turn);
      return Boolean(turn && turn.is_bot);
    },

    /**
     * Flujo de la mano: mientras el siguiente en actuar sea un bot, la mesa le pide
     * su acción al servidor. El servidor decide, la guarda como acción de la mano y
     * responde con el estado nuevo; la mesa lo pinta y sigue con el siguiente.
     * Se detiene cuando le toca al héroe o la mano termina.
     */
    async runBotTurns() {
      if (this.botWaiting) return;
      this.botWaiting = true;
      const api = new GameAPI();
      try {
        while (this.isBotTurn()) {
          await new Promise(r => setTimeout(r, this.botDelayMs));
          try {
            this.applyState(await api.botAction(this.handId));
          } catch (err) {
            // Si el turno ya no era de un bot (otra pestaña, doble petición…) se resincroniza
            await this.loadHandState();
            if (this.isBotTurn()) throw err;
          }
        }
      } catch (err) {
        this.error = 'Error: ' + err.message;
        console.error('Error en el turno del bot:', err);
      } finally {
        this.botWaiting = false;
      }
    },
    async playerFold() { await this.executePlayerAction('fold', 0); },
    async playerCall() { await this.executePlayerAction('call', 0); },
    async playerCheck() { await this.executePlayerAction('check', 0); },
    /** Mantiene el importe de subida dentro de [mínimo, máximo] legales. */
    clampRaise() {
      const r = this.legal?.raise;
      if (!r) return;
      const v = Number.parseInt(this.raiseTo) || 0;
      if (v < r.min || v > r.max) this.raiseTo = Math.min(Math.max(v, r.min), r.max);
    },

    /** Atajos de tamaño: fracción del bote tras igualar (0 = mínimo, 'max' = all-in). */
    setRaiseSize(frac) {
      const r = this.legal?.raise;
      if (!r) return;
      if (frac === 'max') { this.raiseTo = r.max; return; }
      if (frac === 0) { this.raiseTo = r.min; return; }
      const target = this.currentBet + Math.round(frac * (this.pot + (this.legal.to_call || 0)));
      this.raiseTo = Math.min(Math.max(target, r.min), r.max);
    },

    async playerRaise() { await this.executePlayerAction('raise', Number.parseInt(this.raiseTo) || 0); },
    async playerAllIn() { await this.executePlayerAction('allin', 0); },

    async executePlayerAction(action, amount) {
      if (this.actionInProgress || !this.canPlayerAction()) return;
      this.actionInProgress = true;
      try {
        this.applyState(await new GameAPI().playerAction(this.handId, action, amount));
        this.error = null;
      } catch (err) {
        this.error = 'Error: ' + err.message;
        console.error('Action error:', err);
      } finally {
        this.actionInProgress = false;
      }
      this.runBotTurns();
    },

    /**
     * Siguiente mano = siguiente RONDA del torneo:
     *  1. una petición por cada mesa secundaria (el servidor juega su mano y devuelve el resultado),
     *  2. al terminar todas, el servidor procesa eliminaciones, nivela/une mesas y crea la mano del héroe.
     */
    async nextHand() {
      if (this.nextHandLoading) return;
      this.nextHandLoading = true;
      this.error = null;
      const api = new GameAPI();
      const info = window.Alpine?.store('tournamentInfo')?.data || {};

      try {
        const others = info.other_tables || [];
        await others.reduce((pending, table, i) => pending.then(() => {
          this.nextHandProgress = `Mesa ${table.number} (${i + 1}/${others.length})`;
          return api.resolveTable(info.id, table.id, info.hand_number);
        }), Promise.resolve());

        this.nextHandProgress = 'Nivelando mesas…';
        const result = await api.nextHand(this.handId);

        if (result.finished) {
          this.error = result.message || 'El torneo ha terminado';
          this.nextHandLoading = false;
          this.nextHandProgress = '';
          return;
        }
        window.location.reload();
      } catch (err) {
        this.error = err.message;
        this.nextHandLoading = false;
        this.nextHandProgress = '';
      }
    },
    canPlayerAction() {
      const hero = this.players.find(p => p.is_current === true);
      return Boolean(hero && hero.is_turn && !this.gameCompleted && this.legal);
    },

    /**
     * Obtiene un jugador de forma segura (con valores por defecto)
     */
    getSafePlayer(displayIdx) {
      const player = this.getPlayerAtPosition(displayIdx);
      if (!player) {
        return { id: null, name: '', stack: 0, is_bot: false, is_current: false, hole_cards: [] };
      }
      return player;
    },

    /**
     * Obtiene un jugador por posición
     */
    getPlayerAtPosition(displayIdx) {
      // Slot 0 = héroe (abajo); el resto sigue el orden de la mesa
      const n = this.players.length;
      if (displayIdx < 0 || displayIdx >= n) return null;
      const heroIdx = Math.max(0, this.players.findIndex(p => p.is_current === true));
      return this.players[(heroIdx + displayIdx) % n] || null;
    },

    /**
     * Parsea una carta para obtener valor, palo y color
     */
    parseCard(cardStr) {
      if (!cardStr) return { value: '', suit: '', color: '#000' };
      const value = cardStr.substring(0, cardStr.length - 1);
      const suit = cardStr.substring(cardStr.length - 1);
      const colors = {
        '♠': '#000000',
        '♣': '#228B22',
        '♥': '#FF0000',
        '♦': '#0066FF'
      };
      return { value, suit, color: colors[suit] || '#000' };
    },

    /**
     * Formatea chips a formato legible
     */
    /** Fichas → big blinds, p. ej. 0,5bb / 13,4bb */
    formatBB(amount) {
      const bb = (Number(amount) || 0) / (this.bigBlind || 1);
      return bb.toLocaleString('es-ES', { maximumFractionDigits: 1 }) + 'bb';
    },

    formatChips(amount) {
      return Number(amount || 0).toLocaleString('es-ES');
    },
  };
}
