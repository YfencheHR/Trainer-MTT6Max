<?php

namespace App\Http\Controllers;

use App\Events\GameHandUpdated;
use App\Models\TournamentHand;
use App\Models\Table;
use App\Models\Tournament;
use App\Services\HandPlayService;
use App\Services\Tournament\TournamentRoundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamePlayController extends Controller
{
    public function __construct(protected HandPlayService $service, protected TournamentRoundService $rounds)
    {
    }

    /** GET /mtt-simulator/hand/{hand}/state */
    public function getHandState(TournamentHand $hand): JsonResponse
    {
        return $this->respond(fn() => $this->service->state($hand));
    }

    /** GET /mtt-simulator/hand/{hand}/log  (manos anteriores de la mesa) */
    public function logHistory(TournamentHand $hand): JsonResponse
    {
        return $this->respond(fn() => ['log' => $this->service->logHistory($hand)]);
    }

    /** POST /mtt-simulator/hand/{hand}/action */
    public function playerAction(TournamentHand $hand, Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|in:fold,call,check,raise,allin',
            'amount' => 'nullable|numeric|min:0',
        ]);

        return $this->respond(function () use ($hand, $data) {
            $state = $this->service->heroAction($hand, $data['action'], (int) ($data['amount'] ?? 0));
            $this->broadcastHandUpdate($hand->id, $state);

            return $state;
        });
    }

    /** POST /mtt-simulator/hand/{hand}/bot-action */
    public function botAction(TournamentHand $hand): JsonResponse
    {
        return $this->respond(function () use ($hand) {
            $state = $this->service->botAction($hand);
            $this->broadcastHandUpdate($hand->id, $state);

            return $state;
        });
    }

    /** POST /mtt-simulator/hand/{hand}/next */
    public function nextHand(TournamentHand $hand): JsonResponse
    {
        // Cierra la ronda: eliminaciones, nivelación de mesas y siguiente mano del héroe
        return $this->respond(function () use ($hand) {
            $result = $this->rounds->finishRound($hand);
            if ($result['finished']) {
                $hand->refresh();
                $this->broadcastHandUpdate(
                    $hand->id,
                    $this->service->state($hand),
                    true,
                    $result['message'] ?? 'El torneo ha terminado',
                );
            } elseif (!empty($result['hand_id'])) {
                $nextHand = TournamentHand::findOrFail($result['hand_id']);
                $this->broadcastHandUpdate($hand->id, $this->service->state($nextHand));
            }

            return $result;
        });
    }

    private function broadcastHandUpdate(int $channelHandId, array $state, bool $finished = false, ?string $message = null): void
    {
        try {
            broadcast(new GameHandUpdated($channelHandId, $state, $finished, $message));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** POST /mtt-simulator/{tournament}/tables/{table}/resolve  (mesas secundarias de la ronda) */
    public function resolveTable(Tournament $tournament, Table $table, Request $request): JsonResponse
    {
        $data = $request->validate(['round' => 'required|integer|min:1']);
        abort_unless((int) $table->tournament_id === (int) $tournament->id, 404);

        return $this->respond(fn() => $this->rounds->resolveTable($tournament, $table, (int) $data['round']));
    }

    private function respond(callable $fn): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $fn()]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
