<?php

namespace App\Services\Bots\Brain;

/**
 * Probabilidad BASE de cada acción a partir de las ESTADÍSTICAS del propio jugador (el "prior"),
 * repartida según la fuerza de la mano dentro de SU rango (range_rank: 0 = lo mejor, 100 = lo peor).
 *
 * La matriz parte de fold en todas las celdas y el prior "suma" casillas de call / subida / apuesta
 * según lo que el jugador hace de media. Después las reglas la modulan con lo que pasa en esa mano
 * (tamaño de la apuesta, agresividad del rival…) mediante multiplicadores.
 *
 * ANTE UNA APUESTA   (fold a la apuesta, agresividad → cuánto sube del resto)
 *   fold  = f2bet × peso_débil(q)  × fold_mult     las manos peores de su rango son las que abandonan
 *   raise = (1 − fold) × share     × peso_fuerte(q) × raise_mult     las mejores son las que suben
 *   call  = el resto
 *   Un jugador con f2bet = 0 NO foldea nunca, y uno con raise = 0 no sube nunca, tenga lo que tenga.
 *
 * SIN APUESTA DELANTE (le toca abrir la acción)
 *   bet = frecuencia × [ valor × peso_fuerte(q) + (1 − valor) × peso_farol(q) ] × bet_mult
 *   con  valor = vb / (1 + vb)  (ratio valor:farol; 2.0 → 2 apuestas de valor por cada farol).
 *   Las manos de valor salen de lo mejor de su rango y los faroles de manos sin valor de showdown.
 *
 * Los pesos concentran: peso_fuerte(q) = 3(1 − q)²  ·  peso_débil(q) = 3 q²  (ambos promedian 1 en el rango).
 */
class StatsPrior
{
    /**
     * @param array<string,float|int|bool|string|null> $f  características (own_*, range_rank, bluff_candidate…)
     * @param array<string,float> $mods  multiplicadores acumulados por las reglas (fold_mult, raise_mult, bet_mult)
     * @return array{fold: float, call: float, raise: float}  porcentajes (suman 100)
     */
    public function facingBet(array $f, array $mods = []): array
    {
        $q = $this->q($f);
        $d = $this->drawPower($f);

        // Un proyecto cuenta como una mano más fuerte para CONTINUAR: abandona menos que el aire con su misma fuerza
        $qEff = $q * (1 - self::DRAW_CONTINUE * $d);
        $fold = (float) ($f['own_f2bet'] ?? 30) * $this->weak($qEff) * ($mods['fold_mult'] ?? 1.0);
        $fold = $this->clamp($fold, 0, 100);

        // Subir lo empujan las manos fuertes (valor) Y los proyectos (semifarol); la agresión de sus estadísticas multiplica el semifarol
        $raiseBase = (float) ($f['own_raise_vs_bet'] ?? 0);
        $raiseWeight = $this->strong($q) + self::SEMIBLUFF_RAISE * $d * $this->aggMult($f);
        $raise = (100 - $fold) * ($raiseBase / 100) * $raiseWeight * ($mods['raise_mult'] ?? 1.0);
        $raise *= $this->clamp(((float) ($f['opponent_call_frequency'] ?? 50.0)) / 50.0, 0.75, 1.25);
        $raise = $this->clamp($raise, 0, 100 - $fold);

        return ['fold' => $fold, 'call' => 100 - $fold - $raise, 'raise' => $raise];
    }

    /**
     * @return array{bet: float, check: float}  porcentajes (suman 100)
     */
    public function lead(array $f, array $mods = []): array
    {
        $q = $this->q($f);
        $freq = (float) ($f['own_bet_freq'] ?? 30);
        $vb = max(0.01, (float) ($f['own_vb_ratio'] ?? 2.0));
        $value = $vb / (1 + $vb);

        // Los faroles salen de manos sin valor de showdown (aire / proyectos): si no es candidata, casi no hay farol
        $bluffW = !empty($f['bluff_candidate']) ? $this->weak($q) : 0.0;
        // Semifarol: con un proyecto apuesta más que con aire puro, y cuanto más agresivo es (AF) más lo hace
        if (($f['made_category'] ?? 0) <= 1) {
            $bluffW += self::SEMIBLUFF_BET * $this->drawPower($f) * $this->aggMult($f);
        }
        $p = $freq * ($value * $this->strong($q) + (1 - $value) * $bluffW) * ($mods['bet_mult'] ?? 1.0);
        if (($f['street'] ?? '') === 'river' && !empty($f['in_position']) && ($f['value_combo_pct'] ?? 100) <= 20) {
            $p *= $this->clamp(((float) ($f['opponent_call_frequency'] ?? 50.0)) / 50.0, 0.8, 1.3);
        }
        $p = $this->clamp($p, 0, 100);

        return ['bet' => $p, 'check' => 100 - $p];
    }

