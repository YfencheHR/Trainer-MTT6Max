<?php

namespace App\Services\Ev;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Cálculo de equity (probabilidad de ganar, repartiendo los empates) por enumeración
 * exacta cuando faltan pocas cartas y por simulación (Monte Carlo) cuando son muchas.
 *
 * Usa su propio generador aleatorio con semilla derivada de la situación, de modo que:
 *   - el resultado es reproducible (misma situación → mismo número),
 *   - NO toca el generador global (no altera el barajado de las manos).
 *
 * Es un servicio para usar también en juego: un bot puede pedir su equity contra
 * manos aleatorias (equityVsRandom) o contra rangos (equityVsRanges) sin ver cartas ajenas.
 */
class EquityCalculator
{
    /** Máximo de combinaciones de board para enumerar exactamente. */
    private const EXACT_LIMIT = 1100;

    public function __construct(private int $defaultSamples = 1500)
    {
    }

    /**
     * Equity de varias manos conocidas a la vez (suman 1).
     *
     * @param array<int|string, string[]> $hands  clave => 2 cartas  ("A♠","K♦")
     * @param string[]                    $board  0-5 cartas visibles
     * @return array<int|string, float>   clave => equity (0-1)
     */
    public function equities(array $hands, array $board = [], ?int $samples = null): array
    {
        $keys = array_keys($hands);
        $hole = [];
        foreach ($hands as $k => $h) {
            $hole[$k] = FastHandEvaluator::cards($h);
        }
        $boardInts = FastHandEvaluator::cards($board);

        $result = array_fill_keys($keys, 0.0);
        $n = 0;
        foreach ($this->runouts($hole, $boardInts, $samples ?? $this->defaultSamples) as $full) {
            $best = -1;
            $winners = [];
            foreach ($hole as $k => $cards) {
                $s = FastHandEvaluator::score([...$cards, ...$full]);
                if ($s > $best) {
                    $best = $s;
                    $winners = [$k];
                } elseif ($s === $best) {
                    $winners[] = $k;
                }
            }
            $share = 1 / count($winners);
            foreach ($winners as $k) {
                $result[$k] += $share;
            }
            $n++;
        }

        return array_map(fn($v) => $n ? $v / $n : 0.0, $result);
    }

    /**
     * Ganancia esperada de cada jugador sobre un conjunto de botes (principal + laterales).
     * Sirve para el EV "ajustado" de un all-in: lo que se espera ganar según las cartas, no lo que salió.
     *
     * @param array<int|string, string[]> $hands   id => 2 cartas (solo jugadores que siguen en la mano)
     * @param string[]                    $board   cartas visibles cuando ya no hay más apuestas
     * @param array<int, array{amount:int|float, eligible:array}> $layers  botes: importe y jugadores con derecho
     * @return array<int|string, float>  id => fichas esperadas (suma de todos los botes)
     */
    public function expectedWinnings(array $hands, array $board, array $layers, ?int $samples = null): array
    {
        $hole = [];
        foreach ($hands as $k => $h) {
            $hole[$k] = FastHandEvaluator::cards($h);
        }
        $boardInts = FastHandEvaluator::cards($board);

        $won = array_fill_keys(array_keys($hands), 0.0);
        $n = 0;
        foreach ($this->runouts($hole, $boardInts, $samples ?? $this->defaultSamples) as $full) {
            $scores = [];
            foreach ($hole as $k => $cards) {
                $scores[$k] = FastHandEvaluator::score([...$cards, ...$full]);
            }
            foreach ($layers as $layer) {
                $best = -1;
                $winners = [];
                foreach ($layer['eligible'] as $k) {
                    if (!isset($scores[$k])) {
                        continue;
                    }
                    if ($scores[$k] > $best) {
                        $best = $scores[$k];
                        $winners = [$k];
                    } elseif ($scores[$k] === $best) {
                        $winners[] = $k;
                    }
                }
                if ($winners) {
                    $share = $layer['amount'] / count($winners);
                    foreach ($winners as $k) {
                        $won[$k] += $share;
                    }
                }
            }
            $n++;
        }

        return array_map(fn($v) => $n ? $v / $n : 0.0, $won);
    }

    /**
     * Equity de una mano contra manos conocidas de rivales.
     *
     * @return array{equity:float, win:float, tie:float, lose:float}
     */
    public function equityVsKnown(array $hero, array $opponents, array $board = [], ?int $samples = null): array
    {
        $hands = ['hero' => $hero];
        foreach (array_values($opponents) as $i => $o) {
            $hands['o' . $i] = $o;
        }

        $heroInts = FastHandEvaluator::cards($hero);
        $opp = [];
        foreach (array_values($opponents) as $i => $o) {
            $opp['o' . $i] = FastHandEvaluator::cards($o);
        }
        $boardInts = FastHandEvaluator::cards($board);

        $win = $tie = $n = 0;
        $hole = ['hero' => $heroInts] + $opp;
        foreach ($this->runouts($hole, $boardInts, $samples ?? $this->defaultSamples) as $full) {
            $hs = FastHandEvaluator::score([...$heroInts, ...$full]);
            $bestOpp = -1;
            foreach ($opp as $cards) {
                $bestOpp = max($bestOpp, FastHandEvaluator::score([...$cards, ...$full]));
            }
            if ($hs > $bestOpp) {
                $win++;
            } elseif ($hs === $bestOpp) {
                $tie++;
            }
            $n++;
        }

        $win = $n ? $win / $n : 0;
        $tie = $n ? $tie / $n : 0;

        return ['equity' => $win + $tie / 2, 'win' => $win, 'tie' => $tie, 'lose' => 1 - $win - $tie];
    }

