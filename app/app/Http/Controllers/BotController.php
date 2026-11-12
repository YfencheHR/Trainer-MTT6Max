<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Config\BotConfig;
use App\Services\Bots\RivalPreflopTables;
use Illuminate\Http\Request;

class BotController extends Controller
{
    public function index()
    {
        $bots = Bot::with('hudStats')->orderBy('vpip', 'desc')->get();

        // Primero los del Pool (copias de los primarios, en el orden de los primarios, y después los perfiles nuevos por VPIP);
        // después los primarios
        $pool = $bots->filter(fn($b) => $b->isPool())->sortBy(function ($b) {
            $m = (array) $b->metadata;
            $copia = ($m['source'] ?? null) === 'pt4_pool';

            return sprintf('%d-%05d', $copia ? 0 : 1, $copia ? (int) ($m['primary_id'] ?? 0) : 1000 - (int) $b->vpip);
        })->values();
        $primarios = $bots->reject(fn($b) => $b->isPool())->values();

        return view('bots.index', ['bots' => $bots, 'pool' => $pool, 'primarios' => $primarios]);
    }

    public function create(Request $request)
    {
        $tipos = BotConfig::getTipos();
        $perfiles = BotConfig::getPerfiles();

        // Si es una copia, cargar el bot original
        $copyFrom = null;
        if ($request->has('copy_from')) {
            $copyFrom = Bot::find($request->input('copy_from'));
        }

        return view('bots.create', compact('tipos', 'perfiles', 'copyFrom'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|unique:bots,nombre',
            'tipo' => 'required|string',
            'perfil' => 'nullable|string',
            'vpip' => 'nullable|integer|min:0|max:100',
            'pfr' => 'nullable|integer|min:0|max:100',
            'tilt_risk' => 'nullable|integer|min:0|max:100',
            'profesional' => 'boolean',
            'descripcion' => 'nullable|string',
            'icono' => 'nullable|string|max:10',
            'copy_from' => 'nullable|exists:bots,id',
        ]);

        $bot = Bot::create($validated);

        // Si es una copia, copiar todas las estadísticas relacionadas del bot original
        if ($request->has('copy_from') && $request->input('copy_from')) {
            $originalBot = Bot::find($request->input('copy_from'));

            if ($originalBot) {
                // Copiar HUD Stats
                $originalBot->hudStats()->get()->each(function ($stat) use ($bot) {
                    $newStat = $stat->replicate();
                    $newStat->bot_id = $bot->id;
                    $newStat->save();
                });

                // Copiar Rangos
                $originalBot->ranges()->get()->each(function ($range) use ($bot) {
                    $newRange = $range->replicate();
                    $newRange->bot_id = $bot->id;
                    $newRange->save();
                });

                // Copiar Game States
                $originalBot->gameStates()->get()->each(function ($gameState) use ($bot) {
                    $newGameState = $gameState->replicate();
                    $newGameState->bot_id = $bot->id;
                    $newGameState->save();
                });
            }
        }

        return redirect()->route('bots.show', $bot)->with('success', 'Bot creado exitosamente.');
    }

    public function show(Bot $bot, RivalPreflopTables $preflopTables)
    {
        return view('bots.show', [
            'bot' => $bot,
            'preflopTables' => $bot->isPool() ? $preflopTables->forBot($bot) : [],
        ]);
    }

    public function editPreflopTable(Bot $bot, string $position, string $type, RivalPreflopTables $tables)
    {
        abort_unless($bot->isPool(), 404);
        abort_unless(in_array($position, ['UTG', 'HJ', 'CO', 'BTN', 'SB', 'BB'], true), 404);
        abort_unless(in_array($type, ['attack', 'defense'], true), 404);
        abort_if($position === 'UTG' && $type === 'defense', 404);

        $allTables = $tables->forBot($bot);

        return view('bots.preflop-table-editor', [
            'bot' => $bot,
            'tables' => [$position => $allTables[$position]],
            'onlyPosition' => $position,
            'onlyType' => $type,
        ]);
    }

    public function edit(Bot $bot)
    {
        $tipos = BotConfig::getTipos();
        $perfiles = BotConfig::getPerfiles();
        return view('bots.edit', compact('bot', 'tipos', 'perfiles'));
    }

