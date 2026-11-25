<?php

namespace App\Http\Middleware;

use App\Models\Tournament;
use App\Models\TournamentHand;
use App\Services\Tournament\TournamentWindowLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solo la ventana activa de un torneo puede jugar: rechaza (409) las acciones de cualquier otra ventana que lo tenga abierto.
 * La ventana se identifica con la cabecera X-Tournament-Window. Si nadie tiene el torneo abierto, no hay nada que proteger.
 */
class EnsureTournamentWindow
{
    public function __construct(private TournamentWindowLock $windows)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tournamentId = $this->tournamentId($request);
        if ($tournamentId !== null && ! $this->windows->allows($tournamentId, $request->header('X-Tournament-Window'))) {
            return response()->json([
                'success' => false,
                'code' => 'window_taken',
                'message' => __('tournaments.window_taken_message'),
            ], 409);
        }

        return $next($request);
    }

    private function tournamentId(Request $request): ?int
    {
        $tournament = $request->route('tournament');
        if ($tournament instanceof Tournament) {
            return $tournament->id;
        }
        if (is_numeric($tournament)) {
            return (int) $tournament;
        }
        $hand = $request->route('hand');
        $hand = $hand instanceof TournamentHand ? $hand : (is_numeric($hand) ? TournamentHand::find($hand) : null);

        return $hand?->tournament_id;
    }
}
