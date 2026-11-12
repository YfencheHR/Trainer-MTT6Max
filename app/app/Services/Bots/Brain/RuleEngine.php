<?php

namespace App\Services\Bots\Brain;

use Random\Randomizer;

/**
 * Recorre el árbol de reglas de un cerebro y rellena la matriz de decisión.
 *
 * Regla (JSON):
 * {
 *   "id": "vs_apuesta_monstruo",        identificador (para el historial)
 *   "name": "…",                        opcional
 *   "priority": 10,                     orden de aplicación: de menor a mayor (la última pinta encima)
 *   "enabled": true,                    opcional
 *   "chance": 30,                       opcional: % de veces que se considera la regla (otro aleatorio)
 *   "when": { condición anidable },     opcional: sin condición = siempre
 *   "paint": [ operación, … ],          cómo rellena la matriz
 *   "rules": [ reglas hijas … ],        se evalúan solo si esta regla se cumple, después de pintar
 *   "stop": true                        opcional: tras aplicarse, no se evalúan las reglas hermanas siguientes
 * }
 *
 * Operaciones sobre la tabla de porcentajes (siempre en puntos porcentuales, la tabla suma 100):
 *   { "action": "allin" }                                 toda la tabla pasa a all-in (100 %)
 *   { "action": "slowplay", "pct": 20 }                   20 puntos pasan a slowplay (se quitan de las demás)
 *   { "action": "call", "only": ["fold"] }                lo que tenga fold pasa a call
 *   { "action": "bet_pot", "pct": 10, "only": ["call"] }  10 puntos pasan de call a bet_pot
 *   { "mix": { "call": 70, "raise_half": 30 } }           reparte según pesos (se normalizan a 100)
 *   { "mix": { … }, "only": ["fold", "check"] }           solo redistribuye lo que tienen esas acciones
 *   { "add": { "call": 15, "bet_pot": 5 }, "from": ["fold"] }   suma puntos a esas acciones quitándolos de "from"
 * Los pesos, los puntos y "pct" pueden ser {"ref": "feature", "scale": 2}.
 * (Formato antiguo: "rows": "1-20" con "action" equivale a "pct": 20.)
 *
 * Dos operaciones más, para construir la matriz DESDE LAS ESTADÍSTICAS del jugador:
 *   { "prior": "facing_bet", "raise": "bet_pot" }   pinta la probabilidad base (fold / call / subida) que sale de sus
 *   { "prior": "lead", "bet": "bet_half" }           estadísticas y de la fuerza de su mano en su rango (ver StatsPrior)
 *   { "mod": { "fold_mult": 1.5, "raise_mult": 0.5, "bet_mult": 1.2 } }   multiplica el prior (se acumula); lo usan las
 *   reglas de situación (tamaño de la apuesta, agresividad del rival…) y deben aplicarse ANTES del prior (prioridad menor).
 *   { "blend": { "call": 60, "raise_x3": 40 }, "weight": 0.65 } mezcla esta distribución con la actual.
 */
class RuleEngine
{
    public const MAX_DEPTH = 8;

    public const MAX_RULES = 600;

    /** Multiplicadores acumulados por las reglas durante esta decisión. */
    private array $mods = [];

    public function __construct(private ConditionEvaluator $conditions, private StatsPrior $prior) {}

    /**
     * @param  array[]  $rules
     * @param  array[]  $fired  (salida) reglas aplicadas, en orden
     */
    public function apply(array $rules, array $features, DecisionMatrix $matrix, Randomizer $rng, array &$fired, int $depth = 0, int &$visited = 0): void
    {
        if ($depth === 0) {
            $this->mods = [];
        }
        if ($depth > self::MAX_DEPTH) {
            return;
        }

        $ordered = $rules;
        $i = 0;
        foreach ($ordered as &$r) {
            $r['__i'] = $i++;
        }
        unset($r);
        usort($ordered, fn ($a, $b) => [($a['priority'] ?? 0), $a['__i']] <=> [($b['priority'] ?? 0), $b['__i']]);

        foreach ($ordered as $rule) {
            if (++$visited > self::MAX_RULES) {
                return;
            }
            if (($rule['enabled'] ?? true) === false) {
                continue;
            }
            if (! $this->conditions->passes($rule['when'] ?? null, $features)) {
                continue;
            }
            if (isset($rule['chance']) && $rng->getInt(1, 100) > (float) $rule['chance']) {
                continue; // la regla no se considera esta vez
            }

            $fired[] = ['id' => $rule['id'] ?? '?', 'depth' => $depth] + (isset($rule['chance']) ? ['chance' => $rule['chance']] : []);

            foreach ($rule['paint'] ?? [] as $op) {
                $this->paint($op, $features, $matrix);
            }

            if (! empty($rule['rules'])) {
                $this->apply($rule['rules'], $features, $matrix, $rng, $fired, $depth + 1, $visited);
            }

            if (! empty($rule['stop'])) {
                return;
            }
        }
    }

