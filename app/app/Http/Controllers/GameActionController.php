<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentHand;
use App\Services\HandEngine;
use App\Services\HandActionHandler;
use App\Services\HandEvaluator;
use Illuminate\Http\Request;

class GameActionController extends Controller
{
    public function startHand(Tournament $tournament)
    {
        try {
            $engine = new HandEngine($tournament);
            $hand = $engine->createNewHand();

            return response()->json([
                'success' => true,
                'hand' => [
                    'id' => $hand->id,
                    'hand_number' => $hand->hand_number,
                    'button_seat' => $hand->button_seat,
                    'small_blind_seat' => $hand->small_blind_seat,
                    'big_blind_seat' => $hand->big_blind_seat,
                    'hero_cards' => $hand->hero_cards,
                    'status' => $hand->status,
                    'players_in_hand' => $hand->players_in_hand,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function recordAction(TournamentHand $hand, Request $request)
    {
        try {
            $validated = $request->validate([
                'seat' => 'required|integer',
                'action_type' => 'required|in:check,call,raise,fold,all_in',
                'amount' => 'nullable|numeric|min:0',
            ]);

            $actionHandler = new HandActionHandler($hand);
            $actionHandler->recordAction(
                $validated['seat'],
                $validated['action_type'],
                $validated['amount'] ?? null
            );

            $actionHandler->saveActions();

            return response()->json([
                'success' => true,
                'pot' => $actionHandler->getStreetPot(),
                'actions' => $actionHandler->getActions(),
                'player_bets' => $actionHandler->getPlayerBets(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function moveToNextStreet(TournamentHand $hand, Request $request)
    {
        try {
            $validated = $request->validate([
                'next_street' => 'required|in:flop,turn,river,showdown',
            ]);

            $actionHandler = new HandActionHandler($hand);
            $actionHandler->moveToNextStreet($validated['next_street']);
            $actionHandler->saveActions();

            return response()->json([
                'success' => true,
                'current_street' => $validated['next_street'],
                'community_cards' => $hand->community_cards,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function evaluateShowdown(TournamentHand $hand)
    {
        try {
            $evaluator = new HandEvaluator($hand);
            $evaluation = $evaluator->evaluateHand();
            $winner = $evaluator->determineWinner();

            // Actualizar resultado de la mano
            $hand->update([
                'result' => $winner['result'],
                'winning_seat' => $winner['seat'],
                'status' => 'completed',
            ]);

            return response()->json([
                'success' => true,
                'winner' => $winner,
                'evaluation' => $evaluation,
                'hand_id' => $hand->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getHandState(TournamentHand $hand)
    {
        return response()->json([
            'hand' => [
                'id' => $hand->id,
                'hand_number' => $hand->hand_number,
                'status' => $hand->status,
                'button_seat' => $hand->button_seat,
                'small_blind_seat' => $hand->small_blind_seat,
                'big_blind_seat' => $hand->big_blind_seat,
                'hero_seat' => $hand->hero_seat,
                'hero_cards' => $hand->hero_cards,
                'community_cards' => $hand->community_cards,
                'players_in_hand' => $hand->players_in_hand,
                'actions' => $hand->actions,
                'result' => $hand->result,
                'winning_seat' => $hand->winning_seat,
            ],
        ]);
    }
}
