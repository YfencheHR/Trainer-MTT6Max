/**
 * GameAPI - Servicio para comunicación con el servidor de juego
 */
class GameAPI {
    constructor(handId) {
        this.handId = handId;
        this.baseUrl = `/mtt-simulator/hand/${handId}`;
    }

    /**
     * Obtener estado actual de la mano
     */
    async getState() {
        try {
            const response = await fetch(`${this.baseUrl}/state`);
            if (!response.ok) throw new Error('Error obteniendo estado');
            return await response.json();
        } catch (error) {
            console.error('Error en getState:', error);
            throw error;
        }
    }

    /**
     * Enviar acción del jugador
     */
    async playerAction(action, amount = 0) {
        try {
            const response = await fetch(`${this.baseUrl}/action`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    action: action,
                    amount: amount
                })
            });

            if (!response.ok) throw new Error('Error procesando acción');
            return await response.json();
        } catch (error) {
            console.error('Error en playerAction:', error);
            throw error;
        }
    }

    /**
     * Obtener acción del bot (automática)
     */
    async getBotAction() {
        try {
            const response = await fetch(`${this.baseUrl}/bot-action`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            if (!response.ok) throw new Error('Error en acción del bot');
            return await response.json();
        } catch (error) {
            console.error('Error en getBotAction:', error);
            throw error;
        }
    }

    /**
     * Resolver board si hay all-in
     */
    async resolveAllIn() {
        try {
            const response = await fetch(`${this.baseUrl}/resolve-allin`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            if (!response.ok) throw new Error('Error resolviendo all-in');
            return await response.json();
        } catch (error) {
            console.error('Error en resolveAllIn:', error);
            throw error;
        }
    }
}

// Exportar para usar en otros módulos
window.GameAPI = GameAPI;