    /** Peso de un proyecto de escalera / color al continuar (0-1): se aplica solo en flop y turn. */
    private const DRAW_CONTINUE = 0.8;

    /** Cuánto empuja un proyecto hacia SUBIR ante una apuesta (semifarol), antes de multiplicarlo por la agresión del jugador. */
    private const SEMIBLUFF_RAISE = 0.8;

    /** Idem al APOSTAR sin apuesta delante: debe superar el peso de los faroles de aire puro (que se concentran en lo peor del rango). */
    private const SEMIBLUFF_BET = 3.0;

    /** Peso de cada tipo de proyecto (suman por separado: un combo de color + escalera pesa más que cualquiera de los dos). */
    private const W_FLUSH = 0.65;
    private const W_STRAIGHT = 0.58; // 8 outs ≈ 31 % de equity frente a 35 % de las 9 del color

    /** Backdoors (solo flop): llegan a ~4 % de equity cada uno. Suman aparte y poco. */
    private const W_BD_FLUSH = 0.15;
    private const W_BD_STRAIGHT = 0.12;

    /**
     * Fuerza del proyecto (0 a ~1,4). Color y escalera se calculan por separado y se suman:
     *   color     = outs / 9 (9 = proyecto completo)  × W_FLUSH
     *   escalera  = outs / 8 (8 = abierto; gutshot = 0,5) × W_STRAIGHT
     * River: no hay proyecto.
     */
    private function drawPower(array $f): float
    {
        if (($f['street'] ?? '') === 'river') {
            return 0.0;
        }
        if (!array_key_exists('flush_draw_outs', $f) && !array_key_exists('straight_draw_outs', $f)) {
            return $this->clamp(((float) ($f['draw_outs'] ?? 0)) / 12, 0, 1); // características antiguas sin desglose
        }
        $flush = $this->clamp(((float) ($f['flush_draw_outs'] ?? 0)) / 9, 0, 1) * self::W_FLUSH;
        $straight = $this->clamp(((float) ($f['straight_draw_outs'] ?? 0)) / 8, 0, 1) * self::W_STRAIGHT;

        $power = $flush + $straight;

        // Backdoors: solo cuentan si no hay ya proyecto real de ese tipo (no se acumulan sobre un color o una escalera a una carta)
        if (($f['street'] ?? '') === 'flop') {
            $power += (!empty($f['backdoor_flush']) && (int) ($f['flush_draw_outs'] ?? 0) === 0 ? self::W_BD_FLUSH : 0.0)
                + (!empty($f['backdoor_straight']) && (int) ($f['straight_draw_outs'] ?? 0) === 0 ? self::W_BD_STRAIGHT : 0.0);
        }

        return $power;
    }

    /** Multiplicador de agresión de sus estadísticas: AF 2 = neutro; más agresivo semifarolea más, menos agresivo casi nunca. */
    private function aggMult(array $f): float
    {
        return $this->clamp(((float) ($f['own_af'] ?? 2.0)) / 2.0, 0.25, 2.0);
    }

    private function q(array $f): float
    {
        $rank = $this->clamp(((float) ($f['range_rank'] ?? 50)) / 100, 0, 1);
        if (($f['street'] ?? '') === 'river' && isset($f['value_combo_pct'])) {
            $comboStrength = $this->clamp((float) $f['value_combo_pct'] / 100, 0, 1);

            return 0.35 * $rank + 0.65 * $comboStrength;
        }

        return $rank;
    }

    private function strong(float $q): float
    {
        return 3 * (1 - $q) ** 2;
    }

    private function weak(float $q): float
    {
        return 3 * $q ** 2;
    }

    private function clamp(float $v, float $lo, float $hi): float
    {
        return max($lo, min($hi, $v));
    }
}
