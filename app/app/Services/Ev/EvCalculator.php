<?php

namespace App\Services\Ev;

/**
 * Fórmulas de Valor Esperado (EV) del póker. Sin estado ni acceso a BD: se puede usar
 * desde el análisis posterior de manos y también desde un bot en juego.
 *
 * Convenciones (en fichas o en cualquier unidad, siempre la misma):
 *  - $equity  : probabilidad de ganar la mano, repartiendo los empates (0-1).
 *  - $pot     : TODAS las fichas que hay en el centro ahora, incluida la apuesta del rival
 *               que estamos igualando. (Bote de 100 + apuesta rival de 50  →  $pot = 150.)
 *  - $toCall  : lo que hay que poner para igualar.
 *  - El EV se mide respecto a "retirarse ahora" (fold = 0): lo ya puesto en el bote es un coste hundido.
 *
 * Fórmula base:   EV = P(ganar) × lo que ganas − P(perder) × lo que pierdes
 */
class EvCalculator
{
    /** Equity mínima para que igualar tenga EV = 0 (la "pot odds" expresada como probabilidad). */
    public function requiredEquity(float $pot, float $toCall): float
    {
        if ($toCall <= 0) {
            return 0.0;
        }

        return $toCall / ($pot + $toCall);
    }

    /**
     * EV de igualar:  equity × bote − (1 − equity) × lo que pagas.
     *
     * Ejemplo: bote 100 + all-in rival 50 → $pot = 150, $toCall = 50, equity 0,35:
     *   0,35 × 150 − 0,65 × 50 = +20
     */
    public function callEv(float $equity, float $pot, float $toCall): float
    {
        return $equity * $pot - (1 - $equity) * $toCall;
    }

    /**
     * Compara las opciones ante una apuesta: retirarse (0) o igualar.
     *
     * @return array{required_equity:float, ev_fold:float, ev_call:float, best:string, margin:float}
     */
    public function facingBet(float $equity, float $pot, float $toCall): array
    {
        $evCall = $this->callEv($equity, $pot, $toCall);

        return [
            'required_equity' => $this->requiredEquity($pot, $toCall),
            'ev_fold' => 0.0,
            'ev_call' => $evCall,
            'best' => $evCall > 0 ? 'call' : 'fold',
            'margin' => abs($evCall),
        ];
    }

    /**
     * Diferencia de EV entre lo que se hizo y la mejor alternativa.
     * Positivo = se ganó EV respecto a la alternativa; negativo = se perdió EV.
     */
    public function difference(float $evTaken, float $evAlternative): float
    {
        return $evTaken - $evAlternative;
    }
}
