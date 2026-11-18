@extends('app')

@section('content')
<style>
  .pt4-file-input {
    display: block;
    box-sizing: border-box;
    width: 100%;
    margin-top: 0.4rem;
    padding: 0.5rem;
    border: 1px solid #10b981;
    border-radius: 0.4rem;
    background: #11161c;
    color: #d1d5db;
    cursor: pointer;
  }

  .pt4-file-input::file-selector-button {
    margin-right: 0.75rem;
    padding: 0.5rem 0.75rem;
    border: 0;
    border-radius: 0.3rem;
    background: #10b981;
    color: #04210f;
    font-weight: 700;
    cursor: pointer;
  }

  .pt4-import-controls {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 1rem;
    align-items: end;
  }

  .pt4-file-field {
    grid-column: 1 / -1;
    font-size: 0.85rem;
    font-weight: 700;
    color: #e5e7eb;
  }

  .pt4-min-hands-input {
    box-sizing: border-box;
    width: 100%;
    margin-top: 0.4rem;
    padding: 0.55rem 0.65rem;
    border: 1px solid #2e3a48;
    border-radius: 0.3rem;
    background: #11161c;
    color: #d1d5db;
  }

  .pt4-reset-field {
    display: flex;
    min-height: 2.7rem;
    align-items: center;
    gap: 0.55rem;
    padding: 0.55rem 0.7rem;
    border: 1px solid #2e3a48;
    border-radius: 0.4rem;
    background: #11161c;
    color: #d1d5db;
    font-size: 0.8rem;
  }

  .pt4-import-button {
    min-height: 2.7rem;
    padding: 0.55rem 1rem;
    border: none;
    border-radius: 0.4rem;
    background: #10b981;
    color: #04210f;
    font-weight: 700;
    cursor: pointer;
  }

  .pt4-import-button:disabled {
    cursor: wait;
    opacity: 0.65;
  }
