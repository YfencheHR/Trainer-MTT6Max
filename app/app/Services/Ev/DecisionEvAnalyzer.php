<?php

namespace App\Services\Ev;

use App\Models\HandAction;
use App\Models\HandPlayer;
use App\Models\TournamentHand;
use Illuminate\Support\Facades\DB;

/**
 * Análisis de EV de una mano terminada, a partir del historial estructurado.
 *
 * MODELO "vs_actual_hands" (medida a posteriori, no algo que el jugador pudiera saber en la mesa):
 *  - Equity de quien actúa = probabilidad de ganar contra las CARTAS REALES de los rivales que
 *    siguen en la mano en ese momento, con las cartas comunitarias visibles entonces.
 *  - Se supone que, desde ese punto, no hay más apuestas y los rivales que siguen llegan al showdown.
 *
 * 1) EV por decisión (retirarse o igualar ante una apuesta, incluido pagar un all-in):
 *      EV(fold) = 0
 *      EV(call) = equity × bote − (1 − equity) × lo que cuesta igualar
 *      ev_diff  = EV(lo que hizo) − EV(la alternativa)   → positivo: ganó EV · negativo: perdió EV
 *    Las demás decisiones (check, apostar, subir) necesitan un modelo de fold equity del rival:
 *    se guarda su equity pero no se emite veredicto (ev_verdict = 'na').
 *
 * 2) EV ajustado de all-in (mide la suerte): cuando el dinero queda en el centro con cartas por venir,
 *    se calcula lo que cada jugador esperaba ganar según su equity (por capas de bote) y se compara
 *    con lo que salió:  suerte = resultado real − EV esperado.
 */
class DecisionEvAnalyzer
{
    public const MODEL = 'vs_actual_hands';

    private const STREETS = ['preflop' => 'Preflop', 'flop' => 'Flop', 'turn' => 'Turn', 'river' => 'River'];

    public function __construct(private EquityCalculator $equity, private EvCalculator $ev)
    {
    }

    /**
     * Analiza la mano, guarda los resultados y devuelve las entradas de log para el héroe
     * (vacías si no hay jugador humano o no hay nada evaluable).
     *
     * @return array<int, array>
     */
    public function analyze(TournamentHand $hand): array
    {
        $players = HandPlayer::where('tournament_hand_id', $hand->id)->with('player')->get()->keyBy('tournament_player_id');
        $actions = HandAction::where('tournament_hand_id', $hand->id)->orderBy('sequence')->get();
        if ($players->count() < 2 || $actions->isEmpty()) {
            return [];
        }

        $bb = max(1, (int) ($hand->blind_level_config['big_blind'] ?? 1));
        $cards = $players->map(fn($p) => $p->hole_cards)->all();

        $lastDecision = null;
        foreach ($actions as $i => $a) {
            if (!in_array($a->type, ['deal', 'post_sb', 'post_bb'], true)) {
                $lastDecision = $i;
            }
        }

        $board = [];
        $folded = [];
        $invested = array_fill_keys($players->keys()->all(), 0);
        $cache = [];
        $heroDecisions = [];
        $boardAtEnd = [];
        $foldedAtEnd = [];

        foreach ($actions as $i => $a) {
            if ($a->type === 'deal') {
                $board = array_merge($board, $a->cards ?? []);
                continue;
            }
            $pid = $a->tournament_player_id;
            if (in_array($a->type, ['post_sb', 'post_bb'], true)) {
                $invested[$pid] += $a->amount;
                continue;
            }

            $alive = array_values(array_filter($players->keys()->all(), fn($id) => empty($folded[$id])));
            $update = $this->evaluate($a, $pid, $alive, $cards, $board, $bb, $cache);
            DB::table('hand_actions')->where('id', $a->id)->update($update);

            if (($update['ev_verdict'] ?? 'na') !== 'na' && !$players[$pid]->player->is_bot) {
                $heroDecisions[] = [$a, $update];
            }

            $invested[$pid] += $a->amount;
            if ($a->type === 'fold') {
                $folded[$pid] = true;
            }
            if ($i === $lastDecision) {
                $boardAtEnd = $board;
                $foldedAtEnd = $folded;
            }
        }

        // EV ajustado de all-in (solo si quedó dinero en el centro con cartas por venir)
        $allIn = $this->adjustedAllIn($hand, $players, $actions, $lastDecision, $boardAtEnd, $foldedAtEnd, $invested, $cards);

        $hand->ev_analyzed_at = now();

        return $this->logEntries($players, $bb, $heroDecisions, $allIn);
    }

