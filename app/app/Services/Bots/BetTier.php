<?php

namespace App\Services\Bots;

use App\Services\Ev\FastHandEvaluator;

/**
 * Clasificación de una apuesta por el tipo de mano que la hace y por su tamaño, para la matriz de apuestas.
 *
 *  - Tipo de mano: "aire" si la mano no mejora lo que ya hay en el tablero (incluye proyectos y parejas del tablero),
 *    "pareja" si la mejora a una pareja, "fuerte" si llega a dos pares o más.
 *  - Tamaño: % del bote ANTES de apostar (val_*_bet_made_pct de PokerTracker), en cinco tramos.
 */
class BetTier
{
    public const TIERS = ['air', 'pair', 'strong'];

    /** Tramos de tamaño: clave => [mínimo exclusivo, máximo inclusivo] en % del bote. */
    public const BUCKETS = ['25' => [0, 25], '40' => [25, 40], '60' => [40, 60], '85' => [60, 85], 'max' => [85, PHP_INT_MAX]];

    public const STREETS = ['flop' => 3, 'turn' => 4, 'river' => 5];

    /**
     * @param  string[]  $hole  dos cartas ("A♠")
     * @param  string[]  $board  3, 4 o 5 cartas
     */
    public static function tier(array $hole, array $board): string
    {
        $boardCategory = FastHandEvaluator::category(FastHandEvaluator::score(FastHandEvaluator::cards($board)));
        $handCategory = FastHandEvaluator::category(FastHandEvaluator::score(FastHandEvaluator::cards(array_merge($board, $hole))));

        return match (true) {
            $handCategory <= $boardCategory => 'air',
            $handCategory === 1 => 'pair',
            default => 'strong',
        };
    }

    /** Tramo de un tamaño en % del bote. */
    public static function bucket(float $pctOfPot): string
    {
        foreach (self::BUCKETS as $key => [$min, $max]) {
            if ($pctOfPot > $min && $pctOfPot <= $max) {
                return $key;
            }
        }

        return 'max';
    }
}
