import { GameAPI } from './game-api.js';

/**
 * Componente Alpine.js para la mesa de juego
 */
export function gameTable(handId, userId, initialState = null) {
  const s = initialState || {};
  const hero = (s.players || []).find(p => p.is_current === true);
  const autoAdvanceStorageKey = `mtt-auto-advance-${s.tournament?.id ?? handId}`;
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
    autoAdvanceStorageKey,
    autoAdvance: false,
    handAdvancePending: false,
    currentPlayerId: null,
    currentBet: 0,
    legal: s.hero_actions || null,
    lastAction: s.last_action || null,
    bigBlind: Number.parseInt(s.big_blind) || 100,
    minRaiseTo: 0,
    raiseAmount: 0,
    nextHandLoading: false,
    nextHandProgress: '',
    botDelayMs: 900,
    showAllCards: false,
    echo: null,
    handChannel: null,
    handChannelName: null,
    socketConnectedHandler: null,
    lastSyncedSocketId: null,

    /**
     * Inicialización del componente
     */
    init() {
      // El estado inicial llega embebido desde Blade (GameTableManager).
      this.autoAdvance = window.localStorage.getItem(this.autoAdvanceStorageKey) === 'true';
      this.loading = false;
      this.clampRaise(); // importe de subida inicial = mínimo legal
      if (window.Alpine?.store('gameLog')) window.Alpine.store('gameLog').set(s.log);
      if (window.Alpine?.store('tournamentInfo')) window.Alpine.store('tournamentInfo').set(s.tournament);
      if (window.Alpine?.store('handStatistics')) window.Alpine.store('handStatistics').set(s);
      this.connectSocket();
      // Historial de las manos anteriores de la mesa (para que el log no se pierda al recargar)
      new GameAPI().getLogHistory(this.handId)
        .then(d => window.Alpine?.store('gameLog')?.setHistory(d.log))
        .catch(err => console.error('No se pudo cargar el historial del log:', err));
      // Si al cargar la mesa le toca a un bot, se le pide su acción al servidor.
      this.$nextTick(() => this.runBotTurns());
    },
    connectSocket() {
      try {
        this.echo = window.createGameEcho();
        const connection = this.echo.connector.pusher.connection;
        this.socketConnectedHandler = () => {
          if (connection.socket_id === this.lastSyncedSocketId) return;
          this.lastSyncedSocketId = connection.socket_id;
          void this.syncHandState();
        };
        connection.bind('connected', this.socketConnectedHandler);
        this.subscribeToHand(this.handId);
        if (connection.state === 'connected') this.socketConnectedHandler();
      } catch (err) {
        this.error = 'No se pudo conectar a la mesa en tiempo real: ' + err.message;
      }
    },
    subscribeToHand(handId) {
      if (!this.echo) return;
      if (this.handChannelName) this.echo.leave(this.handChannelName);
      this.handChannelName = `game-hand.${handId}`;
      this.handChannel = this.echo.channel(this.handChannelName)
        .listen('.game.hand.updated', event => this.receiveSocketState(event));
    },
    async syncHandState() {
      const handId = this.handId;
      try {
        const state = await new GameAPI().getState(handId);
        if (handId === this.handId) this.receiveSocketState({ state });
      } catch (err) {
        this.error = 'No se pudo sincronizar la mesa: ' + err.message;
      }
    },
    receiveSocketState(event) {
      if (!event?.state) return;
      const nextHandId = Number(event.state.hand_id);
      if (nextHandId && nextHandId < Number(this.handId)) return;
      if (nextHandId && nextHandId !== this.handId) {
        this.handId = nextHandId;
        this.subscribeToHand(nextHandId);
        new GameAPI().getLogHistory(nextHandId)
          .then(history => window.Alpine?.store('gameLog')?.setHistory(history.log))
          .catch(err => console.error('No se pudo cargar el historial del log:', err));
      }
      this.applyState(event.state);
      this.error = null;
      if (event.finished) {
        this.error = event.message || 'El torneo ha terminado';
        this.disconnectSocket();
        return;
      }
      if (this.isBotTurn() || (this.gameCompleted && this.shouldAutoAdvance())) void this.runBotTurns();
    },
    disconnectSocket() {
      if (!this.echo) return;
      if (this.socketConnectedHandler) {
        this.echo.connector.pusher.connection.unbind('connected', this.socketConnectedHandler);
      }
      if (this.handChannelName) this.echo.leave(this.handChannelName);
      this.echo.disconnect();
      this.echo = null;
      this.handChannel = null;
      this.handChannelName = null;
      this.lastSyncedSocketId = null;
    },
    destroy() {
      this.disconnectSocket();
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
      if (window.Alpine?.store('handStatistics')) window.Alpine.store('handStatistics').set(state);
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
      return Boolean(turn?.is_bot);
    },

    isSpectatorMode() {
      return this.players.length > 0 && !this.players.some(player => player.is_current === true);
    },

    shouldAutoAdvance() {
      return this.isSpectatorMode() || this.autoAdvance;
    },

    shouldShowNextHandButton() {
      return !this.shouldAutoAdvance();
    },

    toggleAutoAdvance() {
      this.autoAdvance = !this.autoAdvance;
      window.localStorage.setItem(this.autoAdvanceStorageKey, String(this.autoAdvance));
      if (this.autoAdvance && this.gameCompleted) void this.runBotTurns();
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
      const handId = this.handId;
      const api = new GameAPI();
      const playNextBotTurn = async () => {
        if (this.handId !== handId) return;
        if (!this.isBotTurn()) return;
        await new Promise(r => setTimeout(r, this.botDelayMs));
        if (this.handId !== handId || !this.isBotTurn()) return;
        try {
          const state = await api.botAction(handId);
          if (this.handId === handId) this.applyState(state);
        } catch (err) {
          if (this.handId !== handId) return;
          // Si el turno ya no era de un bot (otra pestaña, doble petición…) se resincroniza
          await this.loadHandState();
          if (this.isBotTurn()) throw err;
        }
        await playNextBotTurn();
      };
      try {
        await playNextBotTurn();
      } catch (err) {
        this.error = 'Error: ' + err.message;
        console.error('Error en el turno del bot:', err);
      } finally {
        this.botWaiting = false;
      }

      if (this.handId !== handId) {
        if (this.isBotTurn() || (this.gameCompleted && this.shouldAutoAdvance())) void this.runBotTurns();
        return;
      }

      if (this.gameCompleted && this.shouldAutoAdvance() && !this.handAdvancePending) {
        this.handAdvancePending = true;
        try {
          await new Promise(resolve => setTimeout(resolve, 1800));
          if (this.gameCompleted && this.shouldAutoAdvance() && !this.nextHandLoading) {
            await this.nextHand();
          }
        } finally {
          this.handAdvancePending = false;
        }
      }
    },
    async playerFold() { await this.executePlayerAction('fold', 0); },
    async playerCall() { await this.executePlayerAction('call', 0); },
    async playerCheck() { await this.executePlayerAction('check', 0); },
    /** Mantiene el importe de subida dentro de [mínimo, máximo] legales. */
    clampRaise() {
      const r = this.legal?.raise;
      if (!r) return;
      const v = Number.parseInt(this.raiseAmount) || 0;
      if (v < r.min || v > r.max) this.raiseAmount = Math.min(Math.max(v, r.min), r.max);
    },

    /** Atajos de tamaño: fracción del bote tras igualar (0 = mínimo, 'max' = all-in). */
    setRaiseSize(frac) {
      const r = this.legal?.raise;
      if (!r) return;
      if (frac === 'max') { this.raiseAmount = r.max; return; }
      if (frac === 0) { this.raiseAmount = r.min; return; }
      const target = this.currentBet + Math.round(frac * (this.pot + (this.legal.to_call || 0)));
      const amountToAdd = target - this.heroBet();
      this.raiseAmount = Math.min(Math.max(amountToAdd, r.min), r.max);
    },

    heroBet() {
      const hero = this.players.find(player => player.is_current === true);
      return Number(hero?.bet) || 0;
    },

    raiseTarget() {
      return this.heroBet() + (Number(this.raiseAmount) || 0);
    },

    async playerRaise() { await this.executePlayerAction('raise', this.raiseTarget()); },
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
      void this.runBotTurns();
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
        const hasMultipleTables = (info.tables || []).length > 1;
        if (hasMultipleTables) {
          const others = info.other_tables || [];
          await others.reduce((pending, table, i) => pending.then(() => {
            this.nextHandProgress = `Mesa ${table.number} (${i + 1}/${others.length})`;
            return api.resolveTable(info.id, table.id, info.hand_number);
          }), Promise.resolve());
          this.nextHandProgress = 'Nivelando mesas…';
        } else {
          this.nextHandProgress = 'Preparando siguiente mano…';
        }

        const result = await api.nextHand(this.handId);

        if (result.finished) {
          this.error = result.message || 'El torneo ha terminado';
          this.disconnectSocket();
          return;
        }

        if (!result.hand_id) throw new Error('El servidor no devolvió la siguiente mano.');

        this.nextHandProgress = 'Cargando nueva mano…';
        const [state, history] = await Promise.all([
          api.getState(result.hand_id),
          api.getLogHistory(result.hand_id),
        ]);
        this.handId = result.hand_id;
        this.subscribeToHand(result.hand_id);
        this.applyState(state);
        window.Alpine?.store('gameLog')?.setHistory(history.log);
        this.error = null;
        void this.runBotTurns();
      } catch (err) {
        this.error = err.message;
      } finally {
        this.nextHandLoading = false;
        this.nextHandProgress = '';
      }
    },
    async switchTable(table) {
      if (this.nextHandLoading || !table?.id || table.is_hero || table.players < 2) return;

      const info = window.Alpine?.store('tournamentInfo')?.data || {};
      if (!info.hero_eliminated) {
        this.error = 'Solo puedes cambiar de mesa cuando estés eliminado.';
        return;
      }

      this.nextHandLoading = true;
      this.nextHandProgress = `Resolviendo Mesa ${table.number}…`;
      this.error = null;
      let switched = false;

      try {
        const result = await new GameAPI().resolveTable(info.id, table.id, info.hand_number);
        const nextHandId = Number(result.hand_id);
        if (!nextHandId) throw new Error('El servidor no devolvió la mano de la mesa seleccionada.');

        this.nextHandProgress = 'Cargando mesa…';
        const [state, history] = await Promise.all([
          new GameAPI().getState(nextHandId),
          new GameAPI().getLogHistory(nextHandId),
        ]);
        this.handId = nextHandId;
        this.subscribeToHand(nextHandId);
        this.applyState(state);
        window.Alpine?.store('gameLog')?.setHistory(history.log);
        this.error = null;
        switched = true;
      } catch (err) {
        this.error = err.message;
      } finally {
        this.nextHandLoading = false;
        this.nextHandProgress = '';
      }

      if (switched) void this.runBotTurns();
    },
    canPlayerAction() {
      const hero = this.players.find(p => p.is_current === true);
      return Boolean(hero?.is_turn && !this.gameCompleted && this.legal);
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

    getPositionStyle(displayIdx) {
      const numPlayers = Math.max(2, this.players.length);
      const angle = (90 + displayIdx * 360 / numPlayers) * Math.PI / 180;
      const left = 50 + 44 * Math.cos(angle);
      const top = 50 + 50 * Math.sin(angle);
      const display = displayIdx < this.players.length ? 'block' : 'none';

      return `display: ${display}; position: absolute; left: ${left.toFixed(2)}%; top: ${top.toFixed(2)}%; width: clamp(118px, 18vw, 142px); transform: translate(-50%, -50%); z-index: 3;`;
    },

    syncBetChip(element, displayIdx, player) {
      const bet = Number(player?.bet) || 0;
      const label = this.formatBetBB(bet);
      const numPlayers = Math.max(2, this.players.length);
      const angle = (90 + displayIdx * 360 / numPlayers) * Math.PI / 180;
      const cos = Math.cos(angle);
      const radius = Math.abs(cos) < 0.01 ? 0.52 : 0.58;

      element.textContent = label;
      element.style.left = `${(50 + 44 * radius * cos).toFixed(2)}%`;
      element.style.top = `${(50 + 50 * radius * Math.sin(angle)).toFixed(2)}%`;
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

    formatBetBB(amount) {
      const chips = Number(amount) || 0;
      const bb = chips / (this.bigBlind || 1);
      if (chips > 0 && Math.round(bb * 10) === 0) return '<0,1bb';
      return this.formatBB(chips);
    },

    formatChips(amount) {
      return Number(amount || 0).toLocaleString('es-ES');
    },
  };
}