    // ---------------------------------------------------------- por decisión

    /** Columnas de EV para una acción. */
    private function evaluate(HandAction $a, int $pid, array $alive, array $cards, array $board, int $bb, array &$cache): array
    {
        $out = ['ev_model' => self::MODEL, 'ev_verdict' => 'na'];

        $opponents = array_diff($alive, [$pid]);
        if (!$opponents) {
            return $out;
        }

        $key = implode(',', $alive) . '|' . implode(',', $board);
        if (!isset($cache[$key])) {
            $cache[$key] = $this->equity->equities(array_intersect_key($cards, array_flip($alive)), $board);
        }
        $eq = $cache[$key][$pid];
        $out['equity'] = round($eq, 4);

        $toCall = (int) $a->to_call;
        $stack = (int) $a->stack_before;
        $callLike = $toCall > 0 && (
            in_array($a->type, ['fold', 'call'], true)
            || ($a->type === 'allin' && $a->amount <= $toCall)
        );
        if (!$callLike) {
            return $out; // check / apuesta / subida: hace falta un modelo de fold equity
        }

        $facing = $this->ev->facingBet($eq, (int) $a->pot_before, min($toCall, $stack));
        $taken = $a->type === 'fold' ? 0.0 : $facing['ev_call'];
        $alt = $a->type === 'fold' ? $facing['ev_call'] : 0.0;
        $diff = $this->ev->difference($taken, $alt);

        return array_merge($out, [
            'required_equity' => round($facing['required_equity'], 4),
            'ev_taken' => (int) round($taken),
            'ev_alternative' => (int) round($alt),
            'ev_diff' => (int) round($diff),
            'ev_best' => $facing['best'],
            'ev_verdict' => abs($diff) < 0.05 * $bb ? 'neutral' : ($diff > 0 ? 'gain' : 'loss'),
        ]);
    }

    // ------------------------------------------------------ all-in ajustado

    /**
     * @return array<int, array{id:int,ev_net:int,ev_luck:int,equity:float,net:int}> por jugador que seguía en la mano
     */
    private function adjustedAllIn(TournamentHand $hand, $players, $actions, ?int $lastDecision, array $boardAtEnd, array $foldedAtEnd, array $invested, array $cards): array
    {
        if ($lastDecision === null || !$players->contains(fn($p) => $p->went_to_showdown)) {
            return [];
        }
        // ¿hubo cartas repartidas después de la última decisión? (run-out)
        $runout = $actions->slice($lastDecision + 1)->contains(fn($a) => $a->type === 'deal');
        if (!$runout) {
            return [];
        }

        $alive = array_values(array_filter($players->keys()->all(), fn($id) => empty($foldedAtEnd[$id])));
        if (count($alive) < 2) {
            return [];
        }

        $layers = $this->layers($invested, $alive);
        $hands = array_intersect_key($cards, array_flip($alive));
        $expected = $this->equity->expectedWinnings($hands, $boardAtEnd, $layers);
        $equities = $this->equity->equities($hands, $boardAtEnd);

        $out = [];
        foreach ($players as $id => $p) {
            $net = (int) $p->stack_end - (int) $p->stack_start;
            if (in_array($id, $alive, true)) {
                $evNet = (int) round($expected[$id] - $invested[$id]);
                $row = ['ev_net' => $evNet, 'ev_luck' => $net - $evNet, 'allin_equity' => round($equities[$id], 4)];
            } else {
                $row = ['ev_net' => $net, 'ev_luck' => 0, 'allin_equity' => null]; // retirado: sin ajuste
            }
            DB::table('hand_players')->where('id', $p->id)->update($row);
            $out[$id] = ['id' => $id, 'ev_net' => $row['ev_net'], 'ev_luck' => $row['ev_luck'], 'equity' => $equities[$id] ?? null, 'net' => $net, 'alive' => in_array($id, $alive, true)];
        }

        return $out;
    }