    /**
     * Equity contra rivales cuyas cartas NO se conocen (manos aleatorias). Para uso en juego.
     */
    public function equityVsRandom(array $hero, int $opponents, array $board = [], ?int $samples = null): float
    {
        return $this->equityVsRanges($hero, array_fill(0, $opponents, null), $board, $samples);
    }

    /**
     * Equity contra rangos. Cada rival es una lista de combos posibles [[c1,c2],…] o null (cualquier mano).
     * Un bot puede construir el rango de un rival a partir de sus estadísticas (VPIP, PFR…).
     *
     * @param array<int, array<int, string[]>|null> $ranges
     */
    public function equityVsRanges(array $hero, array $ranges, array $board = [], ?int $samples = null): float
    {
        $samples ??= $this->defaultSamples;
        $heroInts = FastHandEvaluator::cards($hero);
        $boardInts = FastHandEvaluator::cards($board);
        $rangeInts = array_map(fn($r) => $r === null ? null : array_map(fn($c) => FastHandEvaluator::cards($c), $r), $ranges);

        $rng = new Randomizer(new Mt19937($this->seed([$heroInts, $boardInts, count($ranges)])));
        $need = 5 - count($boardInts);
        $total = 0.0;
        $done = 0;

        for ($i = 0; $i < $samples; $i++) {
            $used = array_fill(0, 52, false);
            foreach ([...$heroInts, ...$boardInts] as $c) {
                $used[$c] = true;
            }

            $oppHands = [];
            $ok = true;
            foreach ($rangeInts as $combos) {
                $hand = null;
                for ($try = 0; $try < 30 && $hand === null; $try++) {
                    if ($combos === null) {
                        $a = $rng->getInt(0, 51);
                        $b = $rng->getInt(0, 51);
                        $cand = [$a, $b];
                    } else {
                        $cand = $combos[$rng->getInt(0, count($combos) - 1)];
                    }
                    if ($cand[0] !== $cand[1] && !$used[$cand[0]] && !$used[$cand[1]]) {
                        $hand = $cand;
                    }
                }
                if ($hand === null) {
                    $ok = false;
                    break;
                }
                $used[$hand[0]] = $used[$hand[1]] = true;
                $oppHands[] = $hand;
            }
            if (!$ok) {
                continue;
            }

            $extra = [];
            while (count($extra) < $need) {
                $c = $rng->getInt(0, 51);
                if (!$used[$c]) {
                    $used[$c] = true;
                    $extra[] = $c;
                }
            }
            $full = [...$boardInts, ...$extra];

            $hs = FastHandEvaluator::score([...$heroInts, ...$full]);
            $best = -1;
            foreach ($oppHands as $o) {
                $best = max($best, FastHandEvaluator::score([...$o, ...$full]));
            }
            $total += $hs > $best ? 1 : ($hs === $best ? 0.5 : 0);
            $done++;
        }

        return $done ? $total / $done : 0.0;
    }

    /**
     * Genera los tableros completos posibles dadas las cartas conocidas.
     * Enumeración exacta si hay pocas combinaciones; si no, muestreo reproducible.
     *
     * @param array<int|string, int[]> $hole
     * @param int[]                    $board
     * @return \Generator<int[]>   las 5 cartas del board (visibles + por venir)
     */
    private function runouts(array $hole, array $board, int $samples): \Generator
    {
        $need = 5 - count($board);
        if ($need <= 0) {
            yield $board;
            return;
        }

        $used = array_fill(0, 52, false);
        foreach ($hole as $cards) {
            foreach ($cards as $c) {
                $used[$c] = true;
            }
        }
        foreach ($board as $c) {
            $used[$c] = true;
        }
        $deck = [];
        for ($c = 0; $c < 52; $c++) {
            if (!$used[$c]) {
                $deck[] = $c;
            }
        }
        $m = count($deck);

        if ($need === 1) {
            foreach ($deck as $a) {
                yield [...$board, $a];
            }
            return;
        }
        if ($need === 2 && $m * ($m - 1) / 2 <= self::EXACT_LIMIT) {
            for ($i = 0; $i < $m - 1; $i++) {
                for ($j = $i + 1; $j < $m; $j++) {
                    yield [...$board, $deck[$i], $deck[$j]];
                }
            }
            return;
        }

        $rng = new Randomizer(new Mt19937($this->seed([$hole, $board])));
        for ($s = 0; $s < $samples; $s++) {
            // Muestreo sin reemplazo de $need cartas (Fisher-Yates parcial)
            $pool = $deck;
            $pick = [];
            for ($k = 0; $k < $need; $k++) {
                $idx = $rng->getInt($k, $m - 1);
                [$pool[$k], $pool[$idx]] = [$pool[$idx], $pool[$k]];
                $pick[] = $pool[$k];
            }
            yield [...$board, ...$pick];
        }
    }

    private function seed(array $parts): int
    {
        return crc32(json_encode($parts)) & 0x7FFFFFFF;
    }
}