</style>
<div x-data="pt4Import()" style="max-width: 1000px; margin: 0 auto; color: #d1d5db;" class="px-4 sm:px-6 lg:px-8 py-6">

  <div style="margin-bottom: 1.25rem;">
    <p style="margin: 0; font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Importar</p>
    <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700;">Datos reales desde PokerTracker 4</h1>
    <p style="margin: 0.4rem 0 0; font-size: 0.9rem; color: #9ca3af; max-width: 720px;">
      Sube una copia de tu base de PokerTracker (el <code>.backup</code> en <code>.zip</code>). Se carga en una base temporal, se calculan las estadísticas de
      cada jugador, se reparten en los 9 perfiles de bot y, si alguno no encaja en ninguno, se crea un bot nuevo. La base temporal se borra al acabar.
    </p>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem;">
    @foreach ([['Bots', 'bots', $ours['bots']], ['Filas de estadísticas (HUD)', 'hud_stats', $ours['hud_stats']], ['Bots creados por importación', 'pool', $pool]] as [$label, $key, $n])
      <div style="background: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 0.75rem 1rem;">
        <p style="margin: 0; font-size: 0.7rem; color: #9ca3af; text-transform: uppercase;">{{ $label }}</p>
        <p style="margin: 0; font-size: 1.4rem; font-weight: 700; color: #10b981;" x-text="ours.{{ $key }}">{{ $n }}</p>
      </div>
    @endforeach
  </div>

  <section style="background: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 1.25rem;">
    <h2 style="margin: 0 0 0.75rem; font-size: 1rem;">Subir copia de PokerTracker</h2>
    <div class="pt4-import-controls">
      <label class="pt4-file-field">1. Selecciona el archivo de PokerTracker (.zip / .backup / .sql)
        <input class="pt4-file-input" type="file" accept=".zip,.backup,.sql" x-ref="file" :disabled="busy" @change="fileName = $event.target.files.length ? $event.target.files[0].name : ''">
        <span x-text="fileName || 'Haz clic en «Seleccionar archivo» para buscarlo en tu equipo'" style="display: block; margin-top: 0.4rem; font-size: 0.75rem; font-weight: 400; color: #9ca3af;"></span>
      </label>
      <label style="font-size: 0.8rem; color: #9ca3af;">2. Manos mínimas por jugador
        <input class="pt4-min-hands-input" type="number" x-model.number="minHands" min="10" max="5000" :disabled="busy">
      </label>
      <label class="pt4-reset-field" title="Olvida lo ya importado y sustituye las estadísticas por las de esta copia">
        <input type="checkbox" x-model="reset" :disabled="busy"> 3. Recalcular desde cero
      </label>
      <button type="button" @click="start()" :disabled="busy" class="pt4-import-button"
        x-text="busy ? 'Trabajando…' : '4. Subir e importar'"></button>
    </div>
    <p x-show="reset" x-cloak style="margin: 0.6rem 0 0; padding: 0.6rem 0.8rem; font-size: 0.78rem; color: #fcd34d; background: rgba(252, 211, 77, 0.08); border: 1px solid rgba(252, 211, 77, 0.35); border-radius: 0.4rem;">
      <template x-if="tournamentsAffected > 0">
        <span>Hay <strong x-text="tournamentsAffected"></strong> torneo(s) que usan los perfiles actuales. <strong>No se borran</strong>: esos perfiles se archivan (los torneos siguen funcionando y sus historiales se conservan) y se crean perfiles nuevos con esta importación.</span>
      </template>
      <template x-if="tournamentsAffected === 0">
        <span>Se sustituirán los perfiles importados anteriormente por los de esta copia. Ningún torneo los usa ahora mismo.</span>
      </template>
    </p>
    <p style="margin: 0.6rem 0 0; font-size: 0.75rem; color: #9ca3af;">Los jugadores con pocas manos se agrupan con otros parecidos (VPIP/PFR) y sus manos se suman, así que cada perfil se apoya en mucha más muestra.</p>

    <div x-show="busy && progress !== null" style="margin-top: 0.9rem; height: 0.5rem; background: #11161c; border-radius: 0.25rem; overflow: hidden;">
      <div :style="`height: 100%; background: #10b981; width: ${progress}%`"></div>
    </div>
    <p x-show="error" x-text="error" style="margin: 0.75rem 0 0; color: #f87171;"></p>
    <p x-show="state === 'done'" style="margin: 0.75rem 0 0; color: #34d399; font-weight: 600;">✔ Importación terminada</p>
    <p x-show="state === 'failed'" style="margin: 0.75rem 0 0; color: #f87171; font-weight: 600;">✗ La importación ha fallado (mira el registro)</p>
    <pre x-show="log" x-text="log" style="margin: 0.75rem 0 0; padding: 0.75rem; background: #11161c; border: 1px solid #2e3a48; border-radius: 0.4rem; font-size: 0.75rem; max-height: 22rem; overflow: auto; white-space: pre;"></pre>
  </section>

  <section x-data="pt4Simulate()" style="margin-top: 1.25rem; background: #1a212a; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 1.25rem;">
    <h2 style="margin: 0 0 0.5rem; font-size: 1rem;">{{ __('simulation.title') }}</h2>
    <p style="margin: 0 0 0.9rem; font-size: 0.85rem; color: #9ca3af; max-width: 760px;">{{ __('simulation.intro') }}</p>
    <div class="pt4-import-controls">
      <label style="font-size: 0.8rem; color: #9ca3af;">{{ __('simulation.players') }}
        <input class="pt4-min-hands-input" type="number" x-model.number="players" min="12" max="600" :disabled="busy">
      </label>
      <label style="font-size: 0.8rem; color: #9ca3af;">{{ __('simulation.speed') }}
        <select class="pt4-min-hands-input" x-model="speed" :disabled="busy">
          @foreach (\App\Services\Tournament\SimulationLauncher::SPEEDS as $s)
            <option value="{{ $s }}">{{ __('simulation.speed_' . $s) }}</option>
          @endforeach
        </select>
      </label>
      <button type="button" @click="start()" :disabled="busy" class="pt4-import-button"
        x-text="busy ? @js(__('simulation.working')) : @js(__('simulation.button'))"></button>
    </div>
    <p style="margin: 0.6rem 0 0; font-size: 0.75rem; color: #9ca3af;">{{ __('simulation.replaces') }}</p>
    <p style="margin: 0.4rem 0 0; font-size: 0.75rem; color: #9ca3af;">{{ __('simulation.how_to_read') }}</p>
    <div x-show="busy && progress" x-cloak style="margin-top: 0.9rem;">
      <p x-text="phaseText()" style="margin: 0 0 0.4rem; font-size: 0.85rem; color: #e5e7eb; font-weight: 600;"></p>
      <div style="height: 0.6rem; background: #11161c; border-radius: 0.3rem; overflow: hidden;">
        <div :style="`height: 100%; background: #10b981; transition: width 0.6s; width: ${progress ? progress.percent : 0}%`"></div>
      </div>
      <p x-text="detailText()" style="margin: 0.4rem 0 0; font-size: 0.78rem; color: #9ca3af;"></p>
    </div>
    <p x-show="error" x-text="error" style="margin: 0.75rem 0 0; color: #f87171;"></p>
    <p x-show="state === 'done'" style="margin: 0.75rem 0 0; color: #34d399; font-weight: 600;">{{ __('simulation.done') }}</p>
    <p x-show="state === 'failed'" style="margin: 0.75rem 0 0; color: #f87171; font-weight: 600;">{{ __('simulation.failed') }}</p>
    <pre x-show="log" x-text="log" style="margin: 0.75rem 0 0; padding: 0.75rem; background: #11161c; border: 1px solid #2e3a48; border-radius: 0.4rem; font-size: 0.75rem; max-height: 30rem; overflow: auto; white-space: pre;"></pre>
  </section>
