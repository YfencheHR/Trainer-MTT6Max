<?php

namespace App\Console\Commands\Concerns;

/** Piezas comunes de los comandos que comparan las decisiones de la IA con las reales de un jugador de PokerTracker. */
trait ScoresAgainstRealActions
{
    private const CLASSES = ['fold', 'check', 'call', 'aggr'];

    private const RANKS = 'AKQJT98765432';

    /** Clase gruesa de una acción: todo lo que sube o apuesta cuenta como «aggr». */
    private function coarse(string $action): string
    {
        return in_array($action, ['fold', 'check', 'call'], true) ? $action : 'aggr';
    }

    private function fmt(array $c): string
    {
        return implode(' ', array_map(fn ($k) => $k.' '.round($c[$k] * 100).'%', array_keys(array_filter($c, fn ($p) => $p > 0.005))));
    }

    /** 'AKs', 'AKo' o 'AA' a partir de las dos cartas ('A♠', 'K♦'). */
    private function handClass(array $cards): string
    {
        [$a, $b] = array_map(fn ($c) => [mb_substr($c, 0, 1), mb_substr($c, 1, 1)], $cards);
        if ($a[0] === $b[0]) {
            return $a[0].$b[0];
        }
        if (strpos(self::RANKS, $a[0]) > strpos(self::RANKS, $b[0])) {
            [$a, $b] = [$b, $a];
        }

        return $a[0].$b[0].($a[1] === $b[1] ? 's' : 'o');
    }
}
