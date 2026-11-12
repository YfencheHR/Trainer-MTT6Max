<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TournamentHand;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function getState($handId)
    {
        try {
            $hand = TournamentHand::find($handId);

            if (!$hand) {
                return response()->json(['error' => 'Hand not found'], 404);
            }

            $hand->load('tournament.players');

            $playersArray = $hand->tournament->players->map(fn($p) => [
                'id' => (int) $p->id,
                'name' => (string) $p->name,
                'stack' => (int) $p->current_stack,
                'is_bot' => (bool) $p->is_bot,
                'is_current' => false,
                'is_eliminated' => (bool) $p->is_eliminated,
                'hole_cards' => [],
                'has_rival_data' => !$p->is_bot,
            ])->values()->toArray();

            // Marcar el primer jugador como héroe (is_current: true)
            if (!empty($playersArray)) {
                $playersArray[0]['is_current'] = true;
                // Mostrar cartas del hero
                if (is_array($hand->hero_cards) && !empty($hand->hero_cards)) {
                    $playersArray[0]['hole_cards'] = $hand->hero_cards;
                } else {
                    // Datos de prueba si no hay cartas
                    $playersArray[0]['hole_cards'] = ['A♠', 'K♣'];
                }
            }

            $state = [
                'hand_id' => (int) $hand->id,
                'players' => $playersArray,
                'board' => is_array($hand->community_cards) ? $hand->community_cards : [],
                'pot' => (int) (data_get($hand->actions, 'pot', 0)),
                'current_street' => $hand->status === 'completed' ? 'completed' : 'preflop',
                'is_all_in' => false,
                'status' => (string) ($hand->status ?? 'in_progress'),
                'eliminated_players' => [],
                'show_all_cards' => false,
            ];

            return response()->json($state);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function playerAction(Request $request, $handId)
    {
        try {
            $validated = $request->validate([
                'action' => 'required|in:fold,call,check,all-in,raise',
                'amount' => 'nullable|integer|min:0',
            ]);

            $hand = TournamentHand::find($handId);

            if (!$hand) {
                return response()->json(['error' => 'Hand not found'], 404);
            }

            $actions = $hand->actions ?? [];
            $actions[] = [
                'timestamp' => now(),
                'player_id' => auth()->id(),
                'action' => $validated['action'],
                'amount' => $validated['amount'] ?? 0,
            ];

            $hand->update(['actions' => $actions]);

            return response()->json([
                'success' => true,
                'message' => 'Action recorded',
                'action' => $validated['action'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getBotAction($handId)
    {
        try {
            $hand = TournamentHand::find($handId);

            if (!$hand) {
                return response()->json(['error' => 'Hand not found'], 404);
            }

            // Lógica simple de bot: 40% fold, 60% call
            $action = rand(1, 100) <= 40 ? 'fold' : 'call';

            return response()->json([
                'action' => $action,
                'amount' => 0,
                'timestamp' => now(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
