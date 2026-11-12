<?php

namespace App\Services\Ev;

/**
 * Evaluador rápido de manos de 5 a 7 cartas.
 *
 * Devuelve un entero comparable: a mayor valor, mejor mano.
 *   categoría (0-8) << 20 | hasta 5 valores de desempate de 4 bits cada uno.
 * Categorías: 0 carta alta, 1 pareja, 2 doble pareja, 3 trío, 4 escalera, 5 color,
 *             6 full, 7 póker, 8 escalera de color.
 *
 * Las cartas se pasan como enteros 0-51 (rango = n % 13, palo = intdiv(n, 13)) o como texto "A♠".
 */
class FastHandEvaluator
{
    public const RANKS = '23456789TJQKA';
    private const SUITS = ['♠' => 0, '♥' => 1, '♦' => 2, '♣' => 3];

    /** "A♠" → entero 0-51 */
    public static function card(string $c): int
    {
        $rank = strpos(self::RANKS, mb_substr($c, 0, 1));
        $suit = self::SUITS[mb_substr($c, 1, 1)] ?? null;
        if ($rank === false || $suit === null) {
            throw new \InvalidArgumentException("Carta no válida: {$c}");
        }

        return $suit * 13 + $rank;
    }

    /** @param string[] $cards */
    public static function cards(array $cards): array
    {
        return array_map(fn($c) => is_int($c) ? $c : self::card($c), $cards);
    }

    /**
     * Puntuación de la mejor mano de 5 entre las cartas dadas (5, 6 o 7).
     *
     * @param bool $straights false = ignora escaleras (y escaleras de color): mano "vista" por quien no las reconoce
     * @param bool $flushes   false = ignora colores (y escaleras de color)
     */
    public static function score(array $cards, bool $straights = true, bool $flushes = true): int
    {
        $cnt = array_fill(0, 13, 0);
        $suitCnt = [0, 0, 0, 0];
        $suitMask = [0, 0, 0, 0];
        $all = 0;

        foreach ($cards as $c) {
            $r = $c % 13;
            $s = intdiv($c, 13);
            $cnt[$r]++;
            $suitCnt[$s]++;
            $suitMask[$s] |= 1 << $r;
            $all |= 1 << $r;
        }

        // Color (y escalera de color)
        for ($s = 0; $flushes && $s < 4; $s++) {
            if ($suitCnt[$s] >= 5) {
                $sf = $straights ? self::straightHigh($suitMask[$s]) : -1;
                if ($sf >= 0) {
                    return (8 << 20) | ($sf << 16);
                }
                $flush = self::topRanks($suitMask[$s], 5);
                return (5 << 20) | self::pack($flush);
            }
        }

        // Grupos de rangos repetidos, de mayor a menor rango
        $quad = -1;
        $trips = [];
        $pairs = [];
        for ($r = 12; $r >= 0; $r--) {
            switch ($cnt[$r]) {
                case 4: $quad = $r; break;
                case 3: $trips[] = $r; break;
                case 2: $pairs[] = $r; break;
            }
        }

        if ($quad >= 0) {
            $kick = self::topRanks($all & ~(1 << $quad), 1);
            return (7 << 20) | self::pack([$quad, $kick[0] ?? 0]);
        }

        if ($trips && (count($trips) > 1 || $pairs)) {
            $pair = count($trips) > 1 ? max($trips[1], $pairs[0] ?? -1) : $pairs[0];
            return (6 << 20) | self::pack([$trips[0], $pair]);
        }

        $st = $straights ? self::straightHigh($all) : -1;
        if ($st >= 0) {
            return (4 << 20) | ($st << 16);
        }

        if ($trips) {
            $kick = self::topRanks($all & ~(1 << $trips[0]), 2);
            return (3 << 20) | self::pack([$trips[0], ...$kick]);
        }

        if (count($pairs) >= 2) {
            $kick = self::topRanks($all & ~(1 << $pairs[0]) & ~(1 << $pairs[1]), 1);
            return (2 << 20) | self::pack([$pairs[0], $pairs[1], $kick[0] ?? 0]);
        }

        if ($pairs) {
            $kick = self::topRanks($all & ~(1 << $pairs[0]), 3);
            return (1 << 20) | self::pack([$pairs[0], ...$kick]);
        }

        return self::pack(self::topRanks($all, 5));
    }

    /** Carta alta de la escalera (0-12) o -1. La rueda A-2-3-4-5 vale 3 (cinco alto). */
    private static function straightHigh(int $mask): int
    {
        for ($high = 12; $high >= 4; $high--) {
            if ((($mask >> ($high - 4)) & 31) === 31) {
                return $high;
            }
        }
        if (($mask & (1 << 12)) && (($mask & 15) === 15)) {
            return 3;
        }

        return -1;
    }

    /** Los n rangos más altos presentes en la máscara. */
    private static function topRanks(int $mask, int $n): array
    {
        $out = [];
        for ($r = 12; $r >= 0 && count($out) < $n; $r--) {
            if ($mask & (1 << $r)) {
                $out[] = $r;
            }
        }

        return $out;
    }

    private static function pack(array $ranks): int
    {
        $v = 0;
        for ($i = 0; $i < 5; $i++) {
            $v |= ($ranks[$i] ?? 0) << (16 - 4 * $i);
        }

        return $v;
    }

    /** Nombre de la categoría (para depuración). */
    public static function category(int $score): int
    {
        return $score >> 20;
    }
}
