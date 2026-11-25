<?php

namespace App\Services\Bots\Brain;

use Illuminate\Support\Facades\DB;

/**
 * Lo que un perfil hace postflop según el tipo de bote, la posición y el stack (tabla bot_postflop_pot_stats, importada de PokerTracker).
 *
 * Cada celda se suaviza hacia la de nivel superior con su muestra: (aciertos + K × padre) / (n + K). Cadena:
 *   (calle, bote, cualquier posición, todos los stacks) → (posición) → (tramo de stack)
 * Si la celda base no llega a MIN_BASE oportunidades no hay dato y quien lo pide usa lo de siempre (sin inventar).
 */
class PostflopPotStats
{
    /** Muestra mínima de la celda base para fiarse de ella. */
    public const MIN_BASE = 30;

    /** Peso del nivel superior en el suavizado (en «manos»). */
    public const K = 40;

    /** @var array<int, array> caché por bot dentro de la petición */
    private static array $cache = [];

    /** @return array<string, array<string, mixed>> filas por "calle|bote|posición|tramo" */
    public static function forBot(int $botId): array
    {
        if (! array_key_exists($botId, self::$cache)) {
            $rows = [];
            foreach (DB::table('bot_postflop_pot_stats')->where('bot_id', $botId)->get() as $r) {
                $rows["{$r->street}|{$r->pot}|{$r->position}|{$r->stack_bucket}"] = (array) $r;
            }
            self::$cache[$botId] = $rows;
        }

        return self::$cache[$botId];
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Frecuencia de apostar cuando le toca y nadie ha apostado: ['bet' => %, 'n' => oportunidades de la celda base] o null.
     */
    public static function open(array $rows, string $street, string $pot, bool $inPosition, float $stackBb): ?array
    {
        $base = self::cell($rows, $street, $pot, 'any', 'all');
        if (! $base || $base['open_opps'] < self::MIN_BASE) {
            return null;
        }
        $value = self::ratio($base['opens'], $base['open_opps'], null);
        $value = self::level($rows, $street, $pot, $inPosition ? 'ip' : 'oop', 'all', 'open_opps', 'opens', $value);
        $value = self::level($rows, $street, $pot, $inPosition ? 'ip' : 'oop', BotProfile::stackBucketFor($stackBb), 'open_opps', 'opens', $value);

        return ['bet' => round(100 * $value, 1), 'n' => (int) $base['open_opps']];
    }

    /**
     * Qué hace al enfrentarse a una primera apuesta: ['fold' => %, 'call' => %, 'raise' => %, 'n' => veces de la celda base] o null.
     */
    public static function facing(array $rows, string $street, string $pot, bool $inPosition, float $stackBb): ?array
    {
        $base = self::cell($rows, $street, $pot, 'any', 'all');
        if (! $base || $base['facing'] < self::MIN_BASE) {
            return null;
        }
        $out = [];
        foreach (['folds' => 'fold', 'calls' => 'call', 'raises' => 'raise'] as $column => $name) {
            $value = self::ratio($base[$column], $base['facing'], null);
            $value = self::level($rows, $street, $pot, $inPosition ? 'ip' : 'oop', 'all', 'facing', $column, $value);
            $value = self::level($rows, $street, $pot, $inPosition ? 'ip' : 'oop', BotProfile::stackBucketFor($stackBb), 'facing', $column, $value);
            $out[$name] = $value;
        }
        $sum = array_sum($out) ?: 1.0;   // tras el suavizado por separado las tres dejan de sumar exactamente 1

        return ['fold' => round(100 * $out['fold'] / $sum, 1), 'call' => round(100 * $out['call'] / $sum, 1), 'raise' => round(100 * $out['raise'] / $sum, 1),
            'n' => (int) $base['facing']];
    }

    private static function cell(array $rows, string $street, string $pot, string $position, string $bucket): ?array
    {
        return $rows["{$street}|{$pot}|{$position}|{$bucket}"] ?? null;
    }

    /** Suaviza el valor del nivel superior con la celda de este nivel (si existe). */
    private static function level(array $rows, string $street, string $pot, string $position, string $bucket, string $nColumn, string $hitColumn, float $parent): float
    {
        $cell = self::cell($rows, $street, $pot, $position, $bucket);
        if (! $cell || $cell[$nColumn] <= 0) {
            return $parent;
        }

        return ($cell[$hitColumn] + self::K * $parent) / ($cell[$nColumn] + self::K);
    }

    private static function ratio(int|float $hits, int|float $n, ?float $default): ?float
    {
        return $n > 0 ? $hits / $n : $default;
    }
}
