<?php

namespace App\Http\Controllers;

use App\Services\Equity\HandMatrixGenerator;
use App\Services\Equity\EquitySimulationClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EquitySimulationController extends Controller
{
    public function __construct(
        private HandMatrixGenerator $generator,
        private EquitySimulationClient $client
    ) {}

    /**
     * Muestra la página de simulación interactiva
     */
    public function simulator()
    {
        $index = $this->generator->loadIndex();
        $stackDepths = array_keys($this->generator->loadIndex()['stack_depths'] ?? []);

        return view('equity.simulator', [
            'stackDepths' => $stackDepths,
            'index' => $index,
        ]);
    }

    /**
     * API: Simula equity entre dos manos/rangos
     */
    public function simulate(Request $request)
    {
        try {
            $validated = $request->validate([
                'hero_hand' => 'required|string|max:3',
                'villain_range' => 'required|array|min:1',
                'villain_range.*' => 'string|max:3',
                'iterations' => 'integer|min:100|max:50000',
            ]);

            $result = $this->client->simulateEquity(
                $validated['hero_hand'],
                $validated['villain_range'],
                $validated['iterations'] ?? 5000
            );

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Equity simulation error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * API: Obtiene rango de ataque/defensa para una profundidad
     */
    public function getRange(Request $request)
    {
        try {
            $type = $request->query('type'); // 'attack' or 'defense'
            $depth = $request->query('depth');

            if (!in_array($type, ['attack', 'defense'])) {
                return response()->json(['error' => 'Invalid type'], 400);
            }

            $method = $type === 'attack' ? 'getAttackRange' : 'getDefenseRange';
            $range = $this->generator->$method($depth);

            return response()->json([
                'status' => 'success',
                'type' => $type,
                'depth' => $depth,
                'range' => $range,
                'count' => count($range),
                'percent' => round(count($range) / 169 * 100, 2),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * API: Verifica si una mano está en un rango
     */
    public function checkHand(Request $request)
    {
        try {
            $hand = $request->query('hand');
            $type = $request->query('type');
            $depth = $request->query('depth');

            $method = $type === 'attack' ? 'isInAttackRange' : 'isInDefenseRange';
            $inRange = $this->generator->$method($hand, $depth);

            $handInfo = $this->generator->getHandInfo($hand, $type, $depth);

            return response()->json([
                'status' => 'success',
                'hand' => $hand,
                'type' => $type,
                'depth' => $depth,
                'in_range' => $inRange,
                'info' => $handInfo,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * API: Compara múltiples manos/configuraciones
     */
    public function compare(Request $request)
    {
        try {
            $validated = $request->validate([
                'hero_hand' => 'required|string|max:3',
                'scenarios' => 'required|array|min:1',
                'scenarios.*.villain_range' => 'required|array|min:1',
                'scenarios.*.villain_range.*' => 'string|max:3',
                'scenarios.*.name' => 'string|max:100',
            ]);

            $results = [];
            foreach ($validated['scenarios'] as $scenario) {
                $sim = $this->client->simulateEquity(
                    $validated['hero_hand'],
                    $scenario['villain_range'],
                    5000
                );

                $results[] = [
                    'name' => $scenario['name'] ?? implode(',', $scenario['villain_range']),
                    'villain_range_size' => count($scenario['villain_range']),
                    'equity' => $sim['equity'],
                    'wins' => $sim['wins'],
                    'losses' => $sim['losses'],
                ];
            }

            return response()->json([
                'status' => 'success',
                'hero_hand' => $validated['hero_hand'],
                'scenarios' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * API: Estado de las tablas generadas
     */
    public function status()
    {
        $index = $this->generator->loadIndex();

        if (!$index) {
            return response()->json([
                'status' => 'not_generated',
                'message' => 'Tablas aún no se han generado. Ejecuta: php artisan equity:generate-tables',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'generated_at' => $index['generated_at'] ?? null,
            'version' => $index['version'] ?? null,
            'stack_depths' => $index['stack_depths'] ?? [],
            'total_tables' => $index['summary']['total_tables'] ?? 0,
        ]);
    }
}
