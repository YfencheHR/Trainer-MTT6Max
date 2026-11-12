<?php

namespace App\Services\History;

use App\Models\TournamentHand;
use Illuminate\Support\Facades\DB;

/**
 * Graba el historial estructurado de cada mano mientras se juega:
 *  - quién está sentado (asiento, posición, stack inicial, cartas)
 *  - cada acción en orden (ciegas, fold/check/call/raise/allin, reparto de calles)
 *  - al terminar: stacks finales, ganancias, nombre de la mano mostrada, botes y board completo.
 *
 * Con esto se puede reconstruir la mano completa sin depender del estado interno del motor.
 */
class HandHistoryRecorder
{
    /** Sienta a los jugadores de la mano (se llama ANTES de poner las ciegas). */
    /** @param array $stats [player_id => stats observadas antes de la mano] */
    public function seat(TournamentHand $hand, array $players, array $positions, array $stats = []): void
    {
        $now = now();
        $rows = [];
        foreach ($players as $i => $p) {
            $rows[] = [
                'tournament_hand_id' => $hand->id,
                'tournament_player_id' => $p['id'],
                'seat' => $p['seat'] ?? ($i + 1),
                'position' => $positions[$i] ?? null,
                'stack_start' => $p['stack'],
                'hole_cards' => json_encode($p['hole_cards']),
                'stats_before' => isset($stats[$p['id']]) ? json_encode($stats[$p['id']]) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('hand_players')->insert($rows);
    }

    /**
     * Registra una acción. El orden lo da meta['seq'] (se incrementa aquí).
     * type: post_sb, post_bb, fold, check, call, raise, allin, deal
     */
    public function action(
        TournamentHand $hand,
        array &$meta,
        string $street,
        ?int $playerId,
        string $type,
        int $amount,
        ?int $toAmount,
        int $potAfter,
        ?int $stackAfter,
        ?array $cards = null,
        array $extra = [], // pot_before, stack_before, to_call, players_active, decision
    ): void {
        $meta['seq'] = ($meta['seq'] ?? 0) + 1;

        DB::table('hand_actions')->insert([
            'tournament_hand_id' => $hand->id,
            'sequence' => $meta['seq'],
            'street' => $street,
            'tournament_player_id' => $playerId,
            'type' => $type,
            'amount' => $amount,
            'to_amount' => $toAmount,
            'pot_after' => $potAfter,
            'stack_after' => $stackAfter,
            'cards' => $cards !== null ? json_encode($cards) : null,
            'pot_before' => $extra['pot_before'] ?? max(0, $potAfter - $amount),
            'stack_before' => $extra['stack_before'] ?? null,
            'to_call' => $extra['to_call'] ?? 0,
            'players_active' => $extra['players_active'] ?? null,
            'decision' => isset($extra['decision']) ? json_encode($extra['decision']) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Cierra la mano: resultado de cada jugador, botes y board completo.
     *
     * @param array $awards    [player_id => fichas ganadas]
     * @param array $pots      [['label','amount','winners'=>[ids],'hand_name']]
     * @param array $handNames [player_id => nombre de la mano mostrada en el showdown]
     */
    public function finish(TournamentHand $hand, array $players, array $awards, array $pots, array $meta, array $handNames): void
    {
        foreach ($players as $p) {
            DB::table('hand_players')
                ->where('tournament_hand_id', $hand->id)
                ->where('tournament_player_id', $p['id'])
                ->update([
                    'stack_end' => $p['stack'],
                    'won' => $awards[$p['id']] ?? 0,
                    'folded_street' => $p['folded_street'] ?? null,
                    'went_to_showdown' => isset($handNames[$p['id']]),
                    'hand_name' => $handNames[$p['id']] ?? null,
                    'updated_at' => now(),
                ]);
        }

        $hand->board_full = $meta['board_full'] ?? null;
        $hand->pots = $pots;
    }
}
