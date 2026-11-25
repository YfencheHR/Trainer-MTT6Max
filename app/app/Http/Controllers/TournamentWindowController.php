<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\Tournament\TournamentWindowLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Qué ventana puede jugar un torneo: la mesa pide el turno al abrirse, lo renueva mientras sigue abierta y lo libera al cerrarse. */
class TournamentWindowController extends Controller
{
    public function __construct(private TournamentWindowLock $windows)
    {
    }

    /** Pide (o renueva) el turno. `take` = quedarse con el torneo aunque lo tenga otra ventana. */
    public function claim(Tournament $tournament, Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:100', 'take' => 'nullable|boolean']);

        return response()->json(['success' => true, 'data' => $this->windows->claim($tournament->id, $data['token'], (bool) ($data['take'] ?? false))]);
    }

    /** La ventana se cierra (también por sendBeacon): libera el torneo si era la activa. */
    public function release(Tournament $tournament, Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:100']);
        $this->windows->release($tournament->id, $data['token']);

        return response()->json(['success' => true]);
    }
}
