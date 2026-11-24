<?php

namespace App\Services\Ai;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Contrato entre el motor de Laravel y la IA que decide por los bots.
 *
 * El bot es un PERFIL: la IA decide con el estado de la mano + las estadísticas por situación del jugador (su personalidad) + las de los
 * rivales. No ve las reglas ni su resultado, y no se espera que acierte siempre: decide como lo haría ese perfil.
 *
 *   ENTRADA  → un «estado» (array JSON): solo lo que el bot puede saber, con cantidades en ciegas grandes.
 *   SALIDA   → una distribución de probabilidad sobre ACTIONS (suma 1), no una acción única.
 *   SORTEO   → lo hace Laravel con la semilla de la decisión (`sample`), como hoy: la misma semilla + el mismo estado
 *              + el mismo modelo reproducen la decisión, y la IA nunca recibe ni decide el azar.
 *
 * El mismo estado se construye de dos orígenes, para que lo aprendido en las manos reales sirva en el simulador:
 *   - fromBotContext()  → una decisión del simulador (HandPlayService::botContext)
 *   - fromHistory()     → una acción de una mano real leída de PokerTracker (HandHistoryParser)
 */
class DecisionContract
{
    public const VERSION = 1;

    /** Espacio de acciones: el mismo vocabulario que ya usa el motor (ActionCatalog), sin las especiales. */
    public const ACTIONS = [
        'fold', 'check', 'call',
        'open_2bb', 'open_2_5bb', 'open_3bb', 'raise_x2_2', 'raise_x3', 'raise_x4',
        'bet_third', 'bet_half', 'bet_twothirds', 'bet_pot', 'bet_overbet',
        'allin',
    ];

    private const POSTFLOP_FRACTION = ['bet_third' => 0.33, 'bet_half' => 0.5, 'bet_twothirds' => 0.66, 'bet_pot' => 1.0, 'bet_overbet' => 1.5];
    private const OPEN_BB = ['open_2bb' => 2.0, 'open_2_5bb' => 2.5, 'open_3bb' => 3.0];
    private const RAISE_MULT = ['raise_x2_2' => 2.2, 'raise_x3' => 3.0, 'raise_x4' => 4.0];

    /**
     * Estado a partir de una decisión del simulador.
     *
     * @param  array<int, array<string, float>>  $ranges  rango con el que cada jugador llega a la calle (FlopRanges): [id => [mano => peso]]
     */
    public function fromBotContext(array $ctx, array $ranges = []): array
    {
        $bb = max(1, (int) $ctx['big_blind']);
        $me = $ctx['me'];
        $rivals = [];
        foreach ($ctx['players'] as $p) {
            if ($p['id'] === $me['id'] || ! empty($p['folded'])) {
                continue;
            }
            $rivals[] = [
                'position' => $p['position'], 'stack_bb' => round($p['stack'] / $bb, 1), 'bet_bb' => round(($p['bet'] ?? 0) / $bb, 1),
                'all_in' => ! empty($p['all_in']),
                'profile' => $this->profile($p),
            ] + (isset($ranges[$p['id']]) ? ['range' => $ranges[$p['id']]] : []);
        }
        $mine = collect($ctx['players'])->firstWhere('id', $me['id']) ?? [];

        return $this->state([
            'street' => $ctx['street'],
            'position' => $me['position'],
            'hole_cards' => $me['hole_cards'],
            'board' => $ctx['board'] ?? [],
            'pot_bb' => $ctx['pot'] / $bb,
            'to_call_bb' => min($ctx['to_call'], $me['stack']) / $bb, // si no le llega el stack, solo puede igualar lo que tiene
            'stack_bb' => $me['stack'] / $bb,
            'players_left' => 1 + count($rivals),
            'raises_this_street' => $this->raisesThisStreet($ctx['history'] ?? [], $ctx['street']),
            'in_position' => $this->inPosition($me['position'], array_column($rivals, 'position'), count($ctx['players']) === 2),
            'history' => $this->compactHistory($ctx['history'] ?? [], array_column($ctx['players'], 'position', 'id'), $bb),
            'rivals' => $rivals,
            'profile' => $this->profile($mine + ['preflop_stats' => $me['stats'] ?? []]),
        ] + (isset($ranges[$me['id']]) ? ['range' => $ranges[$me['id']]] : []));
    }

    /**
     * Acciones legales → vocabulario permitido ahora. Postflop: fold/call ante una apuesta, check si es gratis, y las apuestas
     * por fracción del bote y el all-in cuando se puede subir.
     *
     * @return list<string>
     */
    public function allowedActions(array $legal, string $street, int $raises = 0): array
    {
        $out = [];
        if (! empty($legal['fold'])) {
            $out[] = 'fold';
        }
        if (! empty($legal['check'])) {
            $out[] = 'check';
        }
        if (($legal['call'] ?? null) !== null) {
            $out[] = 'call';
        }
        if (($legal['raise'] ?? null) !== null) {
            // Preflop: abrir a un tamaño fijo si nadie ha subido, y resubir como múltiplo de la apuesta si ya hay una subida
            $out = array_merge($out, $street !== 'preflop' ? array_keys(self::POSTFLOP_FRACTION) : array_keys($raises > 0 ? self::RAISE_MULT : self::OPEN_BB));
        }
        if (($legal['allin'] ?? null) !== null) {
            $out[] = 'allin';
        }

        return $out;
    }

