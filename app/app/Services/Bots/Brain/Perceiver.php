<?php

namespace App\Services\Bots\Brain;

use App\Services\Ev\EquityCalculator;
use App\Services\Ev\FastHandEvaluator as F;
use Random\Randomizer;

/**
 * Capa de percepción: convierte la situación de la mesa en las características (features) que
 * usan las reglas, limitadas por la HABILIDAD del bot.
 *
 *  - Un jugador experto percibe su mano y su equity tal cual son.
 *  - Un jugador flojo puede NO reconocer manos sutiles: una escalera intercalada, con As en el board,
 *    la rueda A-2-3-4-5… Entonces percibe una mano peor y sus reglas actuarán en consecuencia
 *    (por ejemplo, con la mano ganadora puede acabar con fold en su matriz).
 *  - Además se añade ruido al estimar su fuerza y su equity.
 *
 * Config en el cerebro:  "skill": {
 *     "perception": { "straight": 0.9, "flush": 0.95, "straight_flush": 0.9, "complexity_penalty": 0.15, "draws": 0.8 },
 *     "noise":      { "rank_sigma": 4, "equity_sigma": 3 },
 *     "equity_samples": 250
 * }
 *
 * Además de NO VER manos fuertes, un jugador flojo se equivoca por SOBREESTIMAR:
 *  - no cuenta con que el rival tenga escalera o color según el board ("board_awareness" bajo) y se ve mejor
 *    de lo que está; "rank_bias" / "equity_bias" le suman optimismo.
 *  - cree que le farolean aunque la secuencia de acciones y el board no tengan faroles posibles
 *    ("suspicion" alto): su bluff_likelihood sube hacia un valor alto con independencia de la realidad.
 *
 * Todo el azar sale del generador que se pasa (con semilla), así que una decisión se puede reproducir.
 */
class Perceiver
{
    private const MADE = ['high_card', 'pair', 'two_pair', 'trips', 'straight', 'flush', 'full_house', 'quads', 'straight_flush'];
    private const STREETS = ['preflop' => 0, 'flop' => 1, 'turn' => 2, 'river' => 3];
    private const POSITION_GROUP = ['UTG' => 'early', 'HJ' => 'middle', 'CO' => 'late', 'BTN' => 'late', 'SB' => 'blinds', 'BB' => 'blinds'];
    private const POSITION_FACTOR = ['early' => 0.8, 'middle' => 0.95, 'late' => 1.25, 'blinds' => 1.0];

    public function __construct(private PreflopRanking $ranking, private EquityCalculator $equity)
    {
    }

