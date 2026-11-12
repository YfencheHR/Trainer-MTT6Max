<?php

namespace App\Services\Bots\Brain;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Motor de decisión de un bot a partir de su "cerebro" (reglas + habilidad).
 *
 * Por cada decisión (excepción: los bots Pool usan su tabla persistida directamente en preflop):
 *  1. PERCEPCIÓN  → características de la situación tal como las ve el bot (limitadas por su habilidad).
 *  2. TABLA       → tabla de porcentajes por acción, que parte con la acción por defecto (fold) al 100 %.
 *  3. REGLAS      → las estadísticas del jugador y las reglas (anidables) cuya condición se cumple van moviendo
 *                   puntos porcentuales de unas acciones a otras.
 *  4. MATRIZ      → la tabla heurística preflop se mezcla con la distribución del perfil, si existe.
 *  5. IMPRECISIÓN → según su habilidad, un % de puntos pasa a acciones de error.
 *  6. SORTEO      → se sortea un número de 0 a 100 (dos decimales): esa es la acción.
 *  7. TRADUCCIÓN  → la acción se convierte en una acción legal del motor.
 *
 * En preflop, los bots Pool usan su tabla persistida y no mezclan las reglas ni la matriz compartida anterior.
 *
 * Todo el azar (percepción, reglas con "chance", sorteo final) sale de un generador con
 * semilla que se guarda en el historial: la misma semilla + el mismo cerebro reproduce la decisión.
 *
 * Cerebro (JSON):
 * {
 *   "name": "TAG", "version": 1,
 *   "default": "fold",                         acción con la que parte la tabla (100 %)
 *   "skill": { "perception": {…}, "noise": {…}, "equity_samples": 250 },
 *   "imprecision": { "rate": 4, "mistakes": {"fold": 50, "call": 50}, "protect": ["allin"] },
 *   "rules": [ … ]                             ver RuleEngine
 * }
 */
class BrainRunner
{
    public function __construct(
        private Perceiver $perceiver,
        private RuleEngine $rules,
        private ActionCatalog $catalog,
        private PreflopStrategyMatrix $preflopStrategies,
        private PreflopOpponentDanger $preflopDanger,
    ) {}

    /**
     * @param  array  $ctx  contexto de la decisión (ver HandPlayService::botContext)
     * @return array{action:string, amount:int, reason:string, flags:array, trace:array}
     */
    public function decide(array $ctx, array $brain, ?int $seed = null): array
    {
        $seed ??= random_int(1, 2147483646);
        $rng = new Randomizer(new Mt19937($seed));

        // 1) Percepción
        $perception = $this->perceiver->perceive($ctx, $brain, $rng);
        $features = $perception['features'];

        // 2-3) Matriz + reglas
        $matrix = new DecisionMatrix($brain['default'] ?? 'fold');
        $fired = [];
        $strategy = $this->preflopStrategies->forDecision($ctx, $features);
        if (($strategy['source'] ?? null) === 'pool_tables') {
            $matrix->mix($strategy['distribution']);
        } else {
            $rules = $brain['rules'] ?? [];
            if ($strategy) {
                $rules[] = [
                    'id' => 'preflop_hand_matrix',
                    'name' => 'Mezcla con matriz preflop heurística',
                    'priority' => 90,
                    'when' => ['f' => 'street', 'op' => '=', 'v' => 'preflop'],
                    'paint' => [[
                        'blend' => $strategy['distribution'],
                        'weight' => $strategy['prior_weight'],
                    ]],
                ];
            }
            $this->rules->apply($rules, $features, $matrix, $rng, $fired);
        }

        // 4) Ajuste de fold por la presión de los rivales que aún pueden actuar
        $danger = $this->preflopDanger->apply($matrix, $ctx, $features, $brain['stats'] ?? []);

        // 5) Imprecisión de la habilidad
        $lapse = $this->applyImprecision($matrix, $brain['imprecision'] ?? []);

        // 6) Sorteo: un único número de 0 a 100 con dos decimales
        $distribution = $matrix->distribution();
        $roll = $rng->getInt(0, 9999) / 100;
        $cell = $matrix->pick($roll);

        // 6) Traducción a una acción legal
        $resolved = $this->catalog->resolve($cell, [
            'legal' => $ctx['legal'],
            'pot' => $ctx['pot'],
            'to_call' => $ctx['to_call'],
            'current_bet' => $ctx['current_bet'],
            'stack' => $ctx['me']['stack'],
            'bet' => $ctx['me']['bet'],
            'big_blind' => $ctx['big_blind'],
            'limpers' => $features['limpers'],
            'preflop_raises' => $features['preflop_raises'],
            'bet_size_factor' => $features['bet_size_factor'] ?? 1.0,
            'flags' => $ctx['me']['flags'] ?? [],
        ]);

        $top = [];
        foreach (array_slice($distribution, 0, 4, true) as $a => $pct) {
            $top[] = "{$a} ".rtrim(rtrim(number_format($pct, 1), '0'), '.').'%';
        }
        $reason = 'matriz: '.implode(' · ', $top).' → sorteo '.number_format($roll, 2, ',', '')." = {$cell}"
            .($resolved['note'] ? " ({$resolved['note']})" : '');
        if ($strategy) {
            $reason .= ' · tabla '.$strategy['scenario'].'/'.$strategy['hand_code'].'/'.$strategy['position'];
        }
        if ($danger['moved_pct'] > 0) {
            $reason .= ' · presión detrás: +'.number_format($danger['moved_pct'], 1, ',', '').'% fold';
        }

        return [
            'action' => $resolved['action'],
            'amount' => $resolved['amount'],
            'reason' => $reason,
            'flags' => $resolved['flags'],
            'trace' => [
                'engine' => 'matrix',
                'seed' => $seed,
                'brain' => [
                    'name' => $brain['name'] ?? null,
                    'version' => $brain['version'] ?? null,
                    'hash' => self::hash($brain),
                ],
                'features' => $features,          // lo que el bot percibe
                'real' => $perception['real'],    // la realidad (solo para analizar; las reglas no la ven)
                'perception' => $perception['notes'],
                'fired_rules' => $fired,
                'distribution' => $distribution,
                'preflop_strategy' => $strategy,
                'preflop_danger' => $danger,
                'lapse_pct' => $lapse,
                'draw' => ['roll' => $roll, 'action' => $cell],
                'resolved' => ['action' => $resolved['action'], 'amount' => $resolved['amount'], 'tag' => $resolved['tag']],
            ],
        ];
    }