    /** Orden de actuación postflop: la ciega pequeña habla primero y el botón el último (en heads-up el botón es la «SB» y habla el último). */
    private function inPosition(string $me, array $rivals, bool $headsUp): bool
    {
        $rank = ['SB' => 0, 'BB' => 1, 'UTG' => 2, 'HJ' => 3, 'CO' => 4, 'BTN' => 5];
        if ($headsUp) {
            $rank['SB'] = 6;
        }

        return $rivals !== [] && ($rank[$me] ?? 0) > max(array_map(fn ($p) => $rank[$p] ?? 0, $rivals));
    }

    /** Historia de la mano para el modelo: [calle, posición, acción, importe en ciegas]. */
    private function compactHistory(array $history, array $positionById, int $bb): array
    {
        $opened = [];
        $out = [];
        foreach ($history as $h) {
            $street = $h['street'] ?? '';
            $action = $h['action'] ?? '';
            if (in_array($action, ['raise', 'bet', 'allin'], true)) {
                // El motor llama «raise» a toda subida; en las manos reales (PokerTracker) la primera apuesta postflop es un «bet»
                if ($action === 'raise' && $street !== 'preflop' && empty($opened[$street])) {
                    $action = 'bet';
                }
                $opened[$street] = true;
            }
            $out[] = [$street, $positionById[$h['player_id'] ?? 0] ?? '?', $action, round(((float) ($h['amount'] ?? 0)) / $bb, 1)];
        }

        return $out;
    }

    /** Calles que define el contrato (cuáles decide la IA lo dice config('ai.streets'), según los servicios que haya). */
    public function supports(array $state): bool
    {
        return in_array($state['street'], ['preflop', 'flop', 'turn', 'river'], true);
    }

    /**
     * Subidas y limpers que hay preflop en el historial del simulador (lo que necesita el catálogo de acciones para dimensionar una subida).
     *
     * @return array{raises: int, limpers: int}
     */
    public function preflopCounts(array $history, int $bigBlind): array
    {
        $top = max(1, $bigBlind);
        $raises = $limpers = 0;
        foreach ($history as $h) {
            if (($h['street'] ?? '') !== 'preflop') {
                continue;
            }
            $action = $h['action'] ?? '';
            $amount = (int) ($h['amount'] ?? 0);
            if (in_array($action, ['raise', 'bet'], true) || ($action === 'allin' && $amount > $top)) {
                $raises++;
                $top = $amount;
            } elseif ($action === 'call' && $raises === 0) {
                $limpers++;
            }
        }

        return ['raises' => $raises, 'limpers' => $limpers];
    }

    /**
     * La personalidad del jugador: sus estadísticas por situación (preflop y postflop). Es lo único que distingue a un bot de otro;
     * el modelo no recibe reglas ni la decisión de las reglas.
     */
    private function profile(array $player): array
    {
        return ['preflop' => $player['preflop_stats'] ?? $player['stats'] ?? [], 'postflop' => $player['postflop_stats'] ?? []];
    }

    /**
     * Estado + acción real de una mano de PokerTracker.
     *
     * @param  array  $hand    mano de HandHistoryParser::parse()
     * @param  int    $index   posición de la acción dentro de $hand['actions']
     * @return array{state: array, action: string}|null  null si el jugador no tiene cartas conocidas (el estado sería incompleto)
     */
    public function fromHistory(array $hand, int $index, array $profiles = []): ?array
    {
        $a = $hand['actions'][$index] ?? null;
        $me = $a ? collect($hand['players'])->firstWhere('name', $a['player']) : null;
        if (! $me || ! $me['hole_cards']) {
            return null;
        }
        $bb = max(1, (int) $hand['big_blind']);
        $left = array_filter($hand['actions'], fn ($x) => $x['order'] < $a['order']);
        $folded = array_column(array_filter($left, fn ($x) => $x['action'] === 'fold'), 'player');
        $rivals = [];
        foreach ($hand['players'] as $p) {
            if ($p['name'] !== $a['player'] && ! in_array($p['name'], $folded, true)) {
                $rivals[] = ['position' => $p['position'], 'stack_bb' => round($p['stack'] / $bb, 1), 'bet_bb' => 0.0, 'all_in' => false, 'profile' => $this->profile($profiles[$p['name']] ?? [])];
            }
        }

        $state = $this->state([
            'street' => $a['street'],
            'position' => $a['position'],
            'hole_cards' => $me['hole_cards'],
            'board' => $a['board'],
            'pot_bb' => $a['pot_before'] / $bb,
            'to_call_bb' => $a['to_call'] / $bb,
            'stack_bb' => $a['stack_before'] / $bb,
            'players_left' => $a['players_left'],
            'raises_this_street' => count(array_filter($left, fn ($x) => $x['street'] === $a['street'] && $x['action'] === 'raise')),
            'in_position' => $this->inPosition($a['position'], array_column($rivals, 'position'), count($hand['players']) === 2),
            // Como en el simulador: una apuesta o subida va con el total al que sube, y un call con lo que paga
            'history' => array_values(array_map(fn ($x) => [$x['street'], $x['position'], $x['action'],
                round((in_array($x['action'], ['raise', 'bet'], true) ? $x['committed_before'] + $x['amount'] : $x['amount']) / $bb, 1)], $left)),
            'rivals' => $rivals,
            'profile' => $this->profile($profiles[$a['player']] ?? []),
        ]);

        return ['state' => $state, 'action' => $this->actionLabel($a, $bb)];
    }

