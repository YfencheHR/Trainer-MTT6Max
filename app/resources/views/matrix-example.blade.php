@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
  <div class="mb-8">
    <h1 class="text-3xl font-bold mb-2" style="color: #d1d5db;">Matriz de Manos - Ejemplo</h1>
    <p style="color: #a1a5b0;">Componente Matrix renderizando 169 manos de poker</p>
  </div>

  {{-- Controles --}}
  <div class="mb-6 flex gap-4">
    <button
      class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700"
      @click="loadTableData()"
    >
      Cargar Datos
    </button>
    <button
      class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
      @click="clearSelection()"
    >
      Limpiar
    </button>
  </div>

  {{-- Info selección --}}
  <div x-show="selected" class="mb-6 p-4 bg-blue-900/30 rounded border border-blue-500">
    <p><strong>Mano seleccionada:</strong> <span x-text="selected?.name || ''"></span></p>
    <p><strong>EV:</strong> <span x-text="selected?.ev?.toFixed(2) || '-'"></span></p>
  </div>

  {{-- Matriz --}}
  <div style="background-color: #1a212a; border-color: #2e3a48; border: 1px solid; border-radius: 0.5rem; padding: 1rem; overflow-x: auto;">
    <x-matrix
      :items="\"
      :columns="13"
      cellComponent="matrix.hand-cell"
      id="handsMatrix"
    />
  </div>

  {{-- Estadísticas --}}
  <div class="mt-6 grid grid-cols-3 gap-4">
    <div class="p-4 bg-gray-800 rounded" style="border-left: 4px solid #047857;">
      <p style="color: #a1a5b0;" class="text-sm">Total Manos</p>
      <p class="text-2xl font-bold" style="color: #10b981;">169</p>
    </div>
    <div class="p-4 bg-gray-800 rounded" style="border-left: 4px solid #1e40af;">
      <p style="color: #a1a5b0;" class="text-sm">Suited</p>
      <p class="text-2xl font-bold" style="color: #3b82f6;">78</p>
    </div>
    <div class="p-4 bg-gray-800 rounded" style="border-left: 4px solid #92400e;">
      <p style="color: #a1a5b0;" class="text-sm">Offsuit</p>
      <p class="text-2xl font-bold" style="color: #f59e0b;">78</p>
    </div>
  </div>
</div>

<script>
export default function () {
  return {
    hands: [],
    selected: null,

    loadTableData() {
      const ranks = ['A', 'K', 'Q', 'J', 'T', '9', '8', '7', '6', '5', '4', '3', '2'];
      this.hands = [];

      // Pares
      ranks.forEach(rank => {
        this.hands.push({
          name: rank + rank,
          ev: Math.random() * 5,
          bgColor: '#047857'
        });
      });

      // Pairs AK, AQ, etc.
      for (let i = 0; i < ranks.length; i++) {
        for (let j = i + 1; j < ranks.length; j++) {
          // Suited
          this.hands.push({
            name: ranks[i] + ranks[j] + 's',
            ev: Math.random() * 3,
            bgColor: '#1e40af'
          });

          // Offsuit
          this.hands.push({
            name: ranks[i] + ranks[j] + 'o',
            ev: Math.random() * 2,
            bgColor: '#92400e'
          });
        }
      }
    },

    selectHand(event) {
      const cell = event.target.closest('[data-hand]');
      if (!cell) return;
      
      const handName = cell.dataset.hand;
      this.selected = this.hands.find(h => h.name === handName) || null;
    },

    clearSelection() {
      this.selected = null;
    }
  }
}
</script>
@endsection