    /** Multiplicadores que han fijado las reglas (para el historial). */
    public function mods(): array
    {
        return $this->mods;
    }

    private function paint(array $op, array $features, DecisionMatrix $matrix): void
    {
        if (isset($op['mod'])) {
            foreach ($op['mod'] as $k => $v) {
                $v = $this->conditions->value($v, $features);
                if (is_numeric($v)) {
                    $this->mods[$k] = ($this->mods[$k] ?? 1.0) * (float) $v;
                }
            }

            return;
        }

        if (isset($op['blend']) && is_array($op['blend'])) {
            $matrix->blend($op['blend'], (float) ($op['weight'] ?? 1.0));

            return;
        }

        if (isset($op['prior'])) {
            $this->paintPrior($op, $features, $matrix);

            return;
        }

        $only = isset($op['only']) ? (array) $op['only'] : null;

        // Sumar puntos a varias acciones
        if (isset($op['add']) && is_array($op['add'])) {
            foreach ($op['add'] as $action => $pts) {
                $v = $this->conditions->value($pts, $features);
                if (is_numeric($v)) {
                    $matrix->add($action, (float) $v, isset($op['from']) ? (array) $op['from'] : null);
                }
            }

            return;
        }

        // Reparto por pesos
        if (isset($op['mix']) && is_array($op['mix'])) {
            $weights = [];
            foreach ($op['mix'] as $action => $w) {
                $v = $this->conditions->value($w, $features);
                if (is_numeric($v)) {
                    $weights[$action] = (float) $v;
                }
            }
            $matrix->mix($weights, $only);

            return;
        }

        if (! isset($op['action'])) {
            return;
        }

        // Una acción: un porcentaje concreto, lo que tengan otras acciones, o toda la tabla
        $pct = isset($op['pct']) ? $this->conditions->value($op['pct'], $features) : $this->legacyShare($op, $features);
        if ($pct !== null && is_numeric($pct)) {
            $matrix->add($op['action'], (float) $pct, $only);
        } elseif ($only !== null) {
            $matrix->convert($only, $op['action']);
        } else {
            $matrix->set($op['action']);
        }
    }

    /** Formato antiguo: "rows": "1-20" (× "cols") equivale al % de la cuadrícula que cubría. */
    private function legacyShare(array $op, array $features): ?float
    {
        if (! isset($op['rows']) && ! isset($op['cols'])) {
            return null;
        }
        $rows = $this->bounds($op['rows'] ?? null, $features);
        $cols = $this->bounds($op['cols'] ?? null, $features);
        $share = (abs($rows[1] - $rows[0]) + 1) * (abs($cols[1] - $cols[0]) + 1) / 100;

        return $share >= 100 ? null : $share;
    }

    /** Pinta la probabilidad base de las estadísticas del jugador (ver StatsPrior). */
    private function paintPrior(array $op, array $features, DecisionMatrix $matrix): void
    {
        $only = isset($op['only']) ? (array) $op['only'] : null;
        if ($op['prior'] === 'facing_bet') {
            $p = $this->prior->facingBet($features, $this->mods);
            $weights = ['fold' => $p['fold'], 'call' => $p['call'], ($op['raise'] ?? 'bet_pot') => $p['raise']];
        } else {
            $p = $this->prior->lead($features, $this->mods);
            $weights = [($op['bet'] ?? 'bet_half') => $p['bet'], 'check' => $p['check']];
        }
        $matrix->mix(array_filter($weights, fn ($w) => $w > 0), $only);
    }

    /** "1-30", [1, 30], 5, {"ref": …} o null (= todo) → [desde, hasta] */
    private function bounds(mixed $v, array $features): array
    {
        if ($v === null) {
            return [1, 100];
        }
        if (is_string($v) && str_contains($v, '-')) {
            [$a, $b] = array_map('trim', explode('-', $v, 2));

            return [(int) $a, (int) $b];
        }
        if (is_array($v) && ! array_key_exists('ref', $v)) {
            return [(int) $this->conditions->value($v[0] ?? 1, $features), (int) $this->conditions->value($v[1] ?? 100, $features)];
        }
        $x = (int) $this->conditions->value($v, $features);

        return [$x, $x];
    }
}
