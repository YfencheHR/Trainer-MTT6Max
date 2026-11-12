@php
  $allPositions = [
    'UTG' => 'UTG',
    'HJ' => 'HJ / MP',
    'CO' => 'CO',
    'BTN' => 'BTN',
    'SB' => 'SB',
    'BB' => 'BB',
  ];
  $positions = !empty($onlyPosition)
    ? [$onlyPosition => $allPositions[$onlyPosition]]
    : $allPositions;
  $matrixTypes = !empty($onlyType)
    ? [$onlyType => $onlyType === 'attack' ? 'Ataque · sin subida' : 'Defensa · ante una subida']
    : ['attack' => 'Ataque · sin subida', 'defense' => 'Defensa · ante una subida'];
@endphp

<section class="mb-8">
  @if(!empty($editorPage))
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <div>
        <h2 class="text-2xl font-bold text-white mb-1">Editar tabla preflop · {{ $positions[$onlyPosition] }} · {{ $matrixTypes[$onlyType] }}</h2>
        <p style="color: #9ca3af;" class="text-sm">{{ $bot->nombre }} · selecciona una mano para editar sus frecuencias.</p>
      </div>
      <a href="{{ route('bots.show', $bot) }}" class="rounded px-4 py-2 text-sm font-semibold" style="background-color: #374151; color: #fff;">Volver al rival</a>
    </div>
  @else
    <h2 class="text-2xl font-bold text-white mb-1">Tablas preflop aproximadas</h2>
  @endif
  <p style="color: #9ca3af;" class="text-sm mb-4">
    Frecuencias estimadas por mano a partir de PokerTracker 4 y del perfil del rival; se suaviza la frontera y se conserva el porcentaje agregado.
    No depende del stack. Ataque = nadie ha subido; defensa = una subida delante. Las ramas de iso aún no están incluidas.
  </p>
  <p style="color: #9ca3af;" class="text-xs mb-4">
    Los colores ocupan toda la celda. Ataque: rojo open/resubir/push, naranja open-call, amarillo open-fold, verde limp y gris fold. Defensa: rojo 3-bet, azul call y gris fold.
  </p>
  @if(empty($editorPage))
    <p style="color: #9ca3af;" class="text-xs mb-4">Las tablas se guardan por mano y posición; el borde ámbar señala una edición manual.</p>
  @endif
  @if(session('success'))
    <div role="status" class="mb-4 rounded border p-3 text-sm" style="border-color: #10b981; color: #a7f3d0;">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div role="alert" class="mb-4 rounded border p-3 text-sm" style="border-color: #ef4444; color: #fecaca;">
      @foreach($errors->all() as $error)
        <p>{{ $error }}</p>
      @endforeach
    </div>
  @endif

  <div class="space-y-3">
    @foreach($positions as $position => $positionLabel)
      @php
        $data = $tables[$position];
        $positionMatrixTypes = $matrixTypes;
        if ($position === 'UTG') {
          unset($positionMatrixTypes['defense']);
        }
      @endphp
      <details @if($loop->first) open @endif style="background-color: #1a212a; border-color: #2e3a48;" class="rounded border">
        <summary class="cursor-pointer p-4 text-white font-semibold">
          {{ $positionLabel }}
          <span style="color: #9ca3af;" class="ml-2 text-xs font-normal">
            ataque: abrir {{ number_format($data['stats']['open'], 1, ',', '') }}%
            · limp {{ number_format($data['stats']['limp'], 1, ',', '') }}%
            @if($position !== 'UTG')
              · defensa: 3-bet {{ number_format($data['stats']['three_bet'], 1, ',', '') }}%,
              continuar hasta {{ number_format($data['stats']['defense'], 1, ',', '') }}%
            @else
              · sin defensa
            @endif
            · si recibe 3-bet tras open: resubir {{ number_format($data['stats']['open_response']['reraise'], 1, ',', '') }}%,
            pagar {{ number_format($data['stats']['open_response']['call'], 1, ',', '') }}%,
            foldear {{ number_format($data['stats']['open_response']['fold'], 1, ',', '') }}%
            · tras limp+subida: raise {{ number_format($data['stats']['limp_response']['raise'], 1, ',', '') }}%,
            push {{ number_format($data['stats']['limp_response']['push'], 1, ',', '') }}%
          </span>
        </summary>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-4 pt-0">
          @foreach($positionMatrixTypes as $type => $title)
            @php
              $attackByHand = collect($data['attack'])->keyBy('hand');
              $defenseByHand = collect($data['defense'])->keyBy('hand');
            @endphp
            <div class="overflow-x-auto">
              <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <h3 class="text-sm uppercase font-bold" style="color: {{ $type === 'attack' ? '#34d399' : '#93c5fd' }};">{{ $title }} · frecuencias %</h3>
                @if(empty($editorPage))
                  <a href="{{ route('bots.preflop-tables.edit', ['bot' => $bot, 'position' => $position, 'type' => $type]) }}" class="rounded px-3 py-1 text-xs font-semibold" style="background-color: #374151; color: #fff;">Editar tabla</a>
                @endif
              </div>
              <div class="flex flex-wrap gap-x-3 gap-y-1 mb-2 text-xs" style="color: #9ca3af;">
                @if($type === 'attack')
                  @foreach([['Open / resubir / push', '#b91c1c'], ['Open-call', '#c2410c'], ['Open-fold', '#a16207'], ['Limp', '#15803d'], ['Fold', '#4b5563']] as [$action, $color])
                    <span class="inline-flex items-center gap-1"><span class="inline-block rounded-sm" style="width: 8px; height: 8px; background-color: {{ $color }};"></span>{{ $action }}</span>
                  @endforeach
                @else
                  @foreach([['3-bet', '#b91c1c'], ['Pagar', '#1d4ed8'], ['Fold', '#4b5563']] as [$action, $color])
                    <span class="inline-flex items-center gap-1"><span class="inline-block rounded-sm" style="width: 8px; height: 8px; background-color: {{ $color }};"></span>{{ $action }}</span>
                  @endforeach
                @endif
              </div>
              <div class="grid gap-px" style="grid-template-columns: repeat(13, minmax(0, 1fr)); min-width: 520px;">
                @foreach($data[$type] as $cell)
                  @php
                    $editAttack = $attackByHand[$cell['hand']];
                    $editDefense = $defenseByHand[$cell['hand']];
                    $title = $cell['hand'] . ' · percentil ' . number_format($cell['percentile'], 2, ',', '');
                    if ($type === 'attack') {
                      $fold = max(0, 100 - $cell['open'] - $cell['limp']);
                      $frequencies = [
                        ['value' => $cell['open'] * $cell['open_response']['reraise'] / 100, 'color' => '#b91c1c'],
                        ['value' => $cell['open'] * $cell['open_response']['call'] / 100, 'color' => '#c2410c'],
                        ['value' => $cell['open'] * $cell['open_response']['fold'] / 100, 'color' => '#a16207'],
                        ['value' => $cell['limp'] * $cell['limp_response']['raise'] / 100, 'color' => '#b91c1c'],
                        ['value' => $cell['limp'] * $cell['limp_response']['push'] / 100, 'color' => '#b91c1c'],
                        ['value' => $cell['limp'] * max(0, 100 - $cell['limp_response']['raise'] - $cell['limp_response']['push']) / 100, 'color' => '#15803d'],
                        ['value' => $fold, 'color' => '#4b5563'],
                      ];
                      $title .= ' · open ' . number_format($cell['open'], 1, ',', '') . '% · limp ' . number_format($cell['limp'], 1, ',', '') . '% · fold ' . number_format($fold, 1, ',', '') . '%'
                        . ' · si recibe 3-bet tras open: resubir ' . number_format($cell['open_response']['reraise'], 1, ',', '') . '%, pagar ' . number_format($cell['open_response']['call'], 1, ',', '') . '%, fold ' . number_format($cell['open_response']['fold'], 1, ',', '') . '%'
                        . ' · limp-raise ' . number_format($cell['limp_response']['raise'], 1, ',', '') . '% · limp-push ' . number_format($cell['limp_response']['push'], 1, ',', '') . '%';
                    } else {
                      $frequencies = [
                        ['value' => $cell['three_bet'], 'color' => '#b91c1c'],
                        ['value' => $cell['call'], 'color' => '#1d4ed8'],
                        ['value' => $cell['fold'], 'color' => '#4b5563'],
                      ];
                      $title .= ' · 3-bet ' . number_format($cell['three_bet'], 1, ',', '') . '% · pagar ' . number_format($cell['call'], 1, ',', '') . '% · fold ' . number_format($cell['fold'], 1, ',', '') . '%';
                    }
                    $gradientStops = [];
                    $start = 0.0;
                    foreach ($frequencies as $frequency) {
                      $end = min(100.0, $start + max(0.0, (float) $frequency['value']));
                      if ($end > $start) {
                        $gradientStops[] = $frequency['color'] . ' ' . number_format($start, 2, '.', '') . '% ' . number_format($end, 2, '.', '') . '%';
                        $start = $end;
                      }
                    }
                    if ($start < 100) {
                      $gradientStops[] = '#4b5563 ' . number_format($start, 2, '.', '') . '% 100%';
                    }
                    $cellBackground = 'linear-gradient(90deg, ' . implode(', ', $gradientStops) . ')';
                    if ($cell['manual']) {
                      $title .= ' · editada manualmente';
                    }
                  @endphp
                  <button
                    type="button"
                    onclick="openPreflopRangeEditor(this)"
                    data-position="{{ $position }}"
                    data-hand="{{ $cell['hand'] }}"
                    data-table-type="{{ $type }}"
                    data-attack-open="{{ number_format($editAttack['open'], 2, '.', '') }}"
                    data-attack-limp="{{ number_format($editAttack['limp'], 2, '.', '') }}"
                    data-open-reraise="{{ number_format($editAttack['open_response']['reraise'], 2, '.', '') }}"
                    data-open-call="{{ number_format($editAttack['open_response']['call'], 2, '.', '') }}"
                    data-open-fold="{{ number_format($editAttack['open_response']['fold'], 2, '.', '') }}"
                    data-limp-raise="{{ number_format($editAttack['limp_response']['raise'], 2, '.', '') }}"
                    data-limp-push="{{ number_format($editAttack['limp_response']['push'], 2, '.', '') }}"
                    data-defense-three-bet="{{ number_format($editDefense['three_bet'], 2, '.', '') }}"
                    data-defense-call="{{ number_format($editDefense['call'], 2, '.', '') }}"
                    data-defense-fold="{{ number_format($editDefense['fold'], 2, '.', '') }}"
                    class="rounded-sm flex flex-col justify-between text-center py-1 px-px"
                    style="background: {{ $cellBackground }}; border: 1px solid {{ $cell['manual'] ? '#f59e0b' : '#2e3a48' }}; color: #fff; text-shadow: 0 1px 2px #000; min-height: 38px; cursor: pointer;"
                    title="{{ $title }}"
                    aria-label="{{ $title }} · haz clic para editar"
                  >
                    <span class="block font-bold" style="font-size: 10px; line-height: 1.1;">{{ $cell['hand'] }}</span>
                  </button>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>
      </details>
    @endforeach
  </div>

  <dialog id="preflop-range-editor" class="rounded-lg border p-0" style="background-color: #1a212a; border-color: #2e3a48; color: #e5e7eb; width: min(560px, calc(100vw - 2rem)); max-height: 90vh;">
    <div class="p-5">
      <div class="flex items-start justify-between gap-4 mb-4">
        <div>
          <h3 class="text-lg font-bold text-white">Editar frecuencias</h3>
          <p id="preflop-range-editor-hand" class="text-sm" style="color: #9ca3af;"></p>
        </div>
        <button type="button" class="text-xl" aria-label="Cerrar" onclick="document.getElementById('preflop-range-editor').close()">×</button>
      </div>

      <form id="preflop-range-editor-form" method="POST" data-update-url="{{ route('bots.preflop-tables.update', ['bot' => $bot, 'position' => '__POSITION__', 'hand' => '__HAND__']) }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="table_type" value="attack">
        <div class="grid grid-cols-2 gap-3">
          @foreach([
            'attack_open' => ['Abrir (%)', 'attackOpen'],
            'attack_limp' => ['Limp (%)', 'attackLimp'],
            'open_reraise' => ['Resubir ante 3-bet (%)', 'openReraise'],
            'open_call' => ['Pagar ante 3-bet (%)', 'openCall'],
            'open_fold' => ['Fold ante 3-bet (%)', 'openFold'],
            'limp_raise' => ['Limp-raise (%)', 'limpRaise'],
            'limp_push' => ['Limp-push (%)', 'limpPush'],
            'defense_three_bet' => ['3-bet defensa (%)', 'defenseThreeBet'],
            'defense_call' => ['Pagar defensa (%)', 'defenseCall'],
            'defense_fold' => ['Fold defensa (%)', 'defenseFold'],
          ] as $field => [$label, $source])
            @php $fieldType = str_starts_with($field, 'defense_') ? 'defense' : 'attack'; @endphp
            <label class="text-xs" data-preflop-table-field="{{ $fieldType }}" style="color: #9ca3af;">
              {{ $label }}
              <input
                id="preflop-range-{{ $field }}"
                name="{{ $field }}"
                data-preflop-frequency="{{ $source }}"
                type="number"
                min="0"
                max="100"
                step="0.01"
                required
                class="mt-1 w-full rounded border px-2 py-1 text-sm"
                style="background-color: #11161c; border-color: #2e3a48; color: #fff;"
              >
            </label>
          @endforeach
        </div>
        <p class="mt-3 text-xs" style="color: #9ca3af;">Abrir + limp ≤ 100%; cada grupo de respuesta debe sumar 100%. Limp-raise + limp-push ≤ 100%.</p>
        <div class="flex flex-wrap justify-between gap-2 mt-5">
          <button type="button" onclick="document.getElementById('preflop-range-editor').close()" class="rounded px-3 py-2 text-sm" style="background-color: #374151; color: #fff;">Cancelar</button>
          <button type="submit" class="rounded px-3 py-2 text-sm font-semibold" style="background-color: #059669; color: #fff;">Guardar</button>
        </div>
      </form>

      <form id="preflop-range-reset-form" method="POST" data-reset-url="{{ route('bots.preflop-tables.reset', ['bot' => $bot, 'position' => '__POSITION__', 'hand' => '__HAND__']) }}" class="mt-2">
        @csrf
        @method('DELETE')
        <input type="hidden" name="table_type" value="attack">
        <button type="submit" class="text-xs underline" style="color: #fbbf24;">Restaurar frecuencia calculada del perfil</button>
      </form>
    </div>
  </dialog>

  <script>
    function openPreflopRangeEditor(cell) {
      const dialog = document.getElementById('preflop-range-editor');
      const updateForm = document.getElementById('preflop-range-editor-form');
      const resetForm = document.getElementById('preflop-range-reset-form');
      const position = encodeURIComponent(cell.dataset.position);
      const hand = encodeURIComponent(cell.dataset.hand);
      const replaceRouteValues = (url) => url
        .replace('__POSITION__', position)
        .replace('__HAND__', hand);

      updateForm.action = replaceRouteValues(updateForm.dataset.updateUrl);
      resetForm.action = replaceRouteValues(resetForm.dataset.resetUrl);
      updateForm.querySelector('input[name="table_type"]').value = cell.dataset.tableType;
      resetForm.querySelector('input[name="table_type"]').value = cell.dataset.tableType;
      document.getElementById('preflop-range-editor-hand').textContent =
        `${cell.dataset.hand} · ${cell.dataset.position}`;

      updateForm.querySelectorAll('[data-preflop-frequency]').forEach((input) => {
        input.value = cell.dataset[input.dataset.preflopFrequency];
      });
      updateForm.querySelectorAll('[data-preflop-table-field]').forEach((field) => {
        field.hidden = field.dataset.preflopTableField !== cell.dataset.tableType
          || (cell.dataset.position === 'UTG' && field.dataset.preflopTableField === 'defense');
      });

      dialog.showModal();
    }

    document.getElementById('preflop-range-editor').addEventListener('click', (event) => {
      if (event.target === event.currentTarget) {
        event.currentTarget.close();
      }
    });
  </script>
</section>
