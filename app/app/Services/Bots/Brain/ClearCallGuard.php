<?php

namespace App\Services\Bots\Brain;

/**
 * Nadie foldea una mano con muchísima más equity que la que pide el precio.
 *
 * Las estadísticas del perfil (fold ante apuesta) y la imprecisión de su nivel dejan siempre algo de probabilidad de foldear, también con una mano buena:
 * una de cada veinte veces el bot tira un proyecto de color al nut pagando el 14 % del bote. Un jugador, aunque sea muy flojo o muy tight, puede
 * equivocarse con una mano marginal, pero no tira una mano que gana el triple de lo que necesita para pagar.
 *
 * Cuando la equity que ve el bot (la menor entre su equity y la que tiene contra el rango del rival) supera el doble del precio y un mínimo,
 * todo el fold pasa a call y la imprecisión ya no puede reintroducirlo. Solo se aplica postflop y ante una apuesta.
 */
class ClearCallGuard
{
    /** Equity mínima (%) para considerar que la mano no se tira. */
    public const MIN_EQUITY = 35.0;

    /** Cuántas veces la equity que pide el precio (pot odds) hay que tener. */
    public const PRICE_MULTIPLE = 2.0;

    public function applies(array $features): bool
    {
        if (($features['street'] ?? 'preflop') === 'preflop' || empty($features['facing_bet'])) {
            return false;
        }
        $equity = $features['equity'] ?? null;
        $odds = $features['pot_odds'] ?? null;
        if (! is_numeric($equity) || ! is_numeric($odds)) {
            return false;
        }
        $equity = min((float) $equity, (float) ($features['range_equity'] ?? $equity));

        return $equity >= self::MIN_EQUITY && $equity >= self::PRICE_MULTIPLE * (float) $odds;
    }

    /** Pasa todo el fold a call si la mano no se tira. Devuelve si aplicó. */
    public function apply(DecisionMatrix $matrix, array $features): bool
    {
        if (! $this->applies($features)) {
            return false;
        }
        $matrix->convert(['fold'], 'call');

        return true;
    }
}