    public function update(Request $request, Bot $bot)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|unique:bots,nombre,' . $bot->id,
            'tipo' => 'required|string',
            'perfil' => 'nullable|string',
            'vpip' => 'nullable|integer|min:0|max:100',
            'pfr' => 'nullable|integer|min:0|max:100',
            'tilt_risk' => 'nullable|integer|min:0|max:100',
            'profesional' => 'boolean',
            'descripcion' => 'nullable|string',
            'icono' => 'nullable|string|max:10',
        ]);

        $bot->update($validated);

        // Estadísticas (agrupadas por calle en el formulario): todas van a la fila general del bot,
        // nunca a una fila de posición/acción, y solo se aceptan columnas conocidas
        $allowed = collect(\App\Config\BotStreets::STREETS)->flatMap(fn($s) => array_merge($s['stats'], $s['adaptativas']))->all();
        $values = [];
        foreach ($request->input('stats.1', []) as $statKey => $statValue) {
            if (in_array($statKey, $allowed, true) && $statValue !== null && $statValue !== '' && is_numeric($statValue)) {
                $values[$statKey] = \App\Config\BotStreets::isRatio($statKey) ? (float) $statValue : (int) round($statValue);
            }
        }
        if ($values) {
            $general = $bot->generalStats() ?? $bot->hudStats()->create(['nivel' => 1]);
            $general->update($values);
            // vpip/pfr del bot se mantienen en sintonía con su fila general
            $bot->update(array_intersect_key($values, ['vpip' => 1, 'pfr' => 1]));
        }

        return redirect()->route('bots.show', $bot)->with('success', 'Bot actualizado exitosamente.');
    }

    public function updatePreflopRange(Request $request, Bot $bot, string $position, string $hand, RivalPreflopTables $tables)
    {
        abort_unless($bot->isPool(), 404);
        abort_unless(in_array($position, ['UTG', 'HJ', 'CO', 'BTN', 'SB', 'BB'], true), 404);
        abort_unless($tables->hasHand($hand), 404);
        if ($position === 'UTG') {
            $request->merge([
                'defense_three_bet' => 0,
                'defense_call' => 0,
                'defense_fold' => 100,
            ]);
        }

        $validated = $request->validate([
            'table_type' => 'nullable|in:attack,defense',
            'attack_open' => 'required|numeric|min:0|max:100',
            'attack_limp' => 'required|numeric|min:0|max:100',
            'open_reraise' => 'required|numeric|min:0|max:100',
            'open_call' => 'required|numeric|min:0|max:100',
            'open_fold' => 'required|numeric|min:0|max:100',
            'limp_raise' => 'required|numeric|min:0|max:100',
            'limp_push' => 'required|numeric|min:0|max:100',
            'defense_three_bet' => 'required|numeric|min:0|max:100',
            'defense_call' => 'required|numeric|min:0|max:100',
            'defense_fold' => 'required|numeric|min:0|max:100',
        ]);

        if ((float) $validated['attack_open'] + (float) $validated['attack_limp'] > 100.01) {
            return back()->withErrors(['attack' => 'Abrir y limp no pueden superar el 100%.'])->withInput();
        }

        if (abs((float) $validated['open_reraise'] + (float) $validated['open_call'] + (float) $validated['open_fold'] - 100) > 0.01) {
            return back()->withErrors(['open_response' => 'Las respuestas al 3-bet deben sumar el 100%.'])->withInput();
        }

        if (abs((float) $validated['defense_three_bet'] + (float) $validated['defense_call'] + (float) $validated['defense_fold'] - 100) > 0.01) {
            return back()->withErrors(['defense' => 'Las frecuencias de defensa deben sumar el 100%.'])->withInput();
        }

        if ((float) $validated['limp_raise'] + (float) $validated['limp_push'] > 100.01) {
            return back()->withErrors(['limp_response' => 'Limp-raise y limp-push no pueden superar el 100%.'])->withInput();
        }

        $tableType = $validated['table_type'] ?? 'attack';
        unset($validated['table_type']);

        $bot->preflopHandRanges()->updateOrCreate(
            ['position' => $position, 'hand' => $hand],
            array_merge($validated, ['is_manual' => true])
        );

        return redirect()->route('bots.preflop-tables.edit', [
            'bot' => $bot,
            'position' => $position,
            'type' => $tableType,
        ])->with('success', "Frecuencias de {$hand} en {$position} guardadas.");
    }

    public function resetPreflopRange(Request $request, Bot $bot, string $position, string $hand, RivalPreflopTables $tables)
    {
        abort_unless($bot->isPool(), 404);
        abort_unless(in_array($position, ['UTG', 'HJ', 'CO', 'BTN', 'SB', 'BB'], true), 404);
        abort_unless($tables->hasHand($hand), 404);
        $type = $request->validate(['table_type' => 'nullable|in:attack,defense'])['table_type'] ?? 'attack';

        $bot->preflopHandRanges()
            ->where('position', $position)
            ->where('hand', $hand)
            ->delete();

        return redirect()->route('bots.preflop-tables.edit', [
            'bot' => $bot,
            'position' => $position,
            'type' => $type,
        ])->with('success', "Frecuencias de {$hand} en {$position} restauradas al cálculo del perfil.");
    }

    public function destroy(Bot $bot)
    {
        $nombre = $bot->nombre;
        $bot->delete();
        return redirect()->route('bots.index')->with('success', "Bot '{$nombre}' eliminado exitosamente.");
    }

    public function duplicate(Bot $bot)
    {
        // Redirigir al formulario de creación con los datos del bot original como parámetro
        return redirect()->route('bots.create', ['copy_from' => $bot->id]);
    }

    public function api()
    {
        return response()->json(Bot::all());
    }

    public function apiShow(Bot $bot)
    {
        return response()->json($bot->load('hudStats', 'ranges', 'gameStates'));
    }
}
