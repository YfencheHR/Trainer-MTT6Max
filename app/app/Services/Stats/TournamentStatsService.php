<?php

namespace App\Services\Stats;

use App\Models\TournamentPlayerStat;
use Illuminate\Support\Collection;

/**
 * Estadísticas observadas de cada jugador dentro del torneo.
 *
 * Se calculan UNA vez, al terminar la mano, reproduciendo el historial de acciones
 * ($meta['history']) y se acumulan en `tournament_player_stats`. Así los bots (y el HUD)
 * solo ven datos de manos ya jugadas, nunca de la mano en curso.
 */
class TournamentStatsService
{
    /** Manos mínimas para preferir lo observado frente al perfil configurado del bot. */
    public const MIN_HANDS_FOR_HUD = 5;

    /**
     * @param array $players  players_in_hand al terminar (id = tournament_player_id)
     * @param array $meta     actions de la mano (history, street, bb…)
     * @param array $awards   [player_id => fichas ganadas]
     * @param array $board    cartas comunitarias que llegaron a verse
     */
    public function recordHand(array $players, array $meta, array $awards, array $board): void
    {
        $deltas = $this->computeDeltas($players, $meta, $awards, $board);

        foreach ($deltas as $playerId => $inc) {
            $stat = TournamentPlayerStat::firstOrCreate(['tournament_player_id' => $playerId]);
            foreach ($inc as $counter => $value) {
                if ($value) {
                    $stat->{$counter} += $value;
                }
            }
            $stat->save();
        }
    }

    /**
     * Cálculo puro (sin BD): incrementos por jugador para una mano.
     */
    public function computeDeltas(array $players, array $meta, array $awards, array $board): array
    {
        $zero = array_fill_keys(TournamentPlayerStat::COUNTERS, 0);
        $d = [];
        foreach ($players as $p) {
            $d[$p['id']] = $zero;
            $d[$p['id']]['hands'] = 1;
        }

        $history = $meta['history'] ?? [];
        $bb = (int) ($meta['bb'] ?? 100);

        // ---------------- Preflop ----------------
        $raises = 0;
        $curMax = $bb;
        $opener = null;
        $lastRaiser = null;
        $flags = []; // flags por jugador para contar una sola vez por mano
        $foldedPreflop = [];

        foreach ($history as $h) {
            if ($h['street'] !== 'preflop') {
                continue;
            }
            $pid = $h['player_id'];
            $act = $h['action'];
            $amount = (int) $h['amount'];
            $isRaise = $act === 'raise' || ($act === 'allin' && $amount > $curMax);

            // Oportunidad de 3-bet: actúa enfrentando exactamente una subida
            if ($raises === 1) {
                $flags['three_bet_opp'][$pid] = true;
                if ($isRaise) {
                    $flags['three_bet'][$pid] = true;
                }
            }
            // Fold to 3-bet: el que abrió vuelve a actuar enfrentando una 3-bet
            if ($raises === 2 && $pid === $opener) {
                $flags['fold_to_three_bet_opp'][$pid] = true;
                if ($act === 'fold') {
                    $flags['fold_to_three_bet'][$pid] = true;
                }
            }

            if (in_array($act, ['call', 'raise', 'allin'], true)) {
                $flags['vpip'][$pid] = true;
            }
            if ($act === 'fold') {
                $foldedPreflop[$pid] = true;
            }
            if ($isRaise) {
                $raises++;
                $curMax = $amount;
                $flags['pfr'][$pid] = true;
                $opener ??= $pid;
                $lastRaiser = $pid;
            }
        }

        // ---------------- Postflop ----------------
        $sawFlop = count($board) >= 3;
        $street = null;
        $streetMax = 0;
        $cbetDone = false;

        foreach ($history as $h) {
            if ($h['street'] === 'preflop') {
                continue;
            }
            if ($h['street'] !== $street) {
                $street = $h['street'];
                $streetMax = 0;
            }
            $pid = $h['player_id'];
            $act = $h['action'];
            $amount = (int) $h['amount'];
            $aggressive = $act === 'raise' || ($act === 'allin' && $amount > $streetMax);

            // C-bet: el último agresor preflop es el primero en apostar en el flop
            if ($street === 'flop' && !$cbetDone && $pid === $lastRaiser && $streetMax === 0 && in_array($act, ['check', 'raise', 'allin', 'fold', 'call'], true)) {
                $flags['cbet_opp'][$pid] = true;
                if ($aggressive) {
                    $flags['cbet'][$pid] = true;
                }
                $cbetDone = true;
            }

            if ($aggressive) {
                $d[$pid]['aggressive_actions']++;
                $streetMax = $amount;
            } elseif ($act === 'call' || $act === 'allin') {
                $d[$pid]['passive_actions']++;
            }
        }

        // ---------------- Showdown ----------------
        $showdown = ($meta['street'] ?? '') === 'showdown';

        foreach ($players as $p) {
            $id = $p['id'];
            foreach (['vpip', 'pfr', 'three_bet_opp', 'three_bet', 'fold_to_three_bet_opp', 'fold_to_three_bet', 'cbet_opp', 'cbet'] as $k) {
                if (!empty($flags[$k][$id])) {
                    $d[$id][$k] = 1;
                }
            }
            if ($sawFlop && empty($foldedPreflop[$id])) {
                $d[$id]['saw_flop'] = 1;
                if ($showdown && !$p['folded']) {
                    $d[$id]['showdowns'] = 1;
                    if (($awards[$id] ?? 0) > 0) {
                        $d[$id]['showdown_wins'] = 1;
                    }
                }
            }
        }

        return $d;
    }

    /**
     * Resúmenes por id de jugador del torneo (vacíos si aún no hay manos).
     *
     * @return array<int, array>
     */
    public function summaries(iterable|Collection $playerIds): array
    {
        $ids = collect($playerIds)->all();
        $stats = TournamentPlayerStat::whereIn('tournament_player_id', $ids)->get()->keyBy('tournament_player_id');

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = isset($stats[$id]) ? $stats[$id]->summary() : TournamentPlayerStat::emptySummary();
        }

        return $out;
    }
}