    /**
     * @return array{features: array, real: array, notes: array}
     */
    public function perceive(array $ctx, array $brain, Randomizer $rng): array
    {
        $skill = $brain['skill'] ?? [];
        $me = $ctx['me'];
        $bb = max(1, (int) $ctx['big_blind']);
        $street = $ctx['street'];
        $hole = F::cards($me['hole_cards']);
        $board = F::cards($ctx['board'] ?? []);
        $notes = ['rolls' => []];
        $real = [];

        // ------------------------------------------------ situación
        $others = array_values(array_filter($ctx['players'], fn($p) => $p['id'] !== $me['id'] && !$p['folded']));
        $hist = $this->historyStats($ctx['history'] ?? [], $street, $me['id'], $bb);
        $toCall = (int) $ctx['to_call'];
        $pot = (int) $ctx['pot'];
        // Depth for preflop strategy uses each live player's total stack in this hand
        // (remaining stack + chips already committed), then caps at the shortest rival.
        $opponentHandStacks = array_map(
            fn($p) => max(0, (int) ($p['stack'] ?? 0) + (int) ($p['total_in_hand'] ?? 0)),
            $others,
        );
        $effectiveHandStack = $opponentHandStacks
            ? min(max(0, (int) $me['stack'] + (int) ($me['total_in_hand'] ?? 0)), min($opponentHandStacks))
            : 0;

        // SPR only counts chips still behind against opponents who can continue betting.
        $opponentsWhoCanAct = array_values(array_filter(
            $others,
            fn($p) => empty($p['all_in']) && (int) ($p['stack'] ?? 0) > 0
        ));
        $shortestOpponentBehind = $opponentsWhoCanAct
            ? min(array_map(fn($p) => (int) $p['stack'], $opponentsWhoCanAct))
            : 0;
        $effectiveStackBehind = $opponentsWhoCanAct
            ? min((int) $me['stack'], $shortestOpponentBehind)
            : 0;
        $callAmount = min($toCall, $me['stack']);
        $effectiveStackAfterCall = $opponentsWhoCanAct
            ? min(max(0, $me['stack'] - $callAmount), $shortestOpponentBehind)
            : 0;
        $group = self::POSITION_GROUP[$me['position']] ?? 'middle';

        $f = [
            'street' => $street,
            'street_index' => self::STREETS[$street] ?? 0,
            'position' => $me['position'],
            'position_group' => $group,
            'opponents' => count($others),
            'limpers' => $hist['limpers'],
            'preflop_raises' => $hist['preflop_raises'],
            'raises_this_street' => $hist['raises_this_street'],
            'is_preflop_aggressor' => $hist['preflop_aggressor'] === $me['id'],
            'i_raised_preflop' => $hist['i_raised_preflop'],
            'i_limped_preflop' => $hist['i_limped_preflop'],
            'callers_after_raise' => $hist['callers_after_raise'],
            'facing_bet' => $toCall > 0,
            'facing_allin' => $toCall > 0 && $toCall >= $me['stack'],
            'can_raise' => ($ctx['legal']['raise'] ?? null) !== null,
            'stack_bb' => round($me['stack'] / $bb, 2),
            'pot_bb' => round($pot / $bb, 2),
            'to_call_bb' => round($toCall / $bb, 2),
            'bet_vs_pot' => $toCall > 0 ? round($toCall / max(1, $pot - $toCall) * 100, 1) : 0.0,
            'pot_odds' => round(($ctx['required_equity'] ?? 0) * 100, 1),
            'effective_stack_bb' => round($effectiveHandStack / $bb, 2),
            'spr' => round($effectiveStackBehind / max(1, $pot), 2),
            'spr_after_call' => round($effectiveStackAfterCall / max(1, $pot + $callAmount), 2),
        ];

        // ------------------------------------------------ mano de salida
        [$r1, $r2] = [$hole[0] % 13, $hole[1] % 13];
        $hi = max($r1, $r2);
        $lo = min($r1, $r2);
        $p = $this->ranking->percentile($me['hole_cards']);
        $noise = $skill['noise'] ?? [];
        $rankPerceived = $this->clamp($p + $this->gauss($rng) * ($noise['rank_sigma'] ?? 0) * 0.5, 0, 100);
        $f += [
            'hand_pair' => $r1 === $r2,
            'hand_suited' => intdiv($hole[0], 13) === intdiv($hole[1], 13),
            'hand_connected' => $r1 !== $r2 && ($hi - $lo) === 1,
            'hand_code' => $this->ranking->code($me['hole_cards']),
            'hand_gap' => $r1 === $r2 ? 0 : $hi - $lo - 1,
            'hand_high' => $hi,
            'preflop_rank' => round($rankPerceived, 1),
            'preflop_rank_pos' => round($this->clamp($rankPerceived / self::POSITION_FACTOR[$group], 0, 100), 1),
            'preflop_group' => $this->ranking->group($rankPerceived),
        ];
        $real['preflop_rank'] = $p;

        // ------------------------------------------------ mesa
        $f += $this->boardFeatures($board);

        // ------------------------------------------------ mano hecha, proyectos y equity
        $oppCount = max(1, count($others));
        $realEquity = $this->equity->equityVsRandom($me['hole_cards'], $oppCount, $ctx['board'] ?? [], (int) ($skill['equity_samples'] ?? 250)) * 100;
        $real['equity'] = round($realEquity, 1);
        $opponentModel = ['bluff_frequency' => 0.0];

        if (count($board) >= 3) {
            $all = [...$hole, ...$board];
            $realScore = F::score($all);
            $realCat = $realScore >> 20;
            $real['made_category'] = $realCat;
            $real['made'] = $this->madeName($realScore);
            $real['rank'] = round($this->rankPercentile($hole, $board, true, true), 1);

            // Percepción de patrones sutiles
            [$seeStraights, $seeFlushes] = $this->perceivePatterns($realScore, $hole, $board, $skill['perception'] ?? [], $rng, $notes);
            $seenClearly = $seeStraights && $seeFlushes;
            $perceivedScore = $seenClearly ? $realScore : F::score($all, $seeStraights, $seeFlushes);

            // ¿Tiene en cuenta que el rival pueda tener escalera o color? Si no, se ve mejor de lo que está
            $aware = (float) (($skill['perception'] ?? [])['board_awareness'] ?? 1.0);
            $blind = false;
            if ($aware < 1.0 && count($board) < 5 + 1) {
                $blind = !$this->roll($rng, $aware);
                $notes['rolls'][] = ['pattern' => 'board_threats', 'p' => round($aware, 2), 'seen' => !$blind];
            }
            $perceivedRank = ($seenClearly && !$blind)
                ? $real['rank']
                : $this->rankPercentile($hole, $board, $seeStraights, $seeFlushes, !$blind, !$blind);
            $perceivedRank = $this->clamp(
                $perceivedRank + $this->gauss($rng) * ($noise['rank_sigma'] ?? 0) + ($noise['rank_bias'] ?? 0),
                0, 100
            );

            $f['made_category'] = $perceivedScore >> 20;
            $f['made'] = $this->madeName($perceivedScore);
            $f['perceived_rank'] = round($perceivedRank, 1);

            // Fuerza de la mano DENTRO DE SU RANGO (el rango se estrecha en las calles posteriores)
            $stats = $brain['stats'] ?? [];
            $base = !empty($f['i_raised_preflop']) ? ($stats['pfr'] ?? 15) : ($stats['vpip'] ?? 25);
            $rangePct = max(5.0, min(100.0, (float) $base * ['flop' => 1.0, 'turn' => 0.8, 'river' => 0.65][$street]));
            $rangeRank = $this->rangePercentile($hole, $board, $seeStraights, $seeFlushes, !$blind, !$blind, $rangePct);
            $f['range_rank'] = round($this->clamp(
                $rangeRank + $this->gauss($rng) * ($noise['rank_sigma'] ?? 0) + ($noise['rank_bias'] ?? 0), 0, 100
            ), 1);
            $real['range_rank'] = round($this->rangePercentile($hole, $board, true, true, true, true, $rangePct), 1);
            $real['range_pct'] = round($rangePct, 1);
            $f['hole_cards_used'] = $this->holeCardsUsed($hole, $board, $perceivedScore, $seeStraights, $seeFlushes);

            // Proyectos (solo flop y turn)
            $draw = $board && count($board) < 5 ? $this->draws($hole, $board, $realCat) : ['type' => 'none', 'outs' => 0, 'flush_outs' => 0, 'straight_outs' => 0];
            $real['draw_type'] = $draw['type'];
            $real['draw_outs'] = $draw['outs'];
            if ($draw['outs'] > 0) {
                // El proyecto de color y el de escalera se ven (o no) por separado: cada uno con su probabilidad de percepción
                $perc = $skill['perception'] ?? [];
                $fo = $so = 0;
                if ($draw['flush_outs'] > 0) {
                    $p = (float) ($perc['flush_draws'] ?? $perc['draws'] ?? 1.0);
                    $seen = $this->roll($rng, $p);
                    $notes['rolls'][] = ['pattern' => 'flush_draw', 'p' => round($p, 2), 'seen' => $seen];
                    $fo = $seen ? $draw['flush_outs'] : 0;
                }
                if ($draw['straight_outs'] > 0) {
                    $p = (float) ($perc['straight_draws'] ?? $perc['draws'] ?? 1.0);
                    $seen = $this->roll($rng, $p);
                    $notes['rolls'][] = ['pattern' => 'straight_draw', 'p' => round($p, 2), 'seen' => $seen];
                    $so = $seen ? $draw['straight_outs'] : 0;
                }
                $draw = ['type' => match (true) {
                    $fo > 0 && $so > 0 => 'combo', $fo > 0 => 'flush', $so >= 8 => 'oesd', $so > 0 => 'gutshot', default => 'none',
                }, 'outs' => $fo + $so, 'flush_outs' => $fo, 'straight_outs' => $so];
            }
            $f['draw_type'] = $draw['type'];
            $f['draw_outs'] = $draw['outs'];
            // Color y escalera por separado: cada uno suma / multiplica por su cuenta en la fórmula
            $f['flush_draw_outs'] = $draw['flush_outs'] ?? 0;
            $f['straight_draw_outs'] = $draw['straight_outs'] ?? 0;
            // Backdoors (solo flop): color y escalera a los que les faltan DOS cartas. Valen poco, pero valen; se ven peor que un proyecto normal
            $bd = $this->backdoors($hole, $board);
            $perc = $skill['perception'] ?? [];
            $real['backdoor_flush'] = $bd['flush'];
            $real['backdoor_straight'] = $bd['straight'];
            $f['backdoor_flush'] = $bd['flush'] && $this->roll($rng, (float) ($perc['backdoor_flush'] ?? (($perc['draws'] ?? 1.0) * 0.75)));
            $f['backdoor_straight'] = $bd['straight'] && $this->roll($rng, (float) ($perc['backdoor_straight'] ?? (($perc['draws'] ?? 1.0) * 0.6)));
            $opponentModel = $this->opponentRangeModel(
                $ctx,
                $hole,
                $board,
                $perceivedScore,
                $seeStraights,
                $seeFlushes,
                $street,
                $hist
            );
            $outs = $f['draw_outs'] ?? 0;
            $drawEquity = count($board) < 5 ? $outs * (count($board) === 3 ? 4 : 2) : 0;
            $f['range_equity'] = round($this->clamp(
                $opponentModel['equity'] - $this->gauss($rng) * ($noise['equity_sigma'] ?? 0)
                - ($noise['equity_bias'] ?? 0) + $drawEquity, 0, 100), 1);
            $f['opponent_range_pct'] = round($opponentModel['range_pct'], 1);
            $f['value_combo_pct'] = round($opponentModel['value_combo_pct'], 1);
            $f['value_combos'] = $opponentModel['value_combos'];
            $f['worse_combos'] = $opponentModel['worse_combos'];
            $f['opponent_call_frequency'] = round($opponentModel['call_frequency'], 1);
            $f['opponent_fold_frequency'] = round($opponentModel['fold_frequency'], 1);
            $f['opponent_raise_frequency'] = round($opponentModel['raise_frequency'], 1);
            $f['opponent_bet_frequency'] = round($opponentModel['bet_frequency'], 1);
            $f['opponent_aggression'] = round($opponentModel['aggression'], 2);
            $f['opponent_stats_confidence'] = round($opponentModel['stats_confidence'], 2);
            $real['opp_range_pct'] = round($opponentModel['range_pct'], 1);
            $real['value_combo_pct'] = round($opponentModel['value_combo_pct'], 1);
            $real['range_equity'] = round($opponentModel['equity'], 1);
            // Sin valor de showdown: aire o la mesa juega sola (candidata a farol)
            $f['bluff_candidate'] = $f['made_category'] <= 0 || $f['hole_cards_used'] === 0;

            // Equity percibida: si ve bien su mano = equity real; si no, la que le sugiere lo que ve
            $equityPerceived = $realEquity;
            $fromRank = (1 - $perceivedRank / 100) ** $oppCount * 100;
            if ($blind) {
                $equityPerceived = $fromRank; // ignora amenazas: puede ser MÁS alta que la real
            } elseif (!$seenClearly) {
                $equityPerceived = min($realEquity, $fromRank);
            }
            $f['equity'] = round($this->clamp(
                $equityPerceived + $this->gauss($rng) * ($noise['equity_sigma'] ?? 0) + ($noise['equity_bias'] ?? 0),
                0, 100
            ), 1);
            $real['saw_clearly'] = $seenClearly;
            $real['board_blind'] = $blind;
            $real['rank_perceived'] = round($perceivedRank, 1);
        } else {
            // Preflop: sin mano hecha
            $f += [
                'made_category' => $r1 === $r2 ? 1 : 0,
                'made' => $r1 === $r2 ? 'pair' : 'high_card',
                'perceived_rank' => $f['preflop_rank'],
                'hole_cards_used' => 2,
                'draw_type' => 'none',
                'draw_outs' => 0,
                'range_rank' => $f['preflop_rank'],
                'range_equity' => round(100 - $f['preflop_rank'], 1),
                'bluff_candidate' => false,
                'equity' => round($this->clamp($realEquity + $this->gauss($rng) * ($noise['equity_sigma'] ?? 0), 0, 100), 1),
            ];
            $real['saw_clearly'] = true;
        }

        // ------------------------------------------------ rival agresor
        $agg = $this->aggressorStats($ctx, $hist['last_aggressor'], $me['id']);
        $f += [
            'agg_known' => $agg['hands'] >= 5,
            'agg_vpip' => $agg['vpip'],
            'agg_pfr' => $agg['pfr'],
            'agg_three_bet' => $agg['three_bet'],
            'agg_af' => $agg['af'],
            'agg_hands' => $agg['hands'],
        ];

        // ------------------------------------------------ ¿farol o valor? (lectura de la línea del rival)
        $barrels = $this->barrels($ctx['history'] ?? [], $hist['last_aggressor'], $street);
        $realBluff = $this->bluffLikelihood($f, $barrels, $opponentModel['bluff_frequency']);
        $suspicion = (float) ($skill['suspicion'] ?? 0.0);
        $perceivedBluff = $this->clamp(
            $realBluff + $suspicion * (75 - $realBluff) + $this->gauss($rng) * (($noise['bluff_sigma'] ?? 3)),
            0,
            $opponentModel['bluff_frequency']
        );
        $f['agg_barrels'] = $barrels['barrels'];
        $f['opponent_bluff_frequency'] = round($opponentModel['bluff_frequency'], 1);
        $f['bluff_likelihood'] = round($this->clamp($perceivedBluff, 0, 100), 1);
        $real['bluff_likelihood'] = $realBluff;

        // ------------------------------------------------ SUS estadísticas (prior de la matriz)
        $st = (($brain['stats'] ?? [])[$street] ?? []);
        $f['own_f2bet'] = $st['f2bet'] ?? null;
        $f['own_af'] = $st['af'] ?? null;
        $f['own_raise_vs_bet'] = $st['raise_vs_bet'] ?? null;
        $f['own_cr'] = $st['cr'] ?? null;
        $f['own_bet_freq'] = $street === 'flop'
            ? (!empty($f['i_raised_preflop']) ? ($st['cbet'] ?? null) : ($st['donk'] ?? null))
            : ($st['bet'] ?? null);
        $f['own_vb_ratio'] = (($brain['stats'] ?? [])['river']['vb_ratio'] ?? ($brain['stats'] ?? [])['vb_ratio'] ?? 2.0);

        // En posición / fuera de posición y papel (agresor o defensor): si el bot tiene cifras específicas para esa
        // situación (importadas de PokerTracker) sustituyen a las generales de la calle
        $f['in_position'] = $this->inPosition($me['position'], $others, $ctx['players'] ?? []);
        $f['bet_size_factor'] = 1.0;
        if ($street === 'river' && $f['in_position'] && ($f['value_combo_pct'] ?? 100) <= 20) {
            $f['bet_size_factor'] = round($this->clamp(
                1 + ((float) ($f['opponent_call_frequency'] ?? 50) - 50) * 0.004
                - ((float) ($f['opponent_raise_frequency'] ?? 10) - 10) * 0.003,
                0.8,
                1.3
            ), 2);
        }
        $role = $st[$f['in_position'] ? 'ip' : 'oop'] ?? [];
        $ipKey = $f['in_position'] ? 'ip' : 'oop';

        // Cada estadística vale para SU situación. Iniciativa: en el flop la tiene quien subió preflop; en turn y river,
        // quien apostó en la calle anterior (así se miden la c-bet y los barriles en PokerTracker)
        $mine = $this->myBets($ctx['history'] ?? [], $street, $me['id']);
        $f['has_initiative'] = $street === 'flop' ? $f['is_preflop_aggressor'] : $mine['prev'];
        $f['i_bet_this_street'] = $mine['this'];

        if ($toCall > 0 && $mine['this']) {
            // Apostó él y le han subido: aplica su fold A UNA SUBIDA (no el fold a una apuesta de continuación)
            if (isset($st['f2raise'])) {
                $f['own_f2bet'] = $st['f2raise'];
                $f['stats_source'] = 'ante_subida';
            }
        } elseif ($toCall > 0) {
            // Se enfrenta a la apuesta del que tiene la iniciativa: respuesta del defensor en su posición
            if (!$f['has_initiative'] && isset($role['def'])) {
                $d = $role['def'];
                $f['own_f2bet'] = $d['fold'];
                $f['own_raise_vs_bet'] = round($d['raise'] / max(0.01, $d['call'] + $d['raise']) * 100, 1);
                $f['stats_source'] = 'defensor_' . $ipKey;
            }
        } elseif ($f['has_initiative'] && isset($role['agg']['bet'])) {
            // Tiene la iniciativa y le toca: apuesta como agresor (c-bet en el flop, barril en turn / river)
            $f['own_bet_freq'] = $role['agg']['bet'];
            $f['stats_source'] = 'agresor_' . $ipKey;
        }

        // ------------------------------------------------ intenciones
        $flags = $me['flags'] ?? [];
        $f['flag_check_raise'] = !empty($flags['check_raise']);
        $f['flag_float'] = !empty($flags['float']);
        $f['flag_slowplay'] = !empty($flags['slowplay']);

        return ['features' => $f, 'real' => $real, 'notes' => $notes];
    }