    /** Traduce una acción real (cantidad en fichas) a la acción del vocabulario más parecida. */
    public function actionLabel(array $a, int $bb): string
    {
        if ($a['action'] === 'fold' || $a['action'] === 'check') {
            return $a['action'];
        }
        if ($a['action'] === 'call') {
            return $a['all_in'] && $a['amount'] >= $a['stack_before'] ? 'allin' : 'call';
        }
        if ($a['all_in'] || $a['amount'] >= $a['stack_before']) {
            return 'allin';
        }

        if ($a['street'] === 'preflop') {
            if ($a['current_bet'] <= $bb) { // abrir (o aislar limpers)
                return $this->nearest(self::OPEN_BB, ($a['committed_before'] + $a['amount']) / $bb);
            }

            return $this->nearest(self::RAISE_MULT, ($a['committed_before'] + $a['amount']) / max(1, $a['current_bet']));
        }

        // Postflop: lo que sube por encima de igualar, como fracción del bote tras igualar
        $fraction = ($a['amount'] - $a['to_call']) / max(1, $a['pot_before'] + $a['to_call']);

        return $this->nearest(self::POSTFLOP_FRACTION, $fraction);
    }

    /** Sortea una acción de una distribución {acción: probabilidad} con la semilla de la decisión. */
    public function sample(array $distribution, int $seed): string
    {
        $dist = array_filter(array_intersect_key($distribution, array_flip(self::ACTIONS)), fn ($p) => $p > 0);
        if (! $dist) {
            throw new \InvalidArgumentException('La distribución no tiene ninguna acción válida');
        }
        $total = array_sum($dist);
        $roll = (new Randomizer(new Mt19937($seed)))->getInt(0, 999999) / 1000000 * $total;
        $acc = 0.0;
        foreach ($dist as $action => $p) {
            $acc += $p;
            if ($roll < $acc) {
                return $action;
            }
        }

        return array_key_last($dist);
    }

    /**
     * Quita de la distribución las acciones que no son legales ahora y la renormaliza.
     * `$legal` es el mismo array del motor: ['fold'=>bool, 'check'=>bool, 'call'=>?int, 'raise'=>?array].
     */
    public function restrictToLegal(array $distribution, array $legal): array
    {
        $allowed = [];
        foreach ($distribution as $action => $p) {
            $ok = match (true) {
                $action === 'fold' => ! empty($legal['fold']),
                $action === 'check' => ! empty($legal['check']),
                $action === 'call' => $legal['call'] !== null || ! empty($legal['check']),
                $action === 'allin' => ($legal['allin'] ?? $legal['raise'] ?? null) !== null,
                default => ($legal['raise'] ?? null) !== null,
            };
            if ($ok && $p > 0) {
                $allowed[$action] = $p;
            }
        }
        $sum = array_sum($allowed);
        if ($sum <= 0) {
            return [];
        }

        return array_map(fn ($p) => $p / $sum, $allowed);
    }

    private function state(array $s): array
    {
        foreach (['pot_bb', 'to_call_bb', 'stack_bb'] as $k) {
            $s[$k] = round($s[$k], 2);
        }

        return ['contract' => self::VERSION] + $s;
    }

    private function raisesThisStreet(array $history, string $street): int
    {
        return count(array_filter($history, fn ($h) => ($h['street'] ?? null) === $street && in_array($h['action'] ?? '', ['raise', 'bet', 'allin'], true)));
    }

    /** @param array<string,float> $table */
    private function nearest(array $table, float $value): string
    {
        $best = array_key_first($table);
        foreach ($table as $id => $v) {
            if (abs($v - $value) < abs($table[$best] - $value)) {
                $best = $id;
            }
        }

        return $best;
    }
}
