<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentHand;
use App\Services\History\TournamentHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentHistoryController extends Controller
{
    public function __construct(private TournamentHistoryService $history)
    {
    }

    /** GET /mtt-simulator/{tournament}/history */
    public function index(Tournament $tournament, Request $request)
    {
        $filters = [
            'table' => $request->integer('table') ?: null,
            'hero' => $request->boolean('hero'),
            'player' => $request->integer('player') ?: null,
        ];

        return view('mtt-simulator.history', [
            'tournament' => $tournament,
            'data' => $this->history->overview($tournament, $filters, $request->integer('page', 1)),
            'filters' => $filters,
            'tables' => $tournament->tables()->orderBy('table_number')->get(),
            'players' => $tournament->players()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** GET /mtt-simulator/hand/{hand}/history  (detalle de una mano terminada) */
    public function hand(TournamentHand $hand): JsonResponse
    {
        if ($hand->status !== 'completed') {
            return response()->json(['success' => false, 'message' => 'La mano aún no ha terminado'], 409);
        }

        // Manos grabadas antes del módulo de EV: se analizan la primera vez que se consultan
        if (!$hand->ev_analyzed_at && $hand->handActions()->exists()) {
            app(\App\Services\Ev\DecisionEvAnalyzer::class)->analyze($hand);
            $hand->save();
        }

        return response()->json(['success' => true, 'data' => $this->history->detail($hand)]);
    }

    /** GET /mtt-simulator/{tournament}/history/export  (JSON completo para análisis) */
    public function export(Tournament $tournament): JsonResponse
    {
        return response()
            ->json($this->history->export($tournament), 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ->header('Content-Disposition', 'attachment; filename="torneo-' . $tournament->id . '-historial.json"');
    }
}