    /** ¿Apostó o subió este jugador en esta calle y en la anterior? (para saber quién tiene la iniciativa) */
    private function myBets(array $history, string $street, int $meId): array
    {
        $prev = ['turn' => 'flop', 'river' => 'turn'][$street] ?? null;
        $out = ['this' => false, 'prev' => false];
        foreach ($history as $h) {
            if (($h['player_id'] ?? null) !== $meId || !in_array($h['action'] ?? '', ['raise', 'bet', 'allin'], true)) {
                continue;
            }
            if ($h['street'] === $street) {
                $out['this'] = true;
            } elseif ($prev && $h['street'] === $prev) {
                $out['prev'] = true;
            }
        }

        return $out;
    }

    /** Orden de actuación postflop (de primero a último). */
    private const POSTFLOP_ORDER = ['SB' => 0, 'BB' => 1, 'UTG' => 2, 'HJ' => 3, 'CO' => 4, 'BTN' => 5];

    /** En posición = ningún rival que siga en la mano actúa después que él. Cara a cara, la SB es el botón. */
    private function inPosition(string $myPosition, array $others, array $allPlayers): bool
    {
        // Cara a cara de verdad (mesa de 2: SB es el botón y actúa último postflop); con otras etiquetas rige el orden normal
        $labels = array_merge([$myPosition], array_map(fn($o) => $o['position'] ?? '', $others));
        $headsUp = count($allPlayers) === 2 && in_array('SB', $labels, true) && in_array('BB', $labels, true);
        $rank = fn(string $pos) => $headsUp ? ($pos === 'SB' ? 5 : 1) : (self::POSTFLOP_ORDER[$pos] ?? 3);
        $mine = $rank($myPosition);
        foreach ($others as $o) {
            if ($rank($o['position'] ?? '') > $mine) {
                return false;
            }
        }

        return true;
    }

