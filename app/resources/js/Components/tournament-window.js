/**
 * Una sola ventana a la vez por torneo (ver App\Services\Tournament\TournamentWindowLock).
 *
 * La mesa pide el turno al abrirse y lo renueva cada pocos segundos. Si otra ventana lo tiene, esta no juega: muestra un aviso con la opción de
 * continuar aquí (la otra se detiene) o dejarlo donde está. Si otra ventana se lo queda mientras esta juega, se detiene y puede recuperarlo.
 *
 * Estados: 'pending' (aún no se sabe), 'owner' (puede jugar), 'other' (al abrir, otra ventana lo tenía), 'lost' (otra ventana se lo quedó).
 */
const HEARTBEAT_MS = 5000;

export function createTournamentWindow() {
  const token = (window.crypto?.randomUUID?.() ?? `w-${Date.now()}-${Math.random().toString(36).slice(2)}`);
  let urls = null;
  let timer = null;
  let readyResolve;
  const ready = new Promise(resolve => { readyResolve = resolve; });

  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  return {
    token,
    status: 'pending',
    ready,
    started: false,
    message: 'Este torneo está abierto en otra ventana.',   // texto del error cuando una acción se bloquea (lo pone la página, traducido)

    /** Empieza a controlar el torneo: pide el turno, lo renueva y lo libera al cerrar la ventana. */
    start(claimUrl, releaseUrl, message) {
      urls = { claim: claimUrl, release: releaseUrl };
      this.started = true;
      if (message) this.message = message;
      void this.claim(false).then(() => readyResolve());
      timer = window.setInterval(() => void this.claim(false), HEARTBEAT_MS);
      window.addEventListener('pagehide', () => this.release());
    },

    async claim(take) {
      if (!urls) return;
      try {
        const response = await fetch(urls.claim, {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
          body: JSON.stringify({ token, take }),
        });
        const json = await response.json();
        const owner = Boolean(json?.data?.owner);
        this.update(owner);
      } catch (err) {
        // Sin conexión con el servidor no se sabe: se conserva el estado y se reintenta en la siguiente renovación
        console.error('No se pudo comprobar la ventana del torneo:', err);
      }
    },

    update(owner) {
      const before = this.status;
      if (owner) {
        this.status = 'owner';
        if (before !== 'owner' && before !== 'pending') {
          // Recuperó el torneo: la mesa se sincroniza con lo que pasó en la otra ventana
          window.dispatchEvent(new CustomEvent('tournament-window-owner'));
        }
      } else {
        this.status = before === 'owner' ? 'lost' : (before === 'lost' ? 'lost' : 'other');
      }
    },

    /** Continuar aquí: se queda con el torneo aunque otra ventana lo tenga. */
    takeOver() {
      return this.claim(true);
    },

    /** El servidor rechazó una acción porque esta ventana ya no es la activa. */
    markLost() {
      if (this.status === 'owner') this.status = 'lost';
      else if (this.status === 'pending') this.status = 'other';
    },

    get blocked() {
      return this.status === 'other' || this.status === 'lost';
    },

    release() {
      if (!urls || this.status !== 'owner') return;
      const data = new FormData();
      data.append('token', token);
      data.append('_token', csrf());
      navigator.sendBeacon?.(urls.release, data);
    },

    stop() {
      if (timer) window.clearInterval(timer);
    },
  };
}