</div>

<script>
  function pt4Simulate() {
    return {
      players: 600, speed: 'tipica', busy: false, error: null, state: null, log: '', progress: null,
      text: @json(__('simulation.progress')),
      csrf: document.querySelector('meta[name=csrf-token]').content,

      phaseText() {
        const p = this.progress;
        return p ? `${this.text.phase} ${p.phase}/3 · ${this.text[p.phase_name] || p.phase_name} · ${p.percent}%` : '';
      },

      detailText() {
        const p = this.progress;
        if (!p || p.round === null) { return this.text.waiting; }
        const m = Math.floor(p.seconds / 60), s = p.seconds % 60;
        return `${this.text.round} ${p.round} · ${p.alive} / ${p.total} ${this.text.players} · ${p.tables} ${this.text.tables} · ${m} min ${s} s`;
      },

      async start() {
        this.error = null; this.state = null; this.log = ''; this.progress = null; this.busy = true;
        try {
          const r = await fetch(@json(route('import.simulate')), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf },
            body: JSON.stringify({ players: this.players, speed: this.speed }),
          });
          const j = await r.json();
          if (!r.ok || !j.success) {
            if (j.run) { await this.poll(j.run); }
            throw new Error(j.message || Object.values(j.errors || {}).flat().join(' ') || 'Error');
          }
          await this.poll(j.run);
        } catch (e) {
          this.error = e.message;
        } finally {
          this.busy = false;
        }
      },

      async poll(run) {
        const url = @json(route('import.status', ['run' => '__RUN__'])).replace('__RUN__', run);
        for (;;) {
          const j = await (await fetch(url, { headers: { 'Accept': 'application/json' } })).json();
          this.log = j.log; this.state = j.state; this.progress = j.progress;
          if (j.state !== 'running') return;
          await new Promise(r => setTimeout(r, 3000));
        }
      },
    };
  }
</script>

<script>
  function pt4Import() {
    return {
      ours: { bots: {{ $ours['bots'] }}, hud_stats: {{ $ours['hud_stats'] }}, pool: {{ $pool }} },
      tournamentsAffected: {{ $tournamentsAffected ?? 0 }},
      fileName: '',
      minHands: 20, reset: false, busy: false, progress: null, error: null, state: null, log: '',
      csrf: document.querySelector('meta[name=csrf-token]').content,

      async start() {
        const f = this.$refs.file.files[0];
        if (!f) { this.error = 'Elige un archivo.'; return; }
        const size = 1.5 * 1024 * 1024; // PHP admite 2 MB por subida
        const total = Math.max(1, Math.ceil(f.size / size));
        if (total > 4096) { this.error = 'El archivo supera el máximo admitido de 6 GiB.'; return; }
        this.error = null; this.state = null; this.log = ''; this.busy = true; this.progress = 0;
        const id = crypto.randomUUID();
        try {
          let run = null;
          for (let i = 0; i < total; i++) {
            const fd = new FormData();
            fd.append('id', id); fd.append('idx', i); fd.append('total', total); fd.append('name', f.name);
            fd.append('min_hands', this.minHands); fd.append('reset', this.reset ? 1 : 0);
            fd.append('chunk', f.slice(i * size, (i + 1) * size), 'chunk');
            const r = await fetch(@json(route('import.upload')), { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf }, body: fd });
            const j = await r.json();
            if (!r.ok || !j.success) throw new Error((j.message || 'Fallo al subir el archivo') + ' (trozo ' + (i + 1) + ' de ' + total + ')');
            this.progress = Math.round(((i + 1) / total) * 100);
            if (j.done) run = j.run;
          }
          this.progress = null;
          await this.poll(run);
        } catch (e) {
          this.error = e.message;
        } finally {
          this.busy = false;
        }
      },

      async poll(run) {
        const url = @json(route('import.status', ['run' => '__RUN__'])).replace('__RUN__', run);
        for (;;) {
          const j = await (await fetch(url, { headers: { 'Accept': 'application/json' } })).json();
          this.log = j.log; this.ours = j.ours; this.state = j.state;
          if (j.state !== 'running') return;
          await new Promise(r => setTimeout(r, 3000));
        }
      },
    };
  }
</script>
@endsection