    /** Capas de bote (principal + laterales) a partir de lo aportado por cada jugador. */
    private function layers(array $invested, array $alive): array
    {
        $levels = array_values(array_unique(array_map(fn($id) => $invested[$id], $alive)));
        sort($levels);

        $layers = [];
        $prev = 0;
        foreach ($levels as $level) {
            if ($level <= $prev) {
                continue;
            }
            $amount = 0;
            foreach ($invested as $inv) {
                $amount += max(0, min($inv, $level) - $prev);
            }
            $layers[] = ['amount' => $amount, 'eligible' => array_values(array_filter($alive, fn($id) => $invested[$id] >= $level))];
            $prev = $level;
        }
        $excess = 0;
        foreach ($invested as $inv) {
            $excess += max(0, $inv - $prev);
        }
        if ($excess > 0 && $layers) {
            $layers[count($layers) - 1]['amount'] += $excess;
        }

        return $layers;
    }

    // ------------------------------------------------------- entradas de log

    private function logEntries($players, int $bb, array $heroDecisions, array $allIn): array
    {
        $hero = $players->first(fn($p) => !$p->player->is_bot);
        if (!$hero) {
            return [];
        }

        $time = now()->format('H:i:s');
        $entries = [];
        $total = 0;

        foreach ($heroDecisions as [$a, $u]) {
            $diff = $u['ev_diff'];
            $total += $diff;
            $eq = $this->pct($u['equity']);
            $req = $this->pct($u['required_equity']);
            $folded = $a->type === 'fold';
            $what = $folded ? 'Fold' : (($a->type === 'allin' ? 'All-in ' : 'Call ') . $this->bb($a->amount, $bb));

            $text = match (true) {
                $u['ev_verdict'] === 'neutral' => "Decisión neutra: equity {$eq} ≈ necesaria {$req}",
                $u['ev_verdict'] === 'gain' && $folded => "Te retiraste con equity {$eq} (necesitabas {$req}): evitaste perder " . $this->bb(abs($diff), $bb),
                $u['ev_verdict'] === 'gain' => "Igualaste con equity {$eq} (necesitabas {$req}): ganaste " . $this->bb(abs($diff), $bb) . ' de EV',
                $folded => "Te retiraste con equity {$eq} y solo necesitabas {$req}: igualar valía +" . $this->bb(abs($diff), $bb),
                default => "Igualaste con equity {$eq} y necesitabas {$req}: perdiste " . $this->bb(abs($diff), $bb) . ' de EV',
            };

            $entries[] = [
                'time' => $time, 'type' => 'ev', 'verdict' => $u['ev_verdict'],
                'text' => (self::STREETS[$a->street] ?? $a->street) . ' · ' . $what,
                'detail' => $text,
                'ev' => $this->signedBb($diff, $bb),
            ];
        }

        if ($entries) {
            array_unshift($entries, [
                'time' => $time, 'type' => 'ev_header', 'verdict' => 'na',
                'text' => '📊 Análisis de EV de tus decisiones',
                'detail' => 'Equity calculada contra las cartas reales de los rivales',
            ]);
            $entries[] = [
                'time' => $time, 'type' => 'ev_total', 'verdict' => $total > 0 ? 'gain' : ($total < 0 ? 'loss' : 'neutral'),
                'text' => 'Total EV de tus decisiones',
                'ev' => $this->signedBb($total, $bb),
            ];
        }

        $mine = $allIn[$hero->tournament_player_id] ?? null;
        if ($mine && $mine['alive']) {
            $luck = $mine['ev_luck'];
            $entries[] = [
                'time' => $time, 'type' => 'ev_allin', 'verdict' => $luck > 0 ? 'gain' : ($luck < 0 ? 'loss' : 'neutral'),
                'text' => '🎲 All-in: equity ' . $this->pct($mine['equity']) . ' · EV ' . $this->signedBb($mine['ev_net'], $bb) . ' · resultado ' . $this->signedBb($mine['net'], $bb),
                'detail' => $luck === 0 ? 'Sin suerte ni mala suerte' : ($luck > 0 ? 'Suerte: +' : 'Mala suerte: −') . $this->bb(abs($luck), $bb),
                'ev' => $this->signedBb($luck, $bb),
            ];
        }

        return $entries;
    }

    private function pct(?float $v): string
    {
        return round(($v ?? 0) * 100) . '%';
    }

    private function bb(float|int $chips, int $bb): string
    {
        return rtrim(rtrim(number_format($chips / max(1, $bb), 1, ',', ''), '0'), ',') . 'bb';
    }

    private function signedBb(float|int $chips, int $bb): string
    {
        return ($chips > 0 ? '+' : ($chips < 0 ? '−' : '')) . $this->bb(abs($chips), $bb);
    }
}
