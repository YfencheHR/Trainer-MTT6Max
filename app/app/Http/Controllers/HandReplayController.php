<?php

namespace App\Http\Controllers;

use App\Models\HandReplay;
use App\Models\TournamentHand;
use App\Services\Replay\HandReplayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Repetir una mano jugada con decisiones nuevas de los bots y ver cuánto se parecen a lo que hicieron (ver HandReplayService). */
class HandReplayController extends Controller
{
    /** Máximo de repeticiones de una vez: cada una juega la mano entera con el motor. */
    private const MAX_TIMES = 50;

    public function __construct(private HandReplayService $replays)
    {
    }

    /** GET /mtt-simulator/hand/{hand}/replays */
    public function show(TournamentHand $hand)
    {
        abort_unless($hand->status === 'completed', 404);
        $replays = HandReplay::where('tournament_hand_id', $hand->id)->with('actions')->latest('id')->limit(100)->get();

        return view('mtt-simulator.hand-replays', [
            'hand' => $hand->load('tournament', 'table'),
            'players' => collect((array) $hand->players_in_hand),
            'played' => $this->replays->actionsOf($hand),
            'replays' => $replays,
            'stats' => $this->stats($replays),
        ]);
    }

    /**
     * GET /mtt-simulator/hand/{hand}/replays/frames?version=original|{id de repetición}
     * La mesa paso a paso de la mano jugada o de una repetición, con el formato de estado de la mesa de juego.
     */
    public function frames(TournamentHand $hand, Request $request): JsonResponse
    {
        abort_unless($hand->status === 'completed', 404);
        $version = (string) $request->query('version', 'original');
        if ($version === 'original') {
            $actions = $this->replays->actionsOf($hand);
        } else {
            $replay = HandReplay::where('tournament_hand_id', $hand->id)->findOrFail((int) $version);
            $actions = $this->replays->replayActions($replay);
        }

        return response()->json(['success' => true, 'data' => ['frames' => $this->replays->frames($hand, $actions)]]);
    }

    /** POST /mtt-simulator/hand/{hand}/replays */
    public function store(TournamentHand $hand, Request $request): RedirectResponse
    {
        $data = $request->validate(['times' => 'required|integer|min:1|max:' . self::MAX_TIMES]);
        try {
            $this->replays->replay($hand, (int) $data['times']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('mtt-simulator.hand.replays', $hand)->with('status', __('tournaments.replay_done', ['n' => $data['times']]));
    }

    /**
     * Resumen de las repeticiones guardadas: cuántas se desviaron, cuántas terminaron con el mismo ganador y, por jugador, en cuántas hizo exactamente lo
     * mismo que en la mano jugada.
     */
    private function stats($replays): array
    {
        $total = $replays->count();
        $perPlayer = [];
        foreach ($replays as $replay) {
            $byPlayer = $replay->actions->groupBy('side')->map(fn($rows) => $rows->groupBy('player_name')->map(fn($r) => $r->map(fn($a) => $a->street . ' ' . $a->action . ' ' . $a->amount)->implode(' · ')));
            foreach ($byPlayer['original'] ?? [] as $name => $line) {
                $perPlayer[$name]['same'] = ($perPlayer[$name]['same'] ?? 0) + (($byPlayer['replay'][$name] ?? null) === $line ? 1 : 0);
            }
        }

        return [
            'total' => $total,
            'diverged' => $replays->where('diverged', true)->count(),
            'same_winner' => $replays->where('same_winner', true)->count(),
            'per_player' => $perPlayer,
        ];
    }
}