    // ---------------------------------------------------------- historial

    /** Subidas, limpers y último agresor a partir de las acciones de la mano. */
    private function historyStats(array $history, string $street, int $meId, int $bb): array
    {
        $pre = ['max' => $bb, 'raises' => 0, 'limpers' => 0, 'last' => null, 'mine' => false, 'mine_limp' => false, 'callers' => 0];
        $cur = ['max' => 0, 'raises' => 0, 'last' => null];

        foreach ($history as $h) {
            $isRaise = false;
            if ($h['street'] === 'preflop') {
                $amount = (int) $h['amount'];
                if (in_array($h['action'], ['bet', 'raise'], true) || ($h['action'] === 'allin' && $amount > $pre['max'])) {
                    $pre['raises']++;
                    $pre['max'] = $amount;
                    $pre['last'] = $h['player_id'];
                    $isRaise = true;
                    if ($h['player_id'] === $meId) {
                        $pre['mine'] = true;
                    }
                } elseif ($h['action'] === 'call' && $pre['raises'] === 0) {
                    $pre['limpers']++;
                    if ($h['player_id'] === $meId) {
                        $pre['mine_limp'] = true;
                    }
                } elseif ($h['action'] === 'call') {
                    $pre['callers']++; // paga una subida (cold call): relevante para el squeeze
                }
            }
            if ($h['street'] === $street) {
                $amount = (int) $h['amount'];
                if (in_array($h['action'], ['bet', 'raise'], true) || ($h['action'] === 'allin' && $amount > $cur['max'])) {
                    $cur['raises']++;
                    $cur['max'] = $amount;
                    $cur['last'] = $h['player_id'];
                }
            }
        }

        return [
            'preflop_raises' => $pre['raises'],
            'limpers' => $pre['limpers'],
            'preflop_aggressor' => $pre['last'],
            'i_raised_preflop' => $pre['mine'],
            'i_limped_preflop' => $pre['mine_limp'],
            'callers_after_raise' => $pre['callers'],
            'raises_this_street' => $cur['raises'],
            'last_aggressor' => $cur['last'] === $meId ? null : $cur['last'],
        ];
    }

