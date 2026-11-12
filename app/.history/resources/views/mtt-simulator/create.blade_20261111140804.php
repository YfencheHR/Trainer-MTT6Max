@extends('app')

@section('content')
  <div style="max-width: 1200px; margin: 0 auto;" class="px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-3xl font-bold mb-8" style="color: #d1d5db;">{{ __('tournaments.create_title') }}</h1>

    @if ($errors->any())
      <div class="mb-6 p-4 rounded" style="background-color: #3a1f1f; border-left: 4px solid #ef4444;">
        <p style="color: #ef4444;" class="font-semibold">{{ __('common.validation_error') }}</p>
        <ul style="color: #fca5a5;" class="mt-2 list-disc list-inside">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('mtt-simulator.store') }}" style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border p-8 space-y-8">
      @csrf

      <!-- Initial Parameters Section -->
      <div class="grid gap-6" style="grid-template-columns: repeat(3, 1fr);">
        <!-- Row 1: Starting Stack, Big Blind, Total Players -->
        <x-number-input-with-slider
          id="startingStack"
          name="starting_stack"
          label="{{ __('tournaments.starting_stack') }}"
          value="{{ old('starting_stack', $defaults['starting_stack']['default']) }}"
          min="{{ $defaults['starting_stack']['min'] }}"
          max="{{ $defaults['starting_stack']['max'] }}"
          step="{{ $defaults['starting_stack']['step'] }}"
          required
        />

        <x-number-input-with-slider
          id="bigBlind"
          name="big_blind"
          label="{{ __('tournaments.big_blind') }}"
          value="{{ old('big_blind', $defaults['big_blind']['default']) }}"
          min="{{ $defaults['big_blind']['min'] }}"
          max="{{ $defaults['big_blind']['max'] }}"
          step="{{ $defaults['big_blind']['step'] }}"
          required
        />

        <div>
          <x-number-input-with-slider
            id="totalPlayers"
            name="num_players"
            label="{{ __('tournaments.total_players') }}"
            value="{{ old('num_players', $defaults['players']['default']) }}"
            min="{{ $defaults['players']['min'] }}"
            max="{{ $defaults['players']['max'] }}"
            step="{{ $defaults['players']['step'] }}"
            required
            class="players-input"
          />
          <p id="players-error" style="color: #ef4444; display: none; margin-top: 0.5rem;" class="text-xs">{{ __('tournaments.players_error') }}</p>
        </div>

        <!-- Row 2: Tournament Speed, Type -->
        <x-select
          id="speed"
          name="speed"
          label="{{ __('tournaments.speed') }}"
          :options="[
            'tipica' => __('tournaments.speed_typical'),
            'rapida' => __('tournaments.speed_fast'),
            'muy_rapida' => __('tournaments.speed_very_fast')
          ]"
          value="{{ old('speed', 'tipica') }}"
          required
        />

        <x-select
          id="type"
          label="{{ __('tournaments.type') }}"
          :options="['mtt' => __('tournaments.type_normal')]"
          value="mtt"
          disabled
        />
      </div>

      <!-- Checkboxes: Hero Player, Show All Cards, No HUD Data -->
      <div style="padding-top: 1.5rem; padding-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">
        <label class="flex items-center gap-3 cursor-pointer p-3 rounded" style="background-color: #0f1419; border: 1px solid #2e3a48; width: fit-content;">
          <input type="checkbox" id="heroPlayer" name="hero_player" value="1" {{ old('hero_player', true) ? 'checked' : '' }} style="accent-color: #047857; width: 18px; height: 18px;">
          <span style="color: #a1a5b0;">{{ __('tournaments.hero_player') }}</span>
        </label>

        <label class="flex items-center gap-3 cursor-pointer p-3 rounded" style="background-color: #0f1419; border: 1px solid #2e3a48; width: fit-content;">
          <input type="checkbox" name="show_all_cards" value="1" {{ old('show_all_cards') ? 'checked' : '' }} style="accent-color: #047857; width: 18px; height: 18px;">
          <span style="color: #a1a5b0;">{{ __('tournaments.show_all_cards') }}</span>
        </label>

        <label class="flex items-center gap-3 cursor-pointer p-3 rounded" style="background-color: #0f1419; border: 1px solid #2e3a48; width: fit-content;">
          <input type="checkbox" name="no_hud_data" value="1" {{ old('no_hud_data') ? 'checked' : '' }} style="accent-color: #f59e0b; width: 18px; height: 18px;">
          <span style="color: #a1a5b0;">📊 Sin datos del rival (Solo estadísticas del torneo)</span>
        </label>
      </div>

      <!-- Distribución de Rivales con Sliders -->
      <div>
        <p style="color: #d1d5db;" class="block text-xs uppercase font-semibold mb-4">{{ __('tournaments.select_rivals') }}</p>
        <div style="background-color: #0f1419; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 1.5rem;">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @forelse ($bots as $bot)
              <div class="bot-item" data-bot-id="{{ $bot->id }}">
                <div class="mb-3">
                  <div class="flex items-start justify-between gap-2">
                    <div class="flex-1">
                      <p style="color: #d1d5db;" class="font-semibold text-sm">{{ $bot->nombre }}</p>
                      <p style="color: #9ca3af;" class="text-xs">{{ ucfirst($bot->tipo) }}</p>
                    </div>
                    <x-bot-stats :stats="$bot->stats" />
                  </div>
                </div>

                <div class="mb-3">
                  <label for="bot-percentage-{{ $bot->id }}" class="sr-only">{{ __('tournaments.percentage') }}: {{ $bot->nombre }}</label>
                  <input id="bot-percentage-{{ $bot->id }}" type="range" name="bot_percentages[{{ $bot->id }}]" min="0" max="100" value="{{ old('bot_percentages.' . $bot->id, 0) }}" class="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer bot-slider" style="accent-color: #047857;" data-bot-id="{{ $bot->id }}">
                </div>

                <div class="flex items-center justify-between mb-2">
                  <span style="color: #9ca3af;" class="text-xs">{{ __('tournaments.percentage') }}:</span>
                  <span style="color: #10b981;" class="font-bold text-sm bot-percentage">0%</span>
                </div>

                <div class="flex items-center justify-between" style="background-color: #111820; padding: 0.5rem 0.75rem; border-radius: 0.375rem; border: 1px solid #2e3a48;">
                  <span style="color: #9ca3af;" class="text-xs">{{ __('tournaments.bots_to_create') }}:</span>
                  <span style="color: #3b82f6;" class="font-bold text-sm bot-count">0</span>
                </div>
              </div>
            @empty
              <p style="color: #9ca3af;" class="py-4 px-3 text-center col-span-3">{{ __('tournaments.no_bots') }}</p>
            @endforelse
          </div>

          <!-- Resumen de totales -->
          <div class="mt-6 border-t border-slate-700" style="padding-top: 1.5rem;">
            <div class="grid grid-cols-2 gap-4">
              <div style="background-color: #111820; padding: 1rem; border-radius: 0.5rem; border: 1px solid #2e3a48;">
                <p style="color: #9ca3af;" class="text-xs mb-1">{{ __('tournaments.total_percentage') }}</p>
                <p style="color: #10b981;" class="font-bold text-lg total-percentage">0%</p>
              </div>
              <div style="background-color: #111820; padding: 1rem; border-radius: 0.5rem; border: 1px solid #2e3a48;">
                <p style="color: #9ca3af;" class="text-xs mb-1">{{ __('tournaments.total_bots_to_create') }}</p>
                <p style="color: #3b82f6;" class="font-bold text-lg total-bots">0</p>
              </div>
            </div>
            <p id="error-msg" style="color: #ef4444; display: none;" class="text-xs mt-3">{{ __('tournaments.percentage_error') }}</p>
          </div>
        </div>
      </div>

      <!-- Player Time Configuration -->
      <div>
        <label style="color: #d1d5db;" class="block text-xs uppercase font-semibold mb-4">⏱️ {{ __('tournaments.player_time_config') }}</label>
        <div style="background-color: #0f1419; border: 1px solid #2e3a48; border-radius: 0.5rem; padding: 1.5rem;">
          <!-- Time Mode Selection -->
          <div class="mb-6">
            <p style="color: #9ca3af;" class="text-sm mb-3">{{ __('tournaments.time_mode') }}</p>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
              <label class="flex items-center gap-2 cursor-pointer" style="flex: 1; min-width: 200px;">
                <input type="radio" name="player_time_mode" value="unlimited" {{ old('player_time_mode', 'unlimited') === 'unlimited' ? 'checked' : '' }} style="accent-color: #047857; width: 18px; height: 18px;">
                <span style="color: #d1d5db;">🕐 {{ __('tournaments.time_unlimited') }}</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer" style="flex: 1; min-width: 200px;">
                <input type="radio" name="player_time_mode" value="standard" {{ old('player_time_mode') === 'standard' ? 'checked' : '' }} style="accent-color: #047857; width: 18px; height: 18px;">
                <span style="color: #d1d5db;">⏳ {{ __('tournaments.time_standard') }}</span>
              </label>
            </div>
          </div>

          <!-- Standard Time Options (shown when standard is selected) -->
          <div id="standard-time-options" style="display: {{ old('player_time_mode', 'unlimited') === 'standard' ? 'block' : 'none' }}; border-top: 1px solid #2e3a48; padding-top: 1.5rem;">
            <div class="grid gap-6" style="grid-template-columns: repeat(2, 1fr);">
              <x-number-input-with-slider
                id="decisionTime"
                name="time_per_decision_seconds"
                label="{{ __('tournaments.time_per_decision') }}"
                value="{{ old('time_per_decision_seconds', 30) }}"
                min="5"
                max="300"
                step="5"
                suffix="{{ __('tournaments.seconds') }}"
              />

              <x-number-input-with-slider
                id="bankTime"
                name="bank_time_seconds"
                label="{{ __('tournaments.bank_time') }}"
                value="{{ old('bank_time_seconds', 300) }}"
                min="30"
                max="3600"
                step="30"
                suffix="{{ __('tournaments.seconds') }}"
              />
            </div>
            <p style="color: #6b7280; font-size: 0.875rem; margin-top: 1rem;">
              💡 {{ __('tournaments.time_info') }}
            </p>
          </div>
        </div>
      </div>

      <script>
        function updatePercentages() {
          const sliders = document.querySelectorAll('.bot-slider');
          let total = 0;
          let totalBots = 0;

          // Obtener el número de jugadores ingresado por el usuario
          const totalPlayersField = document.querySelector('#totalPlayers');
          const numPlayersInput = parseInt(totalPlayersField.value) || 0;
          const heroPlayerCheckbox = document.querySelector('#heroPlayer');
          const hasHeroPlayer = heroPlayerCheckbox?.checked ?? true;

          // Calcular máximo de bots permitidos
          const maxBotsAllowed = hasHeroPlayer ? numPlayersInput - 1 : numPlayersInput;

          // Primero, calcular el total actual
          sliders.forEach(slider => {
            const value = parseInt(slider.value) || 0;
            total += value;
          });

          // Ahora actualizar cada slider con sus límites dinámicos
          sliders.forEach(slider => {
            const value = parseInt(slider.value) || 0;

            // Calcular porcentaje disponible para este slider
            const otherPercentages = total - value;
            const maxForThisSlider = Math.max(0, 100 - otherPercentages);

            // Actualizar el atributo max del slider
            slider.max = maxForThisSlider;

            const botItem = slider.closest('.bot-item');
            const percentageElement = botItem.querySelector('.bot-percentage');
            const countElement = botItem.querySelector('.bot-count');

            if (percentageElement) {
              percentageElement.textContent = value + '%';
            }

            // Calcular bots a crear para este tipo basado en el número de jugadores ingresado
            const botsForThisType = Math.round(maxBotsAllowed * value / 100);

            if (countElement) {
              countElement.textContent = botsForThisType;
            }

            totalBots += botsForThisType;
          });

          // Actualizar totales
          const totalPercentage = document.querySelector('.total-percentage');
          if (totalPercentage) {
            totalPercentage.textContent = total + '%';
          }

          const totalBotsElement = document.querySelector('.total-bots');
          if (totalBotsElement) {
            totalBotsElement.textContent = totalBots;
          }

          // Mostrar error si el total excede 100%
          const errorMsg = document.querySelector('#error-msg');
          if (total > 100) {
            errorMsg.style.display = 'block';
            totalPercentage.style.color = '#ef4444';
          } else {
            errorMsg.style.display = 'none';
            totalPercentage.style.color = '#10b981';
          }

          // Validar que los bots no superen el número de jugadores
          const playersErrorMsg = document.querySelector('#players-error');

          if (totalBots > maxBotsAllowed) {
            playersErrorMsg.style.display = 'block';
            totalPlayersField.style.borderColor = '#ef4444';
          } else {
            playersErrorMsg.style.display = 'none';
            totalPlayersField.style.borderColor = '#2e3a48';
          }
        }

        // Sincronizar inputs y sliders
        function syncInputsAndSliders() {
          const fields = [
            { input: '#startingStack', slider: '#startingStackSlider' },
            { input: '#bigBlind', slider: '#bigBlindSlider' },
            { input: '#totalPlayers', slider: '#totalPlayersSlider' }
          ];

          fields.forEach(({ input, slider }) => {
            const inputEl = document.querySelector(input);
            const sliderEl = document.querySelector(slider);

            if (inputEl && sliderEl) {
              // Input → Slider
              inputEl.addEventListener('input', () => {
                sliderEl.value = inputEl.value;
                if (input === '#totalPlayers') {
                  updatePercentages();
                }
              });

              // Slider → Input
              sliderEl.addEventListener('input', () => {
                inputEl.value = sliderEl.value;
                if (input === '#totalPlayers') {
                  updatePercentages();
                }
              });
            }
          });
        }

        // Asegurarse de que los elementos existen antes de agregar listeners
        setTimeout(() => {
          syncInputsAndSliders();

          document.querySelectorAll('.bot-slider').forEach(slider => {
            slider.addEventListener('input', updatePercentages);
          });

          // Actualizar cuando se cambia el checkbox de hero player
          const heroPlayerCheckbox = document.querySelector('#heroPlayer');
          if (heroPlayerCheckbox) {
            heroPlayerCheckbox.addEventListener('change', () => {
              updatePercentages();
            });
          }

          updatePercentages();

          // Handle player time mode toggle
          const timeModeRadios = document.querySelectorAll('input[name="player_time_mode"]');
          const standardTimeOptions = document.getElementById('standard-time-options');

          timeModeRadios.forEach(radio => {
            radio.addEventListener('change', () => {
              if (radio.value === 'standard') {
                standardTimeOptions.style.display = 'block';
              } else {
                standardTimeOptions.style.display = 'none';
              }
            });
          });
        }, 100);
      </script>

      <!-- Available Hand Types -->
      <div style="margin-top: 2rem; margin-bottom: 2rem;">
        <label style="color: #d1d5db;" class="block text-xs uppercase font-semibold mb-4">{{ __('tournaments.hand_types') }}</label>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
          @foreach ($handTypes as $type)
            <label class="flex items-center gap-2 p-3 rounded cursor-pointer hover:bg-slate-800 transition" style="background-color: transparent; border: none;">
              <input type="checkbox" name="hand_types[]" value="{{ $type['id'] }}" {{ in_array($type['id'], old('hand_types', [])) ? 'checked' : '' }} style="accent-color: #047857; width: 18px; height: 18px;">
              <div class="flex-1 flex items-center gap-2">
                <span style="color: #d1d5db;" class="text-sm">{{ $type['label'] }}</span>
                @if (!empty($type['example']))
                  <div class="flex gap-1">
                    @foreach ($type['example'] as $visual)
                      @foreach ($visual['cards'] as $card)
                        <span style="background-color: #047857; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; min-width: 28px; text-align: center;">{{ $card }}</span>
                      @endforeach
                      @if ($visual['separator'] ?? false)
                        <span style="color: #047857; padding: 0 2px; font-weight: 600;">|</span>
                      @endif
                    @endforeach
                  </div>
                @endif
              </div>
            </label>
          @endforeach
        </div>
        @error('hand_types')
          <p style="color: #ef4444;" class="text-xs mt-2">{{ $message }}</p>
        @enderror
      </div>

      <!-- Botón Iniciar -->
      <button type="submit" class="w-full py-4 rounded font-semibold text-white transition text-lg hover:opacity-90" style="background-color: #047857;">
        {{ __('tournaments.start_tournament') }}
      </button>
    </form>
  </div>
@endsection
