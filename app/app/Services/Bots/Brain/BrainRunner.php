<?php

namespace App\Services\Bots\Brain;

use App\Services\Bots\BetSizeMatrix;
use App\Services\Bots\BetTier;
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
        private BetSizeMatrix $bets,
        private ClearCallGuard $clearCall = new ClearCallGuard(),
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
        // Los bots que juegan (Pool) deciden el preflop con su tabla; en el resto de calles y situaciones mandan las reglas del cerebro.
        if ($strategy) {
            $matrix->mix($strategy['distribution']);
        } else {
            $this->rules->apply($brain['rules'] ?? [], $features, $matrix, $rng, $fired);
        }

        // Cuando manda la tabla preflop del perfil (calculada con SUS estadísticas) la tabla ES la decisión: ni la presión de los rivales ni la
        // imprecisión la tocan. Antes movían de 1 a 14 puntos a errores (call/fold/bet_pot/all-in) y hasta 9 puntos a fold, y el bot jugaba
        // manos que su propia matriz no recoge (p. ej. un 3-bet «bet_pot» con 43o desde la ciega grande o un fold de 87o desde el botón abriendo al 100 %).
        $danger = $strategy
            ? ['operator_pct' => 0.0, 'moved_pct' => 0.0, 'players' => []]
            : $this->preflopDanger->apply($matrix, $ctx, $features, $ctx['me']['stats'] ?? []); // 4) presión de los rivales que aún pueden actuar

        // Una mano con mucha más equity que la que pide el precio no se tira: ni por las estadísticas ni por un despiste (ver ClearCallGuard)
        $guarded = ! $strategy && $this->clearCall->apply($matrix, $features);
        if ($guarded) {
            $fired[] = ['id' => 'guard_clear_call', 'depth' => 0];
        }

        // 5) Imprecisión de la habilidad
        $lapse = $strategy ? 0.0 : $this->applyImprecision($matrix, $brain['imprecision'] ?? [], $guarded);

        // 6) Sorteo: un único número de 0 a 100 con dos decimales
        $distribution = $matrix->distribution();
        $roll = $rng->getInt(0, 9999) / 100;
        $cell = $matrix->pick($roll);

        // Tamaño de la apuesta según la matriz de su perfil (solo al abrir la acción postflop, que es lo que mide la matriz)
        $sizing = $this->betSizing($cell, $ctx, $rng);

        // 6) Traducción a una acción legal
        $resolved = $this->catalog->resolve($cell, [
            'size_fraction' => $sizing['fraction'] ?? null,
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
                'bet_sizing' => $sizing,
                'resolved' => ['action' => $resolved['action'], 'amount' => $resolved['amount'], 'tag' => $resolved['tag']],
            ],
        ];
    }

    /**
     * Tamaño de una apuesta con la matriz de apuestas del perfil (de PokerTracker): según el tipo de mano real del bot
     * (aire / pareja / fuerte) y la calle, sortea un tramo de tamaño. Solo para bots con datos propios y cuando abre la acción.
     *
     * @return array{tier: string, street: string, fraction: float}|null
     */
    private function betSizing(string $cell, array $ctx, Randomizer $rng): ?array
    {
        $street = (string) ($ctx['street'] ?? '');
        $botId = $ctx['me']['bot_id'] ?? null;
        if (! $botId || ! in_array($cell, ActionCatalog::BET_ACTIONS, true) || ! isset(BetTier::STREETS[$street])
            || (float) $ctx['to_call'] > 0 || ($ctx['legal']['raise'] ?? null) === null) {
            return null;
        }
        $board = (array) ($ctx['board'] ?? []);
        $hole = (array) ($ctx['me']['hole_cards'] ?? []);
        if (count($board) !== BetTier::STREETS[$street] || count($hole) !== 2) {
            return null;
        }
        $matrix = $this->bets->forId((int) $botId, true);
        if (! $matrix) {
            return null;
        }
        $tier = BetTier::tier($hole, $board);
        $fraction = $this->bets->sampleFraction($matrix, $street, $tier, $rng->getInt(0, 9999) / 10000);

        return $fraction === null ? null : ['tier' => $tier, 'street' => $street, 'fraction' => $fraction];
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
        // Los bots que juegan (Pool) deciden el preflop con su tabla; en el resto de calles y situaciones mandan las reglas del cerebro.
        if ($strategy) {
            $matrix->mix($strategy['distribution']);
        } else {
            $this->rules->apply($brain['rules'] ?? [], $perception['features'], $matrix, $rng, $fired);
        }
        $danger = $strategy
            ? ['operator_pct' => 0.0, 'moved_pct' => 0.0, 'players' => []]
            : $this->preflopDanger->apply($matrix, $ctx, $perception['features'], $ctx['me']['stats'] ?? []);
        if (! $strategy) {
            $guarded = $this->clearCall->apply($matrix, $perception['features']);
            if ($guarded) {
                $fired[] = ['id' => 'guard_clear_call', 'depth' => 0];
            }
            $this->applyImprecision($matrix, $brain['imprecision'] ?? [], $guarded);
        }

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
    private function applyImprecision(DecisionMatrix $matrix, array $cfg, bool $noFolds = false): float
    {
        $rate = (float) ($cfg['rate'] ?? 0);
        $mistakes = (array) ($cfg['mistakes'] ?? []);
        if ($noFolds) {
            unset($mistakes['fold']);   // una mano que no se tira (ClearCallGuard) tampoco se tira por un despiste
        }
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