    private function aggressorStats(array $ctx, ?int $aggId, int $meId): array
    {
        $empty = ['vpip' => null, 'pfr' => null, 'three_bet' => null, 'af' => null, 'hands' => 0];
        if ($aggId === null) {
            return $empty;
        }
        foreach ($ctx['players'] as $p) {
            if ($p['id'] === $aggId) {
                $s = $p['stats'] ?? [];

                return [
                    'vpip' => $s['vpip'] ?? null, 'pfr' => $s['pfr'] ?? null,
                    'three_bet' => $s['three_bet'] ?? null, 'af' => $s['af'] ?? null, 'hands' => (int) ($s['hands'] ?? 0),
                ];
            }
        }

        return $empty;
    }

    private function opponentRangeModel(
        array $ctx,
        array $hole,
        array $board,
        int $myScore,
        bool $straights,
        bool $flushes,
        string $street,
        array $history
    ): array {
        $me = $ctx['me'];
        $liveOpponents = array_values(array_filter(
            $ctx['players'],
            fn($player) => $player['id'] !== $me['id'] && !$player['folded']
        ));
        if (!$liveOpponents) {
            return [
                'equity' => 100.0,
                'range_pct' => 0.0,
                'value_combo_pct' => 0.0,
                'value_combos' => 0,
                'worse_combos' => 0,
                'call_frequency' => 50.0,
                'fold_frequency' => 35.0,
                'raise_frequency' => 10.0,
                'bet_frequency' => 50.0,
                'aggression' => 2.0,
                'stats_confidence' => 0.0,
            ];
        }

        $historyRows = $ctx['history'] ?? [];
        $preflopAggressor = $history['preflop_aggressor'] ?? null;
        $rangePercentiles = [];
        $opponentRanges = [];
        $valueCombos = 0;
        $worseCombos = 0;
        $totalCombos = 0;
        $call = $fold = $raise = $bet = $aggression = $confidence = 0.0;

        foreach ($liveOpponents as $opponent) {
            $id = $opponent['id'];
            $observed = (array) ($opponent['stats'] ?? []);
            $profile = (array) ($opponent['preflop_stats'] ?? []);
            $hands = (int) ($observed['hands'] ?? 0);
            $useObserved = $hands >= 5;
            $vpip = $useObserved ? ($observed['vpip'] ?? null) : ($profile['vpip'] ?? $observed['vpip'] ?? null);
            $pfr = $useObserved ? ($observed['pfr'] ?? null) : ($profile['pfr'] ?? $observed['pfr'] ?? null);
            $isAggressor = $id === $preflopAggressor;
            $position = $opponent['position'] ?? '';
            $positionFactor = [
                'UTG' => 0.72, 'HJ' => 0.85, 'CO' => 1.05,
                'BTN' => 1.22, 'SB' => 1.08, 'BB' => 1.18,
            ][$position] ?? 1.0;
            $preflopActions = array_values(array_filter(
                $historyRows,
                fn($action) => ($action['player_id'] ?? null) === $id && ($action['street'] ?? '') === 'preflop'
            ));
            $lastPreflopAction = $preflopActions ? end($preflopActions) : null;
            $preflopRaiseCount = (int) ($history['preflop_raises'] ?? 0);
            $raisesBeforeOpponent = 0;
            foreach ($historyRows as $action) {
                if (($action['street'] ?? '') !== 'preflop') {
                    continue;
                }
                if (($action['player_id'] ?? null) === $id && in_array($action['action'] ?? '', ['raise', 'allin'], true)) {
                    break;
                }
                if (in_array($action['action'] ?? '', ['raise', 'allin'], true)) {
                    $raisesBeforeOpponent++;
                }
            }
            $limpRaised = $isAggressor && $raisesBeforeOpponent === 0 && count(array_filter(
                $preflopActions,
                fn($action) => ($action['action'] ?? '') === 'call'
            )) > 0;
            if ($isAggressor && $limpRaised) {
                $baseRange = $profile['limp_push'] ?? $profile['limp_raise'] ?? ($pfr ?? 15.0);
            } elseif ($isAggressor && $raisesBeforeOpponent >= 2) {
                $baseRange = $profile['four_bet'] ?? ($pfr !== null ? $pfr * 0.25 : 5.0);
            } elseif ($isAggressor && $raisesBeforeOpponent === 1) {
                $baseRange = $profile['three_bet'] ?? $observed['three_bet'] ?? 8.0;
            } elseif ($isAggressor) {
                $baseRange = $pfr ?? 15.0;
            } elseif (($lastPreflopAction['action'] ?? null) === 'call' && $preflopRaiseCount > 0) {
                $baseRange = $profile['cold_call'] ?? max(5.0, (float) ($vpip ?? 25.0) - (float) ($pfr ?? 15.0));
            } elseif (($lastPreflopAction['action'] ?? null) === 'call') {
                $baseRange = $profile['limp'] ?? max(5.0, (float) ($vpip ?? 25.0) - (float) ($pfr ?? 15.0)) * 0.8;
            } else {
                $baseRange = $vpip ?? 25.0;
            }
            $rangePct = $this->clamp((float) $baseRange * $positionFactor, 4.0, 100.0);
            $ipKey = $this->isAfterPosition($position, $me['position'], $ctx['players']) ? 'ip' : 'oop';

            $initiative = $preflopAggressor;
            foreach ($historyRows as $action) {
                if (($action['street'] ?? '') === 'preflop') {
                    if (in_array($action['action'] ?? '', ['bet', 'raise', 'allin'], true)) {
                        $initiative = $action['player_id'] ?? null;
                    }
                    continue;
                }
                if (($action['player_id'] ?? null) !== $id) {
                    if (in_array($action['action'] ?? '', ['bet', 'raise', 'allin'], true)) {
                        $initiative = $action['player_id'] ?? null;
                    }
                    continue;
                }

                $streetStats = (array) (($opponent['postflop_stats'] ?? [])[$action['street'] ?? ''] ?? []);
                $role = $initiative === $id ? 'agg' : 'def';
                $specific = (array) ($streetStats[$ipKey][$role] ?? []);
                $frequency = (float) ($specific['bet'] ?? $streetStats['bet'] ?? $streetStats['cbet'] ?? $streetStats['donk'] ?? 50.0);
                $af = (float) ($streetStats['af'] ?? $observed['af'] ?? 2.0);
                $actionType = $action['action'] ?? '';

                if (in_array($actionType, ['bet', 'raise', 'allin'], true)) {
                    $potBefore = max(1, (int) ($action['pot_before'] ?? 0));
                    $size = (int) ($action['amount'] ?? 0) / $potBefore;
                    $aggressionFactor = $this->clamp($af / 2.0, 0.25, 2.0);
                    $lineFactor = 0.55 + 0.15 * $aggressionFactor + 0.003 * $frequency - 0.08 * max(0.0, $size - 0.5);
                    $rangePct *= $this->clamp($lineFactor, 0.4, 1.25);
                    $initiative = $id;
                } elseif ($actionType === 'call') {
                    $rangePct *= 1.08 + min(0.1, $af * 0.02);
                } elseif ($actionType === 'check') {
                    $rangePct *= 1.16 + ($af < 1.0 ? 0.05 : 0.0);
                }
            }

            $rangePct = $this->clamp($rangePct, 4.0, 100.0);
            $combos = $this->ranking->rangeCombos($rangePct, [...$hole, ...$board]);
            while (!$combos && $rangePct < 100.0) {
                $rangePct = min(100.0, $rangePct + 5.0);
                $combos = $this->ranking->rangeCombos($rangePct, [...$hole, ...$board]);
            }
            if (!$combos) {
                continue;
            }

            $opponentRanges[] = $combos;
            $rangePercentiles[] = $rangePct;
            foreach ($combos as [$first, $second]) {
                $score = F::score([$first, $second, ...$board], $straights, $flushes);
                if ($score > $myScore) {
                    $valueCombos++;
                } elseif ($score < $myScore) {
                    $worseCombos++;
                }
            }
            $totalCombos += count($combos);

            $response = $this->opponentResponseStats($opponent, $street, $ipKey, $observed);
            $call += $response['call'];
            $fold += $response['fold'];
            $raise += $response['raise'];
            $bet += $response['bet'];
            $aggression += $response['aggression'];
            $confidence += $response['confidence'];
        }

        if (!$opponentRanges) {
            throw new \LogicException('No se pudo construir un rango válido para los rivales activos.');
        }

        $samples = count($board) === 5
            ? max(1000, (int) ($ctx['equity_samples'] ?? 250))
            : max(250, (int) ($ctx['equity_samples'] ?? 250));
        $equity = $this->equity->equityVsRanges($me['hole_cards'], $opponentRanges, $ctx['board'] ?? [], $samples) * 100;
        $opponentCount = count($opponentRanges);

        return [
            'equity' => $equity,
            'range_pct' => array_sum($rangePercentiles) / $opponentCount,
            'value_combo_pct' => $totalCombos ? $valueCombos / $totalCombos * 100 : 50.0,
            'value_combos' => $valueCombos,
            'worse_combos' => $worseCombos,
            'call_frequency' => $call / $opponentCount,
            'fold_frequency' => $fold / $opponentCount,
            'raise_frequency' => $raise / $opponentCount,
            'bet_frequency' => $bet / $opponentCount,
            'aggression' => $aggression / $opponentCount,
            'stats_confidence' => $confidence / $opponentCount,
            'bluff_frequency' => $this->opponentBluffFrequency($liveOpponents, $history['last_aggressor'] ?? null),
        ];
    }

