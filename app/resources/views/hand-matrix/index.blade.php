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

    <div id="strategyModal" class="fixed inset-0 z-50 items-center justify-center bg-black/70 p-4" style="display: none;">
      <section role="dialog" aria-modal="true" aria-labelledby="strategyModalTitle" class="w-full max-w-4xl rounded border p-5" style="background-color: #1a212a; border-color: #2e3a48; color: #d1d5db;">
        <div class="mb-4 flex items-center justify-between">
          <div>
            <h2 id="strategyModalTitle" class="text-xl font-bold"></h2>
            <p class="mt-1 text-sm text-slate-400">{{ __('hand-matrix.strategy_help') }}</p>
          </div>
          <button type="button" id="closeStrategyModal" class="rounded px-3 py-1 text-xl hover:bg-slate-700" aria-label="{{ __('hand-matrix.close') }}">×</button>
        </div>

        <div class="mb-2 grid grid-cols-[1.4fr_0.8fr_0.8fr_0.8fr_auto] gap-2 text-xs font-bold uppercase text-slate-400">
          <span>{{ __('hand-matrix.action_key') }}</span>
          <span>{{ __('hand-matrix.frequency_pct') }}</span>
          <span>{{ __('hand-matrix.ev_bb') }}</span>
          <span>{{ __('hand-matrix.size_bb') }}</span>
          <span></span>
        </div>
        <div id="strategyActionRows" class="max-h-80 space-y-2 overflow-y-auto"></div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
          <button type="button" id="addStrategyAction" class="rounded border border-slate-600 px-3 py-2 text-sm hover:bg-slate-700">{{ __('hand-matrix.add_action') }}</button>
          <span id="strategyFrequencyTotal" class="text-sm text-slate-400"></span>
        </div>
        <div class="mt-5 flex justify-end gap-2">
          <button type="button" id="cancelStrategyEdit" class="rounded border border-slate-600 px-4 py-2 hover:bg-slate-700">{{ __('hand-matrix.cancel') }}</button>
          <button type="button" id="saveStrategyEdit" class="rounded bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-600">{{ __('hand-matrix.save_strategy') }}</button>
        </div>
      </section>
    </div>
  </div>

  <script>
    const translations = {
      success: '{{ __("hand-matrix.update_success") }}',
      error: '{{ __("hand-matrix.update_error") }}'
    };

    let filterStructure = {}; // Almacenar toda la estructura
    let originalValues = {}; // Almacenar valores originales para detectar cambios
    let matrixStrategyData = {};
    let editingHandId = null;
    const strategyMessages = {
      invalidKey: @json(__('hand-matrix.invalid_action_key')),
      invalidFrequency: @json(__('hand-matrix.frequency_total_error')),
      saveError: @json(__('hand-matrix.update_error')),
      saved: @json(__('hand-matrix.strategy_update_success')),
      actionKey: @json(__('hand-matrix.action_key')),
      frequency: @json(__('hand-matrix.frequency_pct')),
      ev: @json(__('hand-matrix.ev_bb')),
      size: @json(__('hand-matrix.size_bb')),
      remove: @json(__('hand-matrix.remove_action'))
    };

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
          option.dataset.max = sd.max;
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
        const stackDepthMax = document.getElementById('stackDepthFilter').selectedOptions[0].dataset.max;
        const response = await fetch(`{{ route('hand-matrix.get-matrix-values') }}?scenario=${scenario}&position=${position}&stack_depth=${stackDepth}&stack_depth_max=${stackDepthMax}`);
        console.log('✅ Response received:', response.status);
        const allData = await response.json();
        console.log('📝 Total hands received:', allData.length);
        matrixStrategyData = {};

        let updated = 0;
        allData.forEach((data) => {
          matrixStrategyData[data.id] = {
            actions: data.actions,
            strategyValueId: data.strategy_value_id
          };

          // Buscar el contenedor de la celda
          const container = document.querySelector(`[data-hand-id="${data.id}"]`);
          if (container) {
            const strategyButton = container.querySelector('[data-edit-strategy]');
            const actionCount = strategyButton && strategyButton.querySelector('[data-strategy-count]');
            if (actionCount) {
              const count = data.actions ? Object.keys(data.actions).length : 0;
              actionCount.textContent = count ? ` · ${count}` : '';
            }

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

    function addStrategyActionRow(actionKey = '', action = {}) {
      const row = document.createElement('div');
      row.className = 'strategy-action-row grid grid-cols-[1.4fr_0.8fr_0.8fr_0.8fr_auto] gap-2';

      const fields = [
        { name: 'key', label: strategyMessages.actionKey, value: actionKey, type: 'text' },
        { name: 'frequency_pct', label: strategyMessages.frequency, value: action.frequency_pct ?? 0, type: 'number', min: 0, max: 100, step: 0.1 },
        { name: 'ev_bb', label: strategyMessages.ev, value: action.ev_bb ?? 0, type: 'number', step: 0.01 },
        { name: 'size_bb', label: strategyMessages.size, value: action.size_bb ?? '', type: 'number', min: 0, step: 0.1 }
      ];

      fields.forEach((field) => {
        const input = document.createElement('input');
        input.type = field.type;
        input.dataset.field = field.name;
        input.setAttribute('aria-label', field.label);
        input.placeholder = field.name === 'size_bb' ? '—' : field.label;
        input.value = field.value;
        if (field.min !== undefined) input.min = field.min;
        if (field.max !== undefined) input.max = field.max;
        if (field.step !== undefined) input.step = field.step;
        input.className = 'min-w-0 rounded border border-slate-600 bg-slate-900 px-2 py-2 text-sm text-white';
        row.appendChild(input);
      });

      const removeButton = document.createElement('button');
      removeButton.type = 'button';
      removeButton.textContent = '×';
      removeButton.title = strategyMessages.remove;
      removeButton.className = 'rounded px-2 text-lg text-red-300 hover:bg-slate-700';
      removeButton.addEventListener('click', () => {
        row.remove();
        updateStrategyFrequencyTotal();
      });
      row.appendChild(removeButton);
      document.getElementById('strategyActionRows').appendChild(row);
      row.querySelector('[data-field="frequency_pct"]').addEventListener('input', updateStrategyFrequencyTotal);
      updateStrategyFrequencyTotal();
    }

    function updateStrategyFrequencyTotal() {
      const total = Array.from(document.querySelectorAll('#strategyActionRows [data-field="frequency_pct"]'))
        .reduce((sum, input) => sum + (parseFloat(input.value) || 0), 0);
      document.getElementById('strategyFrequencyTotal').textContent =
        `Σ ${total.toFixed(1)}% / 100%`;
      document.getElementById('strategyFrequencyTotal').classList.toggle('text-red-300', Math.abs(total - 100) > 0.01);
      document.getElementById('strategyFrequencyTotal').classList.toggle('text-emerald-300', Math.abs(total - 100) <= 0.01);
    }

    function openStrategyEditor(handId) {
      editingHandId = String(handId);
      const container = document.querySelector(`[data-hand-id="${handId}"]`);
      const handName = container?.dataset.handName || '';
      document.getElementById('strategyModalTitle').textContent = `${handName} — ${document.getElementById('scenarioFilter').value}`;
      const rows = document.getElementById('strategyActionRows');
      rows.innerHTML = '';

      const actions = matrixStrategyData[handId]?.actions;
      if (actions) {
        Object.entries(actions).forEach(([key, action]) => addStrategyActionRow(key, action));
      }

      document.getElementById('strategyModal').style.display = 'flex';
      updateStrategyFrequencyTotal();
    }

    function closeStrategyEditor() {
      document.getElementById('strategyModal').style.display = 'none';
      editingHandId = null;
    }

    async function saveStrategyEditor() {
      const rows = Array.from(document.querySelectorAll('#strategyActionRows .strategy-action-row'));
      const actions = {};
      let totalFrequency = 0;

      for (const row of rows) {
        const key = row.querySelector('[data-field="key"]').value.trim();
        const frequency = parseFloat(row.querySelector('[data-field="frequency_pct"]').value);
        const ev = parseFloat(row.querySelector('[data-field="ev_bb"]').value);
        const sizeInput = row.querySelector('[data-field="size_bb"]').value.trim();
        const size = sizeInput === '' ? null : parseFloat(sizeInput);

        if (!/^[a-z][a-z0-9_]*$/.test(key) || Object.prototype.hasOwnProperty.call(actions, key)) {
          showSaveNotification(strategyMessages.invalidKey, 'error');
          return;
        }
        if (!Number.isFinite(frequency) || frequency < 0 || frequency > 100 || !Number.isFinite(ev) ||
            (size !== null && (!Number.isFinite(size) || size < 0))) {
          showSaveNotification(strategyMessages.saveError, 'error');
          return;
        }

        actions[key] = { frequency_pct: frequency, ev_bb: ev };
        if (size !== null) actions[key].size_bb = size;
        totalFrequency += frequency;
      }

      if (!rows.length || Math.abs(totalFrequency - 100) > 0.01) {
        showSaveNotification(strategyMessages.invalidFrequency, 'error');
        return;
      }

      const stackOption = document.getElementById('stackDepthFilter').selectedOptions[0];
      const payload = {
        scenario: document.getElementById('scenarioFilter').value,
        position: document.getElementById('positionFilter').value,
        stack_depth_min: parseInt(document.getElementById('stackDepthFilter').value, 10),
        stack_depth_max: parseInt(stackOption.dataset.max, 10),
        updates: [{ hand_group_id: parseInt(editingHandId, 10), actions }]
      };

      try {
        const response = await fetch('{{ route("hand-matrix.batch-update-strategies") }}', {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok) {
          throw new Error(result.message || strategyMessages.saveError);
        }

        matrixStrategyData[editingHandId] = { actions, strategyValueId: null };
        const container = document.querySelector(`[data-hand-id="${editingHandId}"]`);
        const count = container?.querySelector('[data-strategy-count]');
        if (count) count.textContent = ` · ${Object.keys(actions).length}`;
        closeStrategyEditor();
        showSaveNotification(strategyMessages.saved, 'success');
      } catch (error) {
        console.error('Error saving hand strategy:', error);
        showSaveNotification(error.message || strategyMessages.saveError, 'error');
      }
    }

    document.addEventListener('click', (event) => {
      const strategyButton = event.target.closest('[data-edit-strategy]');
      if (strategyButton) {
        openStrategyEditor(strategyButton.dataset.editStrategy);
        return;
      }

      if (event.target.id === 'addStrategyAction') addStrategyActionRow();
      if (event.target.id === 'closeStrategyModal' || event.target.id === 'cancelStrategyEdit' || event.target.id === 'strategyModal') {
        closeStrategyEditor();
      }
      if (event.target.id === 'saveStrategyEdit') saveStrategyEditor();
    });

    document.getElementById('strategyActionRows').addEventListener('input', (event) => {
      if (event.target.dataset.field === 'frequency_pct') updateStrategyFrequencyTotal();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && document.getElementById('strategyModal').style.display !== 'none') {
        closeStrategyEditor();
      }
    });

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
