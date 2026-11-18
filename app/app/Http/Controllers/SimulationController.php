<?php

namespace App\Http\Controllers;

use App\Services\Tournament\SimulationLauncher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Simulación de un torneo solo de bots desde la página de importación (otra acción además de importar).
 * El progreso se consulta con import.status, igual que una importación.
 */
class SimulationController extends Controller
{
    /** POST /import/simulate */
    public function run(Request $request, SimulationLauncher $launcher): JsonResponse
    {
        $data = $request->validate([
            'players' => ['required', 'integer', 'min:12', 'max:600'],
            'speed' => ['required', 'in:' . implode(',', SimulationLauncher::SPEEDS)],
        ]);

        if ($running = $launcher->running()) {
            return response()->json(['success' => false, 'message' => __('simulation.already_running'), 'run' => $running], 409);
        }

        $id = (string) Str::uuid();
        $launcher->launch($id, (int) $data['players'], $data['speed']);

        return response()->json(['success' => true, 'run' => $id]);
    }
}
