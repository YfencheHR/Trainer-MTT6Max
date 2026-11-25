<?php

namespace App\Services\Bots\Brain;

/**
 * Clase de textura de un board: lo que más cambia cuánto se apuesta, se foldea y se sube (medido en las manos reales de PokerTracker).
 *
 *   alta      carta más alta: A · K · QJT (Q a T) · baja (9 o menos)
 *   conexión  C = conectado (flop: 3 cartas en una ventana de 5 rangos; turn y river: 4) · D = desconectado
 *   pareja    P = emparejado · S = sin pareja
 *   palo      F = hay 3 o más cartas de un palo · N = no
 *
 * Clave: «alta|conexión|pareja|palo», p. ej. «A|D|S|N» (A-alta, desconectado, sin pareja, sin color).
 */
class BoardTexture
{
    private const ORDER = '23456789TJQKA';

    /** @param  array<int, string>  $cards  cartas del board en el formato del motor («A♠») */
    public static function classify(array $cards): ?string
    {
        if (count($cards) < 3) {
            return null;
        }
        $ranks = [];
        $suits = [];
        foreach ($cards as $card) {
            $ranks[] = strpos(self::ORDER, strtoupper($card[0]));
            $suits[] = mb_substr($card, -1);
        }

        return self::fromValues($ranks, $suits);
    }

    /** @param  array<int, int>  $ranks  0 (dos) a 12 (as) · @param  array<int, int|string>  $suits  cualquier identificador de palo */
    public static function fromValues(array $ranks, array $suits): string
    {
        $top = max($ranks);
        $high = $top === 12 ? 'A' : ($top === 11 ? 'K' : ($top >= 8 ? 'QJT' : 'LOW'));
        $unique = array_unique($ranks);
        $window = 0;
        for ($low = 0; $low <= 9; $low++) {
            $window = max($window, count(array_filter($unique, fn ($r) => $r >= $low && $r <= $low + 4 || ($r === 12 && $low === 0))));
        }
        $connected = $window >= (count($ranks) === 3 ? 3 : 4) ? 'C' : 'D';
        $paired = count($unique) < count($ranks) ? 'P' : 'S';
        $flushy = max(array_count_values($suits)) >= 3 ? 'F' : 'N';

        return "{$high}|{$connected}|{$paired}|{$flushy}";
    }
}
