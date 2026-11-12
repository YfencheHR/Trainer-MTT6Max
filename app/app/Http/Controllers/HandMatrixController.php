<?php

namespace App\Http\Controllers;

use App\Models\HandGroup;
use App\Models\HandGroupValue;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HandMatrixController extends Controller
{
    public function index()
    {
        $allHands = HandGroup::orderBy('group_name')->get();

        return view('hand-matrix.index', compact('allHands'));
    }

    public function getFilterStructure()
    {
        try {
            // Cargar TODA la estructura de filtros de una sola vez
            // Escenarios permitidos
            $query = HandGroupValue::query();
            $allValues = $query->whereIn('scenario', ['Nash Push', 'Nash Call', 'Opener', 'Defense', 'Ataque', 'Defensa', 'ISO/LIMP'])->get();

            $structure = [];
            $processed = [];

            // Agrupar por scenario, position, stack depth
            foreach ($allValues as $item) {
                $scenario = $item->scenario;

                if (! isset($structure[$scenario])) {
                    $structure[$scenario] = [];
                }

                // Agregar posiciones para este scenario
                $positions = is_array($item->positions) ? $item->positions : [$item->positions];

                foreach ($positions as $position) {
                    if (! isset($structure[$scenario][$position])) {
                        $structure[$scenario][$position] = [];
                    }

                    // Generar etiqueta de stack depth
                    $stackLabel = ($item->stack_depth_min == 0 && $item->stack_depth_max == 20)
                        ? '<20bb'
                        : $item->stack_depth_min.'-'.$item->stack_depth_max.'bb';

                    // Clave única para evitar duplicados
                    $uniqueKey = $scenario.'|'.$position.'|'.$stackLabel;

                    if (! isset($processed[$uniqueKey])) {
                        $structure[$scenario][$position][] = [
                            'label' => $stackLabel,
                            'min' => (int) $item->stack_depth_min,
                            'max' => (int) $item->stack_depth_max,
                        ];
                        $processed[$uniqueKey] = true;
                    }
                }
            }

            return response()->json($structure);
        } catch (\Throwable $e) {
            \Log::error('getFilterStructure error: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    public function show(HandGroup $handGroup, Request $request)
    {
        $scenario = $request->query('scenario', 'Nash Push');
        $position = $request->query('position', 'BTN');
        $stackDepth = $request->query('stack_depth', 50);

        return response()->json([
            'id' => $handGroup->id,
            'group_name' => $handGroup->group_name,
            'combinatorios' => $handGroup->combinatorios,
            'ev' => $handGroup->getValue($scenario, $position, $stackDepth),
        ]);
    }

    public function getMatrixValues(Request $request)
    {
        $scenario = $request->query('scenario', 'Nash Push');
        $position = $request->query('position', 'BTN');
        $stackDepth = $request->query('stack_depth', 50);
        $stackDepthMax = $request->query('stack_depth_max');

        $allHands = HandGroup::orderBy('group_name')->get();
        $strategyValues = HandGroupValue::query()
            ->byScenario($scenario)
            ->where('stack_depth_min', '<=', $stackDepth)
            ->where('stack_depth_max', '>=', $stackDepth)
            ->get()
            ->filter(fn (HandGroupValue $value) => in_array($position, $value->positions ?? [], true))
            ->groupBy('hand_group_id')
            ->map(fn (Collection $values) => $this->selectStrategyValue($values, $stackDepth, $stackDepthMax));

        $result = $allHands->map(function ($hand) use ($scenario, $position, $stackDepth, $strategyValues) {
            $strategy = $strategyValues->get($hand->id);

            return [
                'id' => $hand->id,
                'group_name' => $hand->group_name,
                'ev' => $hand->getValue($scenario, $position, $stackDepth),
                'strategy_value_id' => $strategy?->id,
                'actions' => $strategy?->actions,
            ];
        });

        return response()->json($result);
    }

    public function batchUpdateStrategies(Request $request)
    {
        $validated = $request->validate([
            'scenario' => 'required|string|in:Nash Push,Nash Call,Opener,Defense,Caller,Pusher,Ataque,Defensa,ISO/LIMP',
            'position' => 'required|string|in:UTG,LJ,HJ,CO,BTN,SB,BB',
            'stack_depth_min' => 'required|integer|min:0|max:255',
            'stack_depth_max' => 'required|integer|min:0|max:255|gte:stack_depth_min',
            'updates' => 'required|array|min:1',
            'updates.*.hand_group_id' => 'required|integer|exists:hand_groups,id',
            'updates.*.actions' => 'required|array|min:1',
            'updates.*.actions.*' => 'required|array',
            'updates.*.actions.*.frequency_pct' => 'required|numeric|min:0|max:100',
            'updates.*.actions.*.ev_bb' => 'required|numeric',
            'updates.*.actions.*.size_bb' => 'sometimes|nullable|numeric|min:0|max:1000',
        ]);

        $errors = [];
        foreach ($validated['updates'] as $index => $update) {
            $frequencyTotal = 0.0;

            foreach ($update['actions'] as $actionKey => $action) {
                if (! preg_match('/^[a-z][a-z0-9_]*$/D', (string) $actionKey)) {
                    $errors["updates.{$index}.actions.{$actionKey}"][] =
                        'Action keys must start with a lowercase letter and contain only lowercase letters, numbers, and underscores.';
                }

                $frequencyTotal += (float) $action['frequency_pct'];
            }

            if (abs($frequencyTotal - 100) > 0.01) {
                $errors["updates.{$index}.actions"][] = 'Action frequencies must total 100%.';
            }
        }

        if ($errors) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], 422);
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated['updates'] as $update) {
                $hand = HandGroup::findOrFail($update['hand_group_id']);
                $strategy = $this->findStrategyValue(
                    $hand,
                    $validated['scenario'],
                    $validated['position'],
                    $validated['stack_depth_min'],
                    $validated['stack_depth_max']
                );

                if ($strategy && count($strategy->positions ?? []) > 1) {
                    $remainingPositions = array_values(array_filter(
                        $strategy->positions,
                        fn (string $position) => $position !== $validated['position']
                    ));
                    $strategy->positions = $remainingPositions;
                    $strategy->save();

                    $strategy = new HandGroupValue([
                        'hand_group_id' => $hand->id,
                        'scenario' => $validated['scenario'],
                        'positions' => [$validated['position']],
                        'stack_depth_min' => $strategy->stack_depth_min,
                        'stack_depth_max' => $strategy->stack_depth_max,
                        'ev' => $strategy->ev,
                    ]);
                }

                if (! $strategy) {
                    $strategy = new HandGroupValue([
                        'hand_group_id' => $hand->id,
                        'scenario' => $validated['scenario'],
                        'positions' => [$validated['position']],
                        'stack_depth_min' => $validated['stack_depth_min'],
                        'stack_depth_max' => $validated['stack_depth_max'],
                        'ev' => 0,
                    ]);
                }

                $strategy->actions = $update['actions'];
                $strategy->save();
            }
        });

        return response()->json([
            'message' => __('hand-matrix.strategy_update_success'),
            'updated' => count($validated['updates']),
        ]);
    }

    private function findStrategyValue(
        HandGroup $hand,
        string $scenario,
        string $position,
        int|string $stackDepth,
        int|string|null $stackDepthMax = null
    ): ?HandGroupValue {
        $values = $hand->values()
            ->byScenario($scenario)
            ->where('stack_depth_min', '<=', $stackDepth)
            ->where('stack_depth_max', '>=', $stackDepth)
            ->orderByDesc('stack_depth_max')
            ->get()
            ->filter(fn (HandGroupValue $value) => in_array($position, $value->positions ?? [], true));

        return $this->selectStrategyValue($values, $stackDepth, $stackDepthMax);
    }

    private function selectStrategyValue(
        Collection $values,
        int|string|null $stackDepthMin = null,
        int|string|null $stackDepthMax = null
    ): ?HandGroupValue {
        return $values
            ->sort(function (HandGroupValue $left, HandGroupValue $right) use ($stackDepthMin, $stackDepthMax) {
                if ($stackDepthMin !== null && $stackDepthMax !== null) {
                    $leftExact = (int) $left->stack_depth_min === (int) $stackDepthMin
                        && (int) $left->stack_depth_max === (int) $stackDepthMax;
                    $rightExact = (int) $right->stack_depth_min === (int) $stackDepthMin
                        && (int) $right->stack_depth_max === (int) $stackDepthMax;

                    if ($leftExact !== $rightExact) {
                        return $leftExact ? -1 : 1;
                    }
                }

                $positionCount = count($left->positions ?? []) <=> count($right->positions ?? []);

                if ($positionCount !== 0) {
                    return $positionCount;
                }

                $leftWidth = (int) $left->stack_depth_max - (int) $left->stack_depth_min;
                $rightWidth = (int) $right->stack_depth_max - (int) $right->stack_depth_min;
                if ($leftWidth !== $rightWidth) {
                    return $leftWidth <=> $rightWidth;
                }

                return (int) $right->stack_depth_max <=> (int) $left->stack_depth_max;
            })
            ->first();
    }

    public function update(Request $request, HandGroup $handGroup)
    {
        $validated = $request->validate([
            'gto_ev' => 'required|numeric|between:0,100',
        ]);

        $handGroup->update($validated);

        return response()->json([
            'message' => __('hand-matrix.update_success'),
            'group' => $handGroup,
        ]);
    }

    public function batchUpdate(Request $request)
    {
        $validated = $request->validate([
            'updates' => 'required|array',
            'updates.*.id' => 'required|exists:hand_groups,id',
            'updates.*.ev' => 'required|numeric|between:0,100',
        ]);

        $updated = 0;
        foreach ($validated['updates'] as $update) {
            HandGroup::find($update['id'])->update(['gto_ev' => $update['ev']]);
            $updated++;
        }

        return response()->json([
            'message' => __('hand-matrix.update_success'),
            'updated' => $updated,
        ]);
    }
}
