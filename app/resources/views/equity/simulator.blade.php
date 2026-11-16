@extends('app')

@section('content')
<div class="container mx-auto px-4 py-8" x-data="equitySimulatorData">
  <div class="mb-8">
    <h1 class="text-3xl font-bold mb-2" style="color: #d1d5db;">Equity Calculator</h1>
    <p style="color: #a1a5b0;">Calcula equity preflop entre manos y rangos</p>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div class="p-4 rounded border" style="background-color: #1a212a; border-color: #2e3a48;">
      <label style="color: #a1a5b0;" class="block text-sm font-bold mb-2">Tu Mano (Hero)</label>
      <input
        type="text"
        x-model="heroHand"
        placeholder="ej: AKs"
        class="w-full px-3 py-2 rounded text-black text-sm"
        @input="validateHeroHand()"
      />
      <p x-show="heroError" style="color: #ef4444;" class="text-xs mt-1" x-text="heroError"></p>
    </div>

    <div class="p-4 rounded border" style="background-color: #1a212a; border-color: #2e3a48;">
      <label style="color: #a1a5b0;" class="block text-sm font-bold mb-2">Iteraciones</label>
      <select x-model.number="iterations" class="w-full px-3 py-2 rounded text-black text-sm">
        <option value="500">500 (Rápido)</option>
        <option value="1000" selected>1,000 (Estándar)</option>
        <option value="5000">5,000 (Preciso)</option>
      </select>
    </div>
  </div>

  <div class="mb-8 p-4 rounded border" style="background-color: #1a212a; border-color: #2e3a48;">
    <label style="color: #a1a5b0;" class="block text-sm font-bold mb-2">Rango Villano</label>
    <textarea
      x-model="villainRangeText"
      placeholder="ej: AA,KK,QQ,AKs,AKo"
      class="w-full px-3 py-2 rounded text-black text-sm font-mono"
      rows="3"
      @input="parseVillainRange()"
    ></textarea>
    <p style="color: #a1a5b0;" class="text-xs mt-2"><span x-text="villainRange.length"></span> manos en rango</p>
  </div>

  <div class="mb-8">
    <button
      @click="simulate()"
      :disabled="loading"
      class="w-full px-4 py-3 rounded font-bold"
      style="background-color: #047857; color: white;"
    >
      <span x-show="!loading">🎲 Calcular Equity</span>
      <span x-show="loading">⏳ Calculando...</span>
    </button>
  </div>

  <div x-show="result" class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="p-6 rounded border" style="background-color: #1a3a2e; border-color: #047857;">
      <p style="color: #a1a5b0;" class="text-sm">Equity</p>
      <p class="text-3xl font-bold" style="color: #10b981;"><span x-text="(result?.equity * 100).toFixed(2)"></span>%</p>
    </div>

    <div class="p-6 rounded border" style="background-color: #1a212a; border-color: #2e3a48;">
      <p style="color: #a1a5b0;" class="text-sm">Wins / Ties / Losses</p>
      <p class="text-lg font-bold" style="color: #60a5fa;"><span x-text="result?.wins"></span> / <span x-text="result?.ties"></span> / <span x-text="result?.losses"></span></p>
    </div>

    <div class="p-6 rounded border" style="background-color: #1a212a; border-color: #2e3a48;">
      <p style="color: #a1a5b0;" class="text-sm">Iteraciones</p>
      <p class="text-lg font-bold" style="color: #fbbf24;" x-text="result?.iterations"></p>
    </div>
  </div>

  <div x-show="error" class="mt-4 p-4 rounded border" style="background-color: #3a1a1a; border-color: #dc2626; color: #fca5a5;" x-text="error"></div>
</div>

<script>
(function() {
  // Esperar a que Alpine esté listo
  const checkAlpine = setInterval(() => {
    if (window.Alpine) {
      clearInterval(checkAlpine);

      Alpine.data('equitySimulatorData', () => ({
        heroHand: 'AKs',
        villainRangeText: 'AA,KK,QQ,JJ,AKs,AKo',
        villainRange: [],
        iterations: 1000,
        loading: false,
        result: null,
        error: null,
        heroError: null,
        validateHeroHand() {
          const valid = /^[A-K2-9][A-K2-9][so]?$/.test(this.heroHand);
          this.heroError = valid ? null : 'Formato: AKs, AKo, AA';
        },
        parseVillainRange() {
          this.villainRange = this.villainRangeText
            .split(',')
            .map(h => h.trim().toUpperCase())
            .filter(h => h.length > 0);
        },
        async simulate() {
          this.error = null;
          this.validateHeroHand();
          if (this.heroError) return;
          this.loading = true;
          try {
            const response = await fetch('{{ route('equity.api.simulate') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content
              },
              body: JSON.stringify({
                hero_hand: this.heroHand,
                villain_range: this.villainRange,
                iterations: this.iterations
              })
            });
            const data = await response.json();
            if (data.status === 'success') {
              this.result = data.data;
            } else {
              this.error = data.message || 'Error en simulación';
            }
          } catch (err) {
            this.error = 'Error: ' + err.message;
          } finally {
            this.loading = false;
          }
        }
      }));
    }
  }, 10);
})();
</script>

@endsection