    /** Matriz resultante sin sortear (para el laboratorio y las pruebas). */
    public function matrixFor(array $ctx, array $brain, ?int $seed = null): array
    {
        $seed ??= random_int(1, 2147483646);
        $rng = new Randomizer(new Mt19937($seed));
        $perception = $this->perceiver->perceive($ctx, $brain, $rng);
        $matrix = new DecisionMatrix($brain['default'] ?? 'fold');
        $fired = [];
        $strategy = $this->preflopStrategies->forDecision($ctx, $perception['features']);
        if (($strategy['source'] ?? null) === 'pool_tables') {
            $matrix->mix($strategy['distribution']);
        } else {
            $rules = $brain['rules'] ?? [];
            if ($strategy) {
                $rules[] = [
                    'id' => 'preflop_hand_matrix',
                    'name' => 'Mezcla con matriz preflop heurística',
                    'priority' => 90,
                    'when' => ['f' => 'street', 'op' => '=', 'v' => 'preflop'],
                    'paint' => [[
                        'blend' => $strategy['distribution'],
                        'weight' => $strategy['prior_weight'],
                    ]],
                ];
            }
            $this->rules->apply($rules, $perception['features'], $matrix, $rng, $fired);
        }
        $danger = $this->preflopDanger->apply($matrix, $ctx, $perception['features'], $brain['stats'] ?? []);
        $this->applyImprecision($matrix, $brain['imprecision'] ?? []);

        return [
            'matrix' => $matrix,
            'features' => $perception['features'],
            'real' => $perception['real'],
            'perception' => $perception['notes'],
            'fired_rules' => $fired,
            'distribution' => $matrix->distribution(),
            'preflop_strategy' => $strategy,
            'preflop_danger' => $danger,
        ];
    }

    /**
     * Imprecisión: $rate puntos porcentuales de la tabla pasan a acciones de error.
     * Probabilidad de equivocarse = rate %. "protect" evita que se toquen ciertas acciones
     * (p. ej. un experto con una mano monstruo nunca hace fold: protect ["allin"]).
     *
     * @return float puntos movidos
     */
    private function applyImprecision(DecisionMatrix $matrix, array $cfg): float
    {
        $rate = (float) ($cfg['rate'] ?? 0);
        $mistakes = (array) ($cfg['mistakes'] ?? []);
        if ($rate <= 0 || ! $mistakes) {
            return 0.0;
        }
        $matrix->lapse($rate, $mistakes, (array) ($cfg['protect'] ?? []));

        return round($rate, 2);
    }

    public static function hash(array $brain): string
    {
        return substr(md5(json_encode($brain)), 0, 8);
    }
}