    private function opponentBluffFrequency(array $opponents, ?int $aggressorId): float
    {
        foreach ($opponents as $opponent) {
            if (($opponent['id'] ?? null) !== $aggressorId) {
                continue;
            }
            $river = (array) (($opponent['postflop_stats'] ?? [])['river'] ?? []);
            $bluff = $river['bluff'] ?? null;
            $samples = (int) ($river['sample_size'] ?? 0);
            if ($bluff === null || $samples <= 0) {
                return 0.0;
            }
            $confidence = min(1.0, $samples / 100.0);

            return $this->clamp((float) $bluff, 0.0, 100.0) * $confidence;
        }

        return 0.0;
    }

    private function opponentResponseStats(array $opponent, string $street, string $ipKey, array $observed): array
    {
        $streetStats = (array) (($opponent['postflop_stats'] ?? [])[$street] ?? []);
        $specific = (array) ($streetStats[$ipKey]['def'] ?? []);
        $fold = (float) ($specific['fold'] ?? $streetStats['f2bet'] ?? 35.0);
        $raise = (float) ($specific['raise'] ?? $streetStats['raise_vs_bet'] ?? 10.0);
        $call = (float) ($specific['call'] ?? max(0.0, 100.0 - $fold - $raise));
        $bet = (float) (($streetStats[$ipKey]['agg']['bet'] ?? null)
            ?? $streetStats['bet']
            ?? $streetStats['cbet']
            ?? $streetStats['donk']
            ?? 50.0);
        $aggression = (float) ($streetStats['af'] ?? $observed['af'] ?? 2.0);
        $samples = (int) ($specific['n'] ?? $streetStats['sample_size'] ?? 0);
        $confidence = $samples > 0
            ? min(1.0, $samples / 100.0)
            : ($streetStats ? 0.25 : 0.0);

        return [
            'call' => 50.0 + ($call - 50.0) * $confidence,
            'fold' => 35.0 + ($fold - 35.0) * $confidence,
            'raise' => 10.0 + ($raise - 10.0) * $confidence,
            'bet' => 50.0 + ($bet - 50.0) * $confidence,
            'aggression' => 2.0 + ($aggression - 2.0) * $confidence,
            'confidence' => $confidence,
        ];
    }

    private function isAfterPosition(string $position, string $heroPosition, array $players): bool
    {
        $labels = array_map(fn($player) => $player['position'] ?? '', array_filter($players, fn($player) => !$player['folded']));
        $headsUp = count($labels) === 2 && in_array('SB', $labels, true) && in_array('BB', $labels, true);
        $order = $headsUp
            ? ['BB' => 1, 'SB' => 5]
            : self::POSTFLOP_ORDER;

        return ($order[$position] ?? 3) > ($order[$heroPosition] ?? 3);
    }

    // ---------------------------------------------------------- línea del rival

    /** Calles anteriores en las que el agresor apostó/subió (barriles) o hizo check. */
    private function barrels(array $history, ?int $aggId, string $street): array
    {
        $out = ['barrels' => 0, 'checks' => 0];
        if ($aggId === null) {
            return $out;
        }
        $bet = $checked = [];
        foreach ($history as $h) {
            if ($h['player_id'] !== $aggId || $h['street'] === $street || $h['street'] === 'preflop') {
                continue;
            }
            if (in_array($h['action'], ['bet', 'raise', 'allin'], true)) {
                $bet[$h['street']] = true;
            } elseif ($h['action'] === 'check') {
                $checked[$h['street']] = true;
            }
        }
        $out['barrels'] = count($bet);
        $out['checks'] = count(array_diff_key($checked, $bet));

        return $out;
    }

