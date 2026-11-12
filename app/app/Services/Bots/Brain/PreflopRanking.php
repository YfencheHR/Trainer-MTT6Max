<?php

namespace App\Services\Bots\Brain;

use App\Services\Ev\FastHandEvaluator;

/**
 * Ranking de manos iniciales: percentil (0-100, menor = mejor) de cada una de las 169 manos,
 * ponderado por número de combinaciones (pares 6, suited 4, offsuit 12 de 1326).
 *
 *   AA ≈ 0,2 · AKs ≈ 1,5 · JTs ≈ 11 · 87s ≈ 25 · 72o ≈ 99
 *
 * Así "preflop_rank <= 14" equivale a "juega el 14 % de las manos más fuertes", igual que un VPIP/PFR.
 *
 * El orden sale de la fórmula de Chen (puntos por carta alta, pareja, suited, huecos y conectividad), que
 * valora bien lo que el equity contra manos aleatorias infravalora: suited, conectadas y pares pequeños.
 * Es solo un ORDEN para que las reglas hablen en porcentajes; cuando un bot tiene rangos explícitos por
 * mano (bot_ranges, p. ej. importados de PokerTracker) esos rangos mandan y este orden deja de usarse.
 *
 * La tabla se calcula una sola vez y se guarda en storage/app/brain/preflop-ranking.json.
 */
class PreflopRanking
{
    private ?array $table = null;

    /** Puntos de Chen de la carta alta (índice 0 = 2 … 12 = As). */
    private const POINTS = [1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5, 6, 7, 8, 10];

    /** Percentil de la mano (2 cartas "A♠","K♦"). */
    public function percentile(array $hole): float
    {
        return $this->table()[$this->code($hole)] ?? 100.0;
    }

    /** Percentil de un código canónico como AA, AKs o AKo. */
    public function percentileForCode(string $code): float
    {
        return $this->table()[$code] ?? 100.0;
    }

    public function code(array $hole): string
    {
        [$a, $b] = array_map(fn($c) => FastHandEvaluator::card($c), $hole);
        $ra = $a % 13;
        $rb = $b % 13;
        $hi = max($ra, $rb);
        $lo = min($ra, $rb);
        $letters = FastHandEvaluator::RANKS;
        if ($hi === $lo) {
            return $letters[$hi] . $letters[$lo];
        }

        return $letters[$hi] . $letters[$lo] . (intdiv($a, 13) === intdiv($b, 13) ? 's' : 'o');
    }

    /**
     * Combinaciones concretas (cartas 0-51) de las manos que están en el X % superior del ranking,
     * sin las cartas conocidas ($dead).
     *
     * @param int[] $dead
     * @return array<int, array{0:int,1:int}>
     */
    public function rangeCombos(float $topPercent, array $dead = []): array
    {
        $letters = FastHandEvaluator::RANKS;
        $deadSet = array_flip($dead);
        $out = [];

        foreach ($this->table() as $code => $pct) {
            if ($pct > $topPercent) {
                continue;
            }
            $code = (string) $code; // las claves como "22" o "99" llegan como enteros
            $hi = strpos($letters, $code[0]);
            $lo = strpos($letters, $code[1]);
            $suited = ($code[2] ?? '') === 's';

            if ($hi === $lo) {
                for ($s1 = 0; $s1 < 4; $s1++) {
                    for ($s2 = $s1 + 1; $s2 < 4; $s2++) {
                        $out[] = [$s1 * 13 + $hi, $s2 * 13 + $lo];
                    }
                }
            } elseif ($suited) {
                for ($s = 0; $s < 4; $s++) {
                    $out[] = [$s * 13 + $hi, $s * 13 + $lo];
                }
            } else {
                for ($s1 = 0; $s1 < 4; $s1++) {
                    for ($s2 = 0; $s2 < 4; $s2++) {
                        if ($s1 !== $s2) {
                            $out[] = [$s1 * 13 + $hi, $s2 * 13 + $lo];
                        }
                    }
                }
            }
        }

        return array_values(array_filter($out, fn($c) => !isset($deadSet[$c[0]]) && !isset($deadSet[$c[1]])));
    }

    /** Grupo legible según el percentil. */
    public function group(float $p): string
    {
        return match (true) {
            $p <= 3 => 'premium',
            $p <= 8 => 'strong',
            $p <= 15 => 'good',
            $p <= 30 => 'playable',
            $p <= 50 => 'speculative',
            default => 'trash',
        };
    }

    /** Puntuación de Chen de una mano canónica. */
    public static function chen(int $hi, int $lo, bool $suited): float
    {
        $score = self::POINTS[$hi];
        if ($hi === $lo) {
            return ceil(max(5.0, $score * 2));
        }
        if ($suited) {
            $score += 2;
        }
        $gap = $hi - $lo - 1;
        $score -= match (true) {
            $gap <= 0 => 0,
            $gap === 1 => 1,
            $gap === 2 => 2,
            $gap === 3 => 4,
            default => 5,
        };
        if ($gap <= 1 && $hi < 10) { // conectadas o con un hueco por debajo de la Q: bonus de escalera
            $score += 1;
        }

        return ceil($score);
    }

    /** @return array<string,float> código → percentil */
    private function table(): array
    {
        if ($this->table !== null) {
            return $this->table;
        }

        $path = storage_path('app/brain/preflop-ranking.json');
        if (is_file($path)) {
            return $this->table = json_decode(file_get_contents($path), true) ?: $this->build($path);
        }

        return $this->table = $this->build($path);
    }

    private function build(string $path): array
    {
        $letters = FastHandEvaluator::RANKS;
        $hands = [];

        for ($hi = 12; $hi >= 0; $hi--) {
            for ($lo = $hi; $lo >= 0; $lo--) {
                if ($hi === $lo) {
                    $hands[] = ['code' => $letters[$hi] . $letters[$lo], 'combos' => 6, 'score' => self::chen($hi, $lo, false), 'hi' => $hi, 'lo' => $lo];
                } else {
                    $hands[] = ['code' => $letters[$hi] . $letters[$lo] . 's', 'combos' => 4, 'score' => self::chen($hi, $lo, true), 'hi' => $hi, 'lo' => $lo];
                    $hands[] = ['code' => $letters[$hi] . $letters[$lo] . 'o', 'combos' => 12, 'score' => self::chen($hi, $lo, false), 'hi' => $hi, 'lo' => $lo];
                }
            }
        }

        // Mejor primero; a igualdad: la carta alta y la baja más altas, y suited antes que offsuit
        usort($hands, fn($a, $b) => [$b['score'], $b['hi'], $b['lo'], str_ends_with($b['code'], 's') ? 1 : 0]
            <=> [$a['score'], $a['hi'], $a['lo'], str_ends_with($a['code'], 's') ? 1 : 0]);

        $total = array_sum(array_column($hands, 'combos')); // 1326
        $table = [];
        $before = 0;
        foreach ($hands as $h) {
            $table[$h['code']] = round(($before + $h['combos'] / 2) / $total * 100, 2);
            $before += $h['combos'];
        }

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, json_encode($table));

        return $table;
    }
}
