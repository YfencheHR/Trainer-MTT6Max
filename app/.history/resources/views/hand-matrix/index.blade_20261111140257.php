@extends('app')

@section('content')
  {{-- Filters section --}}
  <div style="max-width: 1440px; margin: 0 auto;" class="px-2 sm:px-3 py-6">
    <!-- Encabezado -->
    <div class="mb-8">
      <h1 class="text-4xl font-bold mb-2" style="color: #d1d5db;">{{ __('hand-matrix.title') }}</h1>
      <p style="color: #a1a5b0;" class="text-lg">{{ __('hand-matrix.description') }}</p>
    </div>

    <!-- Filtros Dinámicos -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem; align-items: end;">
      <div>
        <label for="scenarioFilter" style="color: #a1a5b0;" class="block text-xs uppercase font-bold mb-2">Scenario</label>
        <select id="scenarioFilter" style="background-color: #0f1419; border-color: #2e3a48; color: #d1d5db;" class="w-full px-4 py-2 rounded border">
          <option value="">Cargando...</option>
        </select>
      </div>

      <div>
        <label for="positionFilter" style="color: #a1a5b0;" class="block text-xs uppercase font-bold mb-2">Position</label>
        <select id="positionFilter" style="background-color: #0f1419; border-color: #2e3a48; color: #d1d5db;" class="w-full px-4 py-2 rounded border">
          <option value="">Selecciona Scenario primero</option>
        </select>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div>
          <label for="stackDepthFilter" style="color: #a1a5b0;" class="block text-xs uppercase font-bold mb-2">Stack Depth</label>
          <select id="stackDepthFilter" style="background-color: #0f1419; border-color: #2e3a48; color: #d1d5db;" class="w-full px-4 py-2 rounded border">
            <option value="">Selecciona Position primero</option>
          </select>
        </div>
        <button id="saveBtn" disabled style="background-color: #047857; color: white;" class="w-full px-4 py-2 rounded font-semibold transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:opacity-40 hover:opacity-90" data-visible="false">
          Guardar
        </button>
      </div>
    </div>

    <!-- Matriz de Manos -->
    <div style="overflow-x: auto;">
      <div style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-4">
        @php
          $ranks = ['A', 'K', 'Q', 'J', 'T', '9', '8', '7', '6', '5', '4', '3', '2'];
          $handsMap = [];
          $matrixItems = [];

          foreach($allHands as $hand) {
            $handsMap[$hand->group_name] = $hand;
          }

          foreach($ranks as $rank) {
            foreach($ranks as $suitRank) {
              if ($rank === $suitRank) {
                $handName = $rank . $suitRank;
                $bgColor = '#047857';
              } elseif (array_search($rank, $ranks) < array_search($suitRank, $ranks)) {
                $handName = $rank . $suitRank . 's';
                $bgColor = '#1e3a8a';
              } else {
                $handName = $suitRank . $rank . 'o';
                $bgColor = '#92400e';
              }

              $hand = $handsMap[$handName] ?? null;

              if ($hand) {
                $matrixItems[] = [
                  'id' => $hand->id,
                  'name' => $hand->group_name,
                  'hand_name' => $hand->group_name,
                  'gto_ev' => $hand->gto_ev,
                  'ev' => $hand->gto_ev,
                  'bgColor' => $bgColor
                ];
              } else {
                $matrixItems[] = null;
              }
            }
          }
        @endphp

        <x-matrix
          :columns="13"
          :items="$matrixItems"
          cellComponent="matrix.hand-cell"
          id="handsMatrix"
        />
      </div>
    </div>
  </div>

  <script>
    const translations = {
      success: '{{ __("hand-matrix.update_success") }}',
      error: '{{ __("hand-matrix.update_error") }}'
    };

    let filterStructure = {}; // Almacenar toda la estructura
    let originalValues = {}; // Almacenar valores originales para detectar cambios

    // Cargar TODA la estructura de filtros de una sola vez
    async function loadFilterStructure() {
      console.log('📥 Loading entire filter structure from server...');
      try {
        const response = await fetch('{{ route("hand-matrix.get-filter-structure") }}');
        filterStructure = await response.json();
        console.log('✅ Filter structure loaded:', filterStructure);

        // Poblar scenarios
        populateScenarios();

        // Seleccionar el primer scenario y cargar sus posiciones
        const firstScenario = Object.keys(filterStructure)[0];
        if (firstScenario) {
          document.getElementById('scenarioFilter').value = firstScenario;
          populatePositions(firstScenario);

          // Seleccionar la primera posición y cargar stack depths
          const firstPosition = Object.keys(filterStructure[firstScenario])[0];
          if (firstPosition) {
            document.getElementById('positionFilter').value = firstPosition;
            populateStackDepths(firstScenario, firstPosition);
            // Cargar matriz con los selectores ya poblados
            checkAndLoadMatrix();
          }
        }
      } catch (error) {
        console.error('❌ Error loading filter structure:', error);
      }
    }

    function populateScenarios() {
      const select = document.getElementById('scenarioFilter');
      select.innerHTML = '';
      Object.keys(filterStructure).forEach(scenario => {
        const option = document.createElement('option');
        option.value = scenario;
        option.textContent = scenario;
        select.appendChild(option);
      });
      console.log('✅ Scenarios populated:', Object.keys(filterStructure));
    }

    function populatePositions(scenario) {
      const select = document.getElementById('positionFilter');
      select.innerHTML = '';

      if (filterStructure[scenario]) {
        Object.keys(filterStructure[scenario]).forEach(position => {
          const option = document.createElement('option');
          option.value = position;
          option.textContent = position;
          if (position === 'BTN') option.selected = true;
          select.appendChild(option);
        });
      }
      console.log('✅ Positions populated for', scenario);
    }

    function populateStackDepths(scenario, position) {
      const select = document.getElementById('stackDepthFilter');
      select.innerHTML = '';

      if (filterStructure[scenario] && filterStructure[scenario][position]) {
        filterStructure[scenario][position].forEach((sd, index) => {
          const option = document.createElement('option');
          option.value = sd.min;
          option.textContent = sd.label;
          if (index === 0) option.selected = true;
          select.appendChild(option);
        });
      }
      console.log('✅ Stack depths populated for', scenario, position);
    }

    // Agregar event listeners a los selectores
    function attachFilterListeners() {
      console.log('🎯 attachFilterListeners() called');
      const scenario = document.getElementById('scenarioFilter');
      const position = document.getElementById('positionFilter');
      const stackDepth = document.getElementById('stackDepthFilter');

      if (scenario) {
        scenario.addEventListener('change', (e) => {
          console.log('⚡ Scenario changed to:', e.target.value);
          populatePositions(e.target.value);
          const firstPosition = Object.keys(filterStructure[e.target.value])[0];
          if (firstPosition) {
            document.getElementById('positionFilter').value = firstPosition;
            populateStackDepths(e.target.value, firstPosition);
            checkAndLoadMatrix();
          }
        });
      }
      if (position) {
        position.addEventListener('change', (e) => {
          const scenario = document.getElementById('scenarioFilter').value;
          console.log('⚡ Position changed to:', e.target.value);
          populateStackDepths(scenario, e.target.value);
          checkAndLoadMatrix();
        });
      }
      if (stackDepth) {
        stackDepth.addEventListener('change', () => {
          console.log('⚡ Stack depth changed');
          checkAndLoadMatrix();
        });
      }
    }

    // Validar y cargar matriz solo si los 3 selectores tienen valores
    function checkAndLoadMatrix() {
      const scenario = document.getElementById('scenarioFilter').value;
      const position = document.getElementById('positionFilter').value;
      const stackDepth = document.getElementById('stackDepthFilter').value;

      if (scenario && position && stackDepth) {
        console.log('✅ All filters set. Loading matrix...');
        updateMatrixValues();
      } else {
        console.log('⚠️ Waiting for all filters to be selected');
      }
    }

    // Ejecutar cuando el documento esté listo
    console.log('⏳ Page ready state:', document.readyState);
    console.log('📥 Loading filter structure from server...');

    // Listener global para el botón Guardar usando delegación
    document.addEventListener('click', async (e) => {
      if (e.target.id !== 'saveBtn') return;

      console.log('💾 Save button clicked');
      const changes = [];
      const inputs = document.querySelectorAll('input[name^="ev_"]');

      inputs.forEach(input => {
        const match = input.name.match(/ev_(\d+)/);
        if (match) {
          const handId = match[1];
          const newValue = parseFloat(input.value);

          if (input.value !== originalValues[handId] && !isNaN(newValue)) {
            changes.push({
              id: handId,
              ev: newValue
            });
          }
        }
      });

      if (changes.length === 0) {
        console.log('⚠️ No changes to save');
        return;
      }

      console.log(`📊 Saving ${changes.length} changes`);

      try {
        const response = await fetch('{{ route("hand-matrix.batch-update") }}', {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ updates: changes })
        });

        if (response.ok) {
          console.log('✅ Save successful');

          // Actualizar valores originales
          inputs.forEach(input => {
            const match = input.name.match(/ev_(\d+)/);
            if (match) {
              const handId = match[1];
              originalValues[handId] = input.value;
            }
          });

          document.getElementById('saveBtn').disabled = true;
          showSaveNotification('{{ __("hand-matrix.update_success") }}', 'success');
        } else {
          console.error('❌ Save failed:', response.status);
          showSaveNotification('{{ __("hand-matrix.update_error") }}', 'error');
        }
      } catch (error) {
        console.error('❌ Error:', error);
        showSaveNotification('{{ __("hand-matrix.update_error") }}', 'error');
      }
    }, false);

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
        attachFilterListeners();
        loadFilterStructure();
        // Inicializar valores y listeners después de que se cargue el HTML
        setTimeout(() => {
          initializeOriginalValues();
          attachChangeListeners();
        }, 100);
      });
    } else {
      attachFilterListeners();
      loadFilterStructure();
      setTimeout(() => {
        initializeOriginalValues();
        attachChangeListeners();
      }, 100);
    }

    function initializeOriginalValues() {
      originalValues = {};
      // Los inputs hidden del componente number-input tienen formato name="ev_X"
      const inputs = document.querySelectorAll('input[name^="ev_"]');
      inputs.forEach(input => {
        const match = input.name.match(/ev_(\d+)/);
        if (match) {
          const handId = match[1];
          originalValues[handId] = input.value;
        }
      });
      console.log('✅ Original values initialized:', Object.keys(originalValues).length);
    }

    function attachChangeListeners() {
      const saveBtn = document.getElementById('saveBtn');
      // Los inputs hidden del componente number-input tienen formato name="ev_X"
      const inputs = document.querySelectorAll('input[name^="ev_"]');

      inputs.forEach(input => {
        input.addEventListener('change', () => {
          const hasChanges = Array.from(inputs).some(inp => {
            const match = inp.name.match(/ev_(\d+)/);
            if (match) {
              const handId = match[1];
              return inp.value !== originalValues[handId];
            }
            return false;
          });
          if (hasChanges) {
            saveBtn.disabled = false;
            console.log('✅ Enable Save button');
          } else {
            saveBtn.disabled = true;
            console.log('❌ Disable Save button');
          }
        });
      });
    }

    // Actualizar matriz cuando cambian los filtros - UNA SOLA PETICIÓN
    async function updateMatrixValues() {
      console.log('🔴 updateMatrixValues() called');
      const scenario = document.getElementById('scenarioFilter').value;
      const position = document.getElementById('positionFilter').value;
      const stackDepth = document.getElementById('stackDepthFilter').value;

      console.log('📊 Filters:', { scenario, position, stackDepth });

      try {
        console.log('📡 Fetching all matrix values in ONE request...');
        const response = await fetch(`{{ route('hand-matrix.get-matrix-values') }}?scenario=${scenario}&position=${position}&stack_depth=${stackDepth}`);
        console.log('✅ Response received:', response.status);
        const allData = await response.json();
        console.log('📝 Total hands received:', allData.length);

        let updated = 0;
        allData.forEach((data) => {
          // Buscar el contenedor de la celda
          const container = document.querySelector(`[data-hand-id="${data.id}"]`);
          if (container) {
            // Buscar el input visible (type="text") dentro del contenedor
            const visibleInput = container.querySelector('input[type="text"]');
            // Buscar el input oculto (type="hidden")
            const hiddenInput = container.querySelector(`input[name="ev_${data.id}"]`);

            if (visibleInput && hiddenInput) {
              const newValue = parseFloat(data.ev).toFixed(2);
              // Actualizar ambos inputs
              visibleInput.value = newValue;
              hiddenInput.value = newValue;
              // Disparar evento en el input visible para que Alpine se actualice
              visibleInput.dispatchEvent(new Event('input', { bubbles: true }));
              visibleInput.dispatchEvent(new Event('change', { bubbles: true }));
              updated++;
            }
          }
        });

        console.log(`✏️ Updated ${updated} cells out of ${allData.length}`);

        // Reinicializar valores originales después de cargar la matriz
        setTimeout(() => {
          initializeOriginalValues();
          attachChangeListeners();
          document.getElementById('saveBtn').disabled = true;
        }, 100);

      } catch (error) {
        console.error('❌ Error updating matrix:', error);
      }
    }




    function showSaveNotification(message, type = 'success') {
      const notification = document.createElement('div');
      notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        border-radius: 8px;
        background-color: ${type === 'success' ? '#1e3a1f' : '#3a1f1f'};
        color: ${type === 'success' ? '#10b981' : '#ef4444'};
        font-weight: 600;
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
      `;
      notification.textContent = message;
      document.body.appendChild(notification);

      setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
      }, 3000);
    }
  </script>

  <style>
    #handsMatrix {
      display: grid;
      gap: 2px;
      grid-template-columns: repeat(13, 1fr);
    }

    @keyframes slideIn {
      from {
        transform: translateX(400px);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    @keyframes slideOut {
      from {
        transform: translateX(0);
        opacity: 1;
      }
      to {
        transform: translateX(400px);
        opacity: 0;
      }
    }

    @media (max-width: 1200px) {
      .flex.flex-row {
        flex-direction: column;
      }

      .flex.flex-row > div:last-child {
        width: 100%;
      }
    }
  </style>
@endsection