    /**
     * Probabilidad (0-100) de que la apuesta del rival sea un farol, según la secuencia de acciones y el board.
     * Una buena lectura: con tres barriles seguidos en un board seco casi nunca es farol; un check-check y una
     * apuesta en el river en un board con proyectos que no llegaron sí puede serlo.
     */
    private function bluffLikelihood(array $f, array $line, float $bluffCapacity): float
    {
        if ($f['street'] === 'preflop' || !$f['facing_bet'] || $bluffCapacity <= 0) {
            return 0.0;
        }
        $p = 22.0;
        $p -= 9 * $line['barrels'];                                   // línea coherente de valor
        if ($line['checks'] >= 1 && $line['barrels'] === 0 && $f['street_index'] >= 2) {
            $p += 12;                                                  // apuesta tardía tras checks: sondeo o farol
        }
        $wet = $f['board_wetness'];
        if ($f['street_index'] === 3) {
            $p += $wet >= 25 ? 18 : ($wet <= 10 ? -8 : 0);            // proyectos que pudieron fallar / board seco sin faroles
        } elseif ($f['street_index'] === 2 && $wet >= 25) {
            $p += 8;
        }
        $bet = $f['bet_vs_pot'];
        $p += $bet >= 120 ? 8 : ($bet <= 35 ? -4 : 0);                // sobreapuesta polarizada / apuesta pequeña
        if ($f['agg_known']) {
            $p += $f['agg_af'] >= 3 ? 10 : ($f['agg_af'] !== null && $f['agg_af'] <= 1.2 ? -8 : 0);
            $p += $f['agg_vpip'] !== null && $f['agg_vpip'] <= 16 ? -8 : ($f['agg_vpip'] !== null && $f['agg_vpip'] >= 35 ? 6 : 0);
        }

        return round(min($this->clamp($p, 3, 90), $bluffCapacity), 1);
    }

    // ---------------------------------------------------------- mesa

    private function boardFeatures(array $board): array
    {
        $n = count($board);
        $out = [
            'board_count' => $n, 'board_paired' => false, 'board_flush_possible' => false,
            'board_straight_possible' => false, 'board_high' => -1, 'board_has_ace' => false, 'board_wetness' => 0,
            'board_four_flush' => false,
        ];
        if ($n === 0) {
            return $out;
        }

        $ranks = array_map(fn($c) => $c % 13, $board);
        $suits = array_count_values(array_map(fn($c) => intdiv($c, 13), $board));
        $counts = array_count_values($ranks);
        $suitedMax = max($suits);
        $window = 0;
        for ($low = 0; $low <= 8; $low++) {
            $in = 0;
            foreach (array_unique($ranks) as $r) {
                if ($r >= $low && $r <= $low + 4) {
                    $in++;
                }
            }
            if (in_array(12, $ranks, true) && $low === 0) {
                $in = max($in, count(array_filter(array_unique($ranks), fn($r) => $r <= 3)) + 1); // rueda
            }
            $window = max($window, $in);
        }

        $paired = max($counts) >= 2;
        $wet = ($suitedMax >= 4 ? 40 : ($suitedMax === 3 ? 25 : 0)) + ($window >= 4 ? 35 : ($window === 3 ? 20 : 0)) - ($paired ? 10 : 0);

        return [
            'board_count' => $n,
            'board_paired' => $paired,
            'board_flush_possible' => $suitedMax >= 3,
            'board_four_flush' => $suitedMax >= 4, // un color a UNA carta
            'board_straight_possible' => $window >= 3,
            'board_high' => max($ranks),
            'board_has_ace' => in_array(12, $ranks, true),
            'board_wetness' => max(0, min(100, $wet)),
        ];
    }

    // ---------------------------------------------------------- percepción de patrones

    /**
     * Decide si el jugador "ve" la escalera / el color que tiene.
     * Probabilidad = base por patrón − penalización × complejidad (rueda, cartas intercaladas, As en la mesa).
     *
     * @return array{0:bool,1:bool} [ve escaleras, ve colores]
     */
    private function perceivePatterns(int $score, array $hole, array $board, array $pp, Randomizer $rng, array &$notes): array
    {
        $cat = $score >> 20;
        $seeStraights = true;
        $seeFlushes = true;
        $pen = (float) ($pp['complexity_penalty'] ?? 0.15);

        $try = function (string $pattern, int $complexity) use ($pp, $pen, $rng, &$notes): bool {
            $base = (float) ($pp[$pattern] ?? 1.0);
            $p = $this->clamp($base - $pen * $complexity, 0, 1);
            $seen = $this->roll($rng, $p);
            $notes['rolls'][] = ['pattern' => $pattern, 'complexity' => $complexity, 'p' => round($p, 2), 'seen' => $seen];

            return $seen;
        };

        if ($cat === 8) {
            $c = $this->straightComplexity($hole, $board, true);
            if (!$try('straight_flush', $c)) {
                // No lo ve como escalera de color: ¿ve al menos el color o la escalera por separado?
                $seeFlushes = $try('flush', 0);
                $seeStraights = $try('straight', $c);
            }
        } elseif ($cat === 4) {
            $seeStraights = $try('straight', $this->straightComplexity($hole, $board, false));
        } elseif ($cat === 5) {
            $seeFlushes = $try('flush', 0);
        }

        return [$seeStraights, $seeFlushes];
    }

    /**
     * Complejidad de ver una escalera: +1 rueda (el As cuenta como 1), +1 cartas propias intercaladas,
     * +1 As en la mesa que no forma parte de la escalera (distrae).
     */
    private function straightComplexity(array $hole, array $board, bool $suitedOnly): int
    {
        $cards = [...$hole, ...$board];
        if ($suitedOnly) {
            $bySuit = [];
            foreach ($cards as $c) {
                $bySuit[intdiv($c, 13)][] = $c;
            }
            foreach ($bySuit as $list) {
                if (count($list) >= 5) {
                    $cards = $list;
                    break;
                }
            }
        }
        $mask = 0;
        foreach ($cards as $c) {
            $mask |= 1 << ($c % 13);
        }

        $high = -1;
        for ($h = 12; $h >= 4; $h--) {
            if ((($mask >> ($h - 4)) & 31) === 31) {
                $high = $h;
                break;
            }
        }
        $wheel = false;
        if ($high < 0 && ($mask & (1 << 12)) && (($mask & 15) === 15)) {
            $high = 3;
            $wheel = true;
        }
        if ($high < 0) {
            return 0;
        }

        $seq = $wheel ? [12, 0, 1, 2, 3] : range($high, $high - 4);
        $complexity = $wheel ? 1 : 0;

        $holeInSeq = array_values(array_filter(array_map(fn($c) => $c % 13, $hole), fn($r) => in_array($r, $seq, true)));
        if (count($holeInSeq) === 2 && abs($holeInSeq[0] - $holeInSeq[1]) > 1 && !($wheel && in_array(12, $holeInSeq, true))) {
            $complexity++; // las dos cartas propias no son vecinas: están "intercaladas" con las de la mesa
        }
        $boardRanks = array_map(fn($c) => $c % 13, $board);
        if (in_array(12, $boardRanks, true) && !in_array(12, $seq, true)) {
            $complexity++; // As en la mesa que no forma parte de la escalera
        }

        return $complexity;
    }

