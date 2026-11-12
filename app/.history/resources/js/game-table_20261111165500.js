/**
 * Controlador Alpine.js para la mesa de poker
 */
function gameTable(handId, currentUserId) {
    return {
        handId: handId,
        currentUserId: currentUserId,

        // Estado de la mano
        hand: {
            id: null,
            current_street: 'preflop',
            current_player_id: null,
            pot: 0,
            board: [],
            players: [],
            hand_status: 'active',
            is_all_in: false,
            actions: []
        },

        // UI State
        loading: true,
        error: null,
        actionInProgress: false,
        botWaiting: false,
        botWaitSeconds: 0,
        eliminatedPlayers: [],
        gameCompleted: false,

        // Instancia API
        api: null,

        async init() {
            try {
                this.api = new GameAPI(this.handId);
                await this.loadHandState();

                // Polling para actualizar estado
                setInterval(() => this.updateGameState(), 2000);
            } catch (e) {
                this.error = 'Error inicializando la mesa: ' + e.message;
                console.error(e);
            }
        },

        async loadHandState() {
            try {
                this.loading = true;
                this.error = null;

                const response = await this.api.getState();
                if (response.success) {
                    this.hand = response.data.data;
                    this.loading = false;

                    // Si hay un bot a jugar, esperar un poco
                    this.checkForBotAction();
                } else {
                    throw new Error(response.message || 'Error obteniendo estado');
                }
            } catch (e) {
                this.error = e.message;
                console.error(e);
            }
        },

        async updateGameState() {
            if (this.gameCompleted || this.loading) return;

            try {
                const response = await this.api.getState();
                if (response.success) {
                    this.hand = response.data.data;

                    if (this.hand.hand_status === 'completed') {
                        this.gameCompleted = true;
                        this.showEliminatedPlayers();
                    } else {
                        this.checkForBotAction();
                    }
                }
            } catch (e) {
                console.error('Error actualizando estado:', e);
            }
        },

        checkForBotAction() {
            const currentPlayer = this.hand.players.find(p => p.id === this.hand.current_player_id);

            if (currentPlayer?.is_bot && !this.actionInProgress) {
                this.executeBotAction();
            }
        },

        async executeBotAction() {
            if (this.actionInProgress) return;

            this.actionInProgress = true;
            this.botWaiting = true;

            // Esperar 1.5 segundos antes de ejecutar acción del bot
            await new Promise(resolve => setTimeout(resolve, 1500));

            try {
                const response = await this.api.getBotAction();

                if (response.success) {
                    this.hand = response.data.data;

                    if (this.hand.hand_status === 'completed') {
                        this.gameCompleted = true;
                        this.showEliminatedPlayers();
                    } else {
                        // Esperar un poco y si hay otro bot, ejecutar
                        await new Promise(resolve => setTimeout(resolve, 500));
                        this.checkForBotAction();
                    }
                }
            } catch (e) {
                this.error = 'Error en acción del bot: ' + e.message;
                console.error(e);
            } finally {
                this.actionInProgress = false;
                this.botWaiting = false;
            }
        },

        async playerFold() {
            await this.sendAction('fold', 0);
        },

        async playerCall() {
            // Calcular cantidad a llamar
            const currentPlayer = this.hand.players.find(p => p.id === this.currentUserId);
            const amountToCall = this.hand.current_bet - (currentPlayer?.current_bet || 0);

            await this.sendAction('call', amountToCall);
        },

        async playerCheck() {
            await this.sendAction('check', 0);
        },

        async playerRaise(raiseAmount) {
            await this.sendAction('raise', raiseAmount);
        },

        async playerAllIn() {
            const currentPlayer = this.hand.players.find(p => p.id === this.currentUserId);
            await this.sendAction('allin', currentPlayer?.stack || 0);
        },

        async sendAction(action, amount) {
            if (this.actionInProgress) return;

            this.actionInProgress = true;

            try {
                const response = await this.api.playerAction(action, amount);

                if (response.success) {
                    this.hand = response.data.data;

                    if (this.hand.hand_status === 'completed') {
                        this.gameCompleted = true;
                        this.showEliminatedPlayers();
                    } else {
                        // Esperar un poco para que sea obvio que cambió
                        await new Promise(resolve => setTimeout(resolve, 500));
                        this.checkForBotAction();
                    }
                }
            } catch (e) {
                this.error = 'Error enviando acción: ' + e.message;
                console.error(e);
            } finally {
                this.actionInProgress = false;
            }
        },

        async resolveAllIn() {
            if (!this.hand.is_all_in) return;

            try {
                const response = await this.api.resolveAllIn();

                if (response.success) {
                    this.hand = response.data.data;
                    this.eliminatedPlayers = response.data.eliminated_players || [];

                    if (this.hand.hand_status === 'completed') {
                        this.gameCompleted = true;
                    }
                }
            } catch (e) {
                this.error = 'Error resolviendo all-in: ' + e.message;
                console.error(e);
            }
        },

        showEliminatedPlayers() {
            // Los jugadores eliminados ya están en eliminatedPlayers
            console.log('Jugadores eliminados:', this.eliminatedPlayers);
        },

        // ========== HELPERS PARA LA VISTA ==========

        isCurrentPlayer(playerId) {
            return playerId === this.currentUserId;
        },

        isActionPlayer(playerId) {
            return playerId === this.hand.current_player_id;
        },

        canPlayerAction() {
            return this.hand.current_player_id === this.currentUserId && !this.actionInProgress;
        },

        isHeroPlayer(player) {
            return player.id === this.currentUserId;
        },

        getPositionBadge(index, totalPlayers) {
            const positions = ['BTN', 'SB', 'BB', 'UTG', 'UTG+1', 'CO'];
            return positions[index % positions.length] || '';
        },

        getPlayerBorderColor(player) {
            // Colores según estado
            if (player.is_folded) return '#6b7280';      // Gray
            if (player.is_all_in) return '#f59e0b';       // Orange
            if (player.stack <= 0) return '#6b7280';      // Gray (eliminado)
            if (this.hand.current_player_id === player.id) return '#10b981';  // Green (turno)
            return '#60a5fa';                            // Blue (inactivo)
        },

        getPlayerPositionStyle(index, totalPlayers) {
            const positionMap = {
                2: [
                    { bottom: '5%', left: '50%', transform: 'translateX(-50%)' },
                    { top: '5%', left: '50%', transform: 'translateX(-50%)' }
                ],
                3: [
                    { bottom: '5%', left: '50%', transform: 'translateX(-50%)' },
                    { top: '8%', right: '10%' },
                    { top: '8%', left: '10%' }
                ],
                4: [
                    { bottom: '5%', left: '50%', transform: 'translateX(-50%)' },
                    { top: '5%', left: '50%', transform: 'translateX(-50%)' },
                    { top: '35%', right: '5%' },
                    { top: '35%', left: '5%' }
                ],
                5: [
                    { bottom: '5%', left: '50%', transform: 'translateX(-50%)' },
                    { bottom: '25%', right: '8%' },
                    { top: '12%', right: '8%' },
                    { top: '12%', left: '8%' },
                    { bottom: '25%', left: '8%' }
                ],
                6: [
                    { bottom: '-18%', left: '50%', transform: 'translateX(-50%)' },
                    { bottom: '0%', right: '-3%' },
                    { top: '0%', right: '-3%' },
                    { top: '-18%', left: '50%', transform: 'translateX(-50%)' },
                    { top: '0%', left: '-3%' },
                    { bottom: '0%', left: '-3%' }
                ]
            };

            const positions = positionMap[totalPlayers] || positionMap[6];
            const pos = positions[index % positions.length];

            // Convertir a objeto para que Alpine.js pueda aplicarlo
            return pos;
        },

        formatChips(amount) {
            if (amount >= 1000000) return (amount / 1000000).toFixed(1) + 'M';
            if (amount >= 1000) return (amount / 1000).toFixed(1) + 'K';
            return Math.round(amount).toString();
        }
    };
}

// Exportar para usar en Blade
window.gameTable = gameTable;
