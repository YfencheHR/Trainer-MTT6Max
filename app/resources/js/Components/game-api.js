/**
 * API wrapper para el servidor de juego (rutas mtt-simulator.hand.*).
 * Todas las respuestas son { success, data } o { success:false, message }.
 */
export class GameAPI {
  constructor(baseUrl = '/mtt-simulator/hand') {
    this.baseUrl = baseUrl;
  }

  async request(path, method = 'GET', body = null) {
    const url = path.startsWith('/') ? path : `${this.baseUrl}/${path}`;
    const response = await fetch(url, {
      method,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.getCsrfToken(),
      },
      body: body ? JSON.stringify(body) : null,
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok || json.success === false) {
      throw new Error(json.message || `HTTP ${response.status}`);
    }
    return json.data;
  }

  getState(handId) { return this.request(`${handId}/state`); }

  /** Log de las manos anteriores de la mesa (al recargar la página). */
  getLogHistory(handId) { return this.request(`${handId}/log`); }

  playerAction(handId, action, amount = 0) {
    return this.request(`${handId}/action`, 'POST', { action, amount });
  }

  /** Cierra la ronda: eliminaciones, nivelación de mesas y siguiente mano del héroe. */
  nextHand(handId) { return this.request(`${handId}/next`, 'POST'); }

  /** El servidor juega la mano de una mesa secundaria en la ronda indicada. */
  resolveTable(tournamentId, tableId, round) {
    return this.request(`/mtt-simulator/${tournamentId}/tables/${tableId}/resolve`, 'POST', { round });
  }

  botAction(handId) { return this.request(`${handId}/bot-action`, 'POST'); }

  getCsrfToken() {
    const token = document.querySelector('meta[name="csrf-token"]');
    return token ? token.getAttribute('content') : '';
  }
}
