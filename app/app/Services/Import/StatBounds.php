<?php

namespace App\Services\Import;

/**
 * Margen de protección para estadísticas agregadas con valores extremos poco creíbles.
 *
 * Un perfil medio que "foldea el 100 % a un 3-bet" no existe: es muestra pequeña o un promedio que oculta que sus manos
 * fuertes sí continúan. Si el generador de rangos aplica ese 100 % tal cual, foldea hasta AA. Aquí se acotan SOLO las
 * estadísticas de respuesta/defensa donde ni 0 ni 100 tienen sentido como tendencia de un jugador.
 *
 * NO se tocan las que sí pueden valer 0 con toda lógica (abrir/RFI, PFR, limp, 3-bet, 4-bet, squeeze, check-raise,
 * raise a una apuesta…): hay jugadores que nunca abren y solo limpean, o nunca suben.
 *
 * Los límites son [mínimo, máximo] y se pueden ajustar aquí sin tocar nada más.
 */
class StatBounds
{
    /** @var array<string, array{0: float, 1: float}> */
    public const BOUNDS = [
        // Fold ante agresión: nadie foldea siempre ni nunca
        'fold_to_3bet' => [5, 90],
        'fold_to_4bet' => [5, 90],
        'f2cbet' => [5, 90],
        'f2raise_flop' => [5, 90],
        'f2raise_turn' => [5, 90],
        'f2beturn' => [5, 90],
        'f2betriver' => [5, 90],
        // Seguir (call) ante un 3-bet: 100 % significaría no foldear ni subir jamás
        'call_vs_3bet' => [0, 90],
        // Defensa de ciegas ante un robo
        'bb_defense' => [5, 95],
        'sb_defense' => [5, 95],
        // Apuesta de continuación: ni siempre ni nunca
        'cbet' => [5, 95],
    ];

    /**
     * Acota los valores presentes y mantiene coherente la respuesta a un 3-bet (fold + call + re-raise = 100):
     * lo que se le quita al fold pasa a call, que es la continuación habitual.
     *
     * @param  array<string, int|float>  $values
     * @return array<string, int|float>
     */
    public static function apply(array $values): array
    {
        $clamped = false;
        foreach (self::BOUNDS as $stat => [$min, $max]) {
            if (! array_key_exists($stat, $values) || $values[$stat] === null) {
                continue;
            }
            $v = $values[$stat];
            $new = min($max, max($min, $v));
            if ($new != $v) {
                $values[$stat] = is_int($v) ? (int) round($new) : (float) $new;
                $clamped = true;
            }
        }

        if ($clamped && isset($values['fold_to_3bet'], $values['call_vs_3bet'], $values['reraise_vs_3bet'])) {
            $residual = 100 - $values['fold_to_3bet'] - $values['call_vs_3bet'] - $values['reraise_vs_3bet'];
            if ($residual > 0) {
                $values['call_vs_3bet'] = round(min(self::BOUNDS['call_vs_3bet'][1], $values['call_vs_3bet'] + $residual), 2);
            } elseif ($residual < 0) {
                $call = max(0, $values['call_vs_3bet'] + $residual);
                $values['call_vs_3bet'] = round($call, 2);
            }
        }

        return $values;
    }
}