    // ---------------------------------------------------------- manos y proyectos

    private function madeName(int $score): string
    {
        $cat = $score >> 20;
        if ($cat === 8 && (($score >> 16) & 15) === 12) {
            return 'royal_flush';
        }

        return self::MADE[$cat] ?? 'high_card';
    }

    /** % de manos rivales posibles que le ganan a la nuestra (empates cuentan la mitad). */
    private function rankPercentile(array $hole, array $board, bool $straights, bool $flushes, bool $villainStraights = true, bool $villainFlushes = true): float
    {
        $mine = F::score([...$hole, ...$board], $straights, $flushes);
        $used = array_fill(0, 52, false);
        foreach ([...$hole, ...$board] as $c) {
            $used[$c] = true;
        }
        $deck = [];
        for ($c = 0; $c < 52; $c++) {
            if (!$used[$c]) {
                $deck[] = $c;
            }
        }
        $m = count($deck);
        $better = 0.0;
        $total = 0;
        for ($i = 0; $i < $m - 1; $i++) {
            for ($j = $i + 1; $j < $m; $j++) {
                $s = F::score([$deck[$i], $deck[$j], ...$board], $villainStraights, $villainFlushes);
                $better += $s > $mine ? 1 : ($s === $mine ? 0.5 : 0);
                $total++;
            }
        }

        return $total ? $better / $total * 100 : 0.0;
    }

    /**
     * % de manos de SU RANGO que le ganan a la nuestra (0 = lo mejor de su rango, 100 = lo peor).
     * El rango es el X % superior de manos iniciales (VPIP, o PFR si fue el agresor preflop).
     */
    private function rangePercentile(array $hole, array $board, bool $straights, bool $flushes, bool $vs, bool $vf, float $rangePct): float
    {
        $mine = F::score([...$hole, ...$board], $straights, $flushes);
        $combos = $this->ranking->rangeCombos($rangePct, [...$hole, ...$board]);
        if (!$combos) {
            return 50.0;
        }
        $better = 0.0;
        foreach ($combos as [$a, $b]) {
            $s = F::score([$a, $b, ...$board], $vs, $vf);
            $better += $s > $mine ? 1 : ($s === $mine ? 0.5 : 0);
        }

        return $better / count($combos) * 100;
    }

    private function holeCardsUsed(array $hole, array $board, int $score, bool $straights, bool $flushes): int
    {
        $used = 0;
        for ($i = 0; $i < 2; $i++) {
            $without = [$hole[1 - $i], ...$board];
            if (F::score($without, $straights, $flushes) < $score) {
                $used++;
            }
        }

        return $used;
    }

    /** Proyectos de escalera / color: outs y tipo (solo si aún no hay escalera o mejor). */
    private function draws(array $hole, array $board, int $realCat): array
    {
        if ($realCat >= 4) {
            return ['type' => 'none', 'outs' => 0];
        }
        $cards = [...$hole, ...$board];
        $used = array_fill(0, 52, false);
        foreach ($cards as $c) {
            $used[$c] = true;
        }

        $flushOuts = $straightOuts = $all = 0;
        for ($c = 0; $c < 52; $c++) {
            if ($used[$c]) {
                continue;
            }
            $cat = F::score([...$cards, $c]) >> 20;
            if ($cat === 5 || $cat === 8) {
                $flushOuts++;
                $all++;
            } elseif ($cat === 4) {
                $straightOuts++;
                $all++;
            }
        }

        $type = match (true) {
            $flushOuts > 0 && $straightOuts > 0 => 'combo',
            $flushOuts > 0 => 'flush',
            $straightOuts >= 8 => 'oesd',
            $straightOuts > 0 => 'gutshot',
            default => 'none',
        };

        return ['type' => $type, 'outs' => $all, 'flush_outs' => $flushOuts, 'straight_outs' => $straightOuts];
    }

    /**
     * Proyectos de "puerta de atrás" en el flop: tres cartas del mismo palo (al menos una suya) o tres rangos dentro de una
     * ventana de cinco (al menos uno suyo) sin que ya haya proyecto real. Necesitan las DOS cartas siguientes.
     */
    private function backdoors(array $hole, array $board): array
    {
        if (count($board) !== 3) {
            return ['flush' => false, 'straight' => false];
        }
        $all = [...$hole, ...$board];
        $flush = false;
        foreach (array_count_values(array_map(fn($c) => intdiv($c, 13), $all)) as $suit => $n) {
            if ($n === 3 && count(array_filter($hole, fn($c) => intdiv($c, 13) === $suit)) >= 1) {
                $flush = true;
            }
        }
        $ranks = array_unique(array_map(fn($c) => $c % 13, $all));
        $holeRanks = array_unique(array_map(fn($c) => $c % 13, $hole));
        $max = 0;
        $usesHole = false;
        for ($s = -1; $s <= 8; $s++) {
            $win = array_map(fn($r) => $r < 0 ? 12 : $r, range($s, $s + 4)); // el As también hace de carta baja
            $in = array_intersect($ranks, $win);
            $max = max($max, count($in));
            if (count($in) === 3 && array_intersect($holeRanks, $in)) {
                $usesHole = true;
            }
        }

        return ['flush' => $flush, 'straight' => $max === 3 && $usesHole];
    }

    // ---------------------------------------------------------- utilidades

    private function roll(Randomizer $rng, float $p): bool
    {
        return $rng->getInt(0, 9999) / 10000 < $p;
    }

    private function gauss(Randomizer $rng): float
    {
        $u1 = max(1, $rng->getInt(1, 99999)) / 100000;
        $u2 = $rng->getInt(0, 99999) / 100000;

        return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }

    private function clamp(float $v, float $lo, float $hi): float
    {
        return max($lo, min($hi, $v));
    }
}
