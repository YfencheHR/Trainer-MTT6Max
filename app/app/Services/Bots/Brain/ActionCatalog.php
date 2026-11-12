<?php

namespace App\Services\Bots\Brain;

/**
 * Catálogo de acciones que pueden aparecer en las celdas de la matriz de decisión
 * y su traducción a acciones legales del motor (fold / check / call / raise / allin).
 *
 * Tres familias:
 *  - básicas:    fold, check, call, allin
 *  - tamaños:    bet_* (fracción del bote), open_* / raise_x* (preflop)
 *  - especiales: slowplay, check_raise, float_call, bluff_raise
 *                Solo existen en la matriz de un bot si alguna de SUS reglas las pinta,
 *                así que los jugadores con más habilidad las usan y los demás no.
 */
class ActionCatalog
{
    /** Fracción del bote (tras igualar) para apuestas / subidas postflop. */
    private const POT_FRACTIONS = [
        'bet_third' => 0.33,
        'bet_half' => 0.5,
        'bet_twothirds' => 0.66,
        'bet_pot' => 1.0,
        'bet_overbet' => 1.5,
    ];

    /** Subidas preflop: "open" en ciegas grandes; "x" = múltiplo de la apuesta actual. */
    private const OPEN_BB = ['open_2bb' => 2.0, 'open_2_5bb' => 2.5, 'open_3bb' => 3.0];
    private const RAISE_MULT = ['raise_x2_2' => 2.2, 'raise_x3' => 3.0, 'raise_x4' => 4.0];

    public const SPECIALS = ['slowplay', 'check_raise', 'float_call', 'bluff_raise'];

    /** @return array<string, array{label:string, family:string, special:bool}> */
    public static function all(): array
    {
        $out = [
            'fold' => ['label' => 'Fold (o check si es gratis)', 'family' => 'passive'],
            'check' => ['label' => 'Check (fold si hay apuesta)', 'family' => 'passive'],
            'call' => ['label' => 'Call (check si es gratis)', 'family' => 'passive'],
            'allin' => ['label' => 'All-in', 'family' => 'aggressive'],
        ];
        foreach (array_keys(self::POT_FRACTIONS) as $id) {
            $out[$id] = ['label' => 'Apuesta/subida ' . (self::POT_FRACTIONS[$id] * 100) . '% del bote', 'family' => 'aggressive'];
        }
        foreach (self::OPEN_BB as $id => $bb) {
            $out[$id] = ['label' => "Abrir a {$bb}bb (+1bb por limper)", 'family' => 'aggressive'];
        }
        foreach (self::RAISE_MULT as $id => $m) {
            $out[$id] = ['label' => "Resubir a x{$m} la apuesta", 'family' => 'aggressive'];
        }
        $out['slowplay'] = ['label' => 'Slowplay: check/call con mano fuerte', 'family' => 'special'];
        $out['check_raise'] = ['label' => 'Check-raise: check ahora y sube si apuestan', 'family' => 'special'];
        $out['float_call'] = ['label' => 'Float: call con intención de apostar después', 'family' => 'special'];
        $out['bluff_raise'] = ['label' => 'Farol: subida grande con mano débil', 'family' => 'special'];

        foreach ($out as $id => &$a) {
            $a['special'] = in_array($id, self::SPECIALS, true);
        }

        return $out;
    }

    public static function exists(string $id): bool
    {
        return isset(self::all()[$id]);
    }

    /**
     * Traduce una acción de la matriz a una acción legal del motor.
     *
     * @param array $ctx  legal, pot, to_call, current_bet, stack, bet (lo ya puesto en la calle),
     *                    big_blind, limpers, preflop_raises, flags (del jugador)
     * @return array{action:string, amount:int, tag:?string, flags:array, note:?string}
     */
    public function resolve(string $id, array $ctx): array
    {
        $legal = $ctx['legal'];
        $flags = $ctx['flags'] ?? [];
        $out = fn(string $a, int $amt = 0, ?string $tag = null, array $set = [], ?string $note = null) => [
            'action' => $a, 'amount' => $amt, 'tag' => $tag, 'flags' => $set, 'note' => $note,
        ];

        $passive = function (bool $callIfPossible) use ($legal, $out) {
            if ($legal['check']) {
                return $out('check');
            }
            if ($callIfPossible && $legal['call'] !== null) {
                return $out('call');
            }

            return $out('fold');
        };

        switch ($id) {
            case 'fold':
            case 'check':
                return $passive(false);
            case 'call':
                return $passive(true);
            case 'allin':
                if ($legal['allin'] !== null) {
                    return $out('allin');
                }

                return $passive(true);
            case 'slowplay':
                return $legal['check'] ? $out('check', 0, 'slowplay', ['slowplay' => true]) : ($legal['call'] !== null ? $out('call', 0, 'slowplay') : $out('fold'));
            case 'float_call':
                return $legal['check'] ? $out('check') : ($legal['call'] !== null ? $out('call', 0, 'float', ['float' => true]) : $out('fold'));
            case 'check_raise':
                if ($legal['check']) {
                    return $out('check', 0, 'check_raise', ['check_raise' => true]);
                }
                if (!empty($flags['check_raise'])) {
                    return $this->raise('bet_pot', $ctx, $out, 'check_raise');
                }

                return $passive(true);
            case 'bluff_raise':
                return $this->raise('bet_twothirds', $ctx, $out, 'bluff');
        }

        if (isset(self::POT_FRACTIONS[$id]) || isset(self::OPEN_BB[$id]) || isset(self::RAISE_MULT[$id])) {
            return $this->raise($id, $ctx, $out, null);
        }

        throw new \InvalidArgumentException("Acción desconocida: {$id}");
    }

    /** Subida/apuesta con el tamaño de la acción, ajustada a los límites legales. */
    private function raise(string $id, array $ctx, callable $out, ?string $tag): array
    {
        $legal = $ctx['legal'];
        if ($legal['raise'] === null) {
            // No puede subir: lo más parecido es igualar (o all-in si solo le queda eso)
            if ($legal['call'] !== null) {
                return $out('call', 0, $tag, [], 'no puede subir → call');
            }

            return $legal['check'] ? $out('check', 0, $tag, [], 'no puede subir → check') : $out('fold');
        }

        $bb = max(1, (int) $ctx['big_blind']);
        $pot = (float) $ctx['pot'];
        $toCall = (float) $ctx['to_call'];
        $current = (float) $ctx['current_bet'];

        if (isset(self::POT_FRACTIONS[$id])) {
            $sizeFactor = $this->clamp((float) ($ctx['bet_size_factor'] ?? 1.0), 0.8, 1.3);
            $target = $current + self::POT_FRACTIONS[$id] * $sizeFactor * ($pot + $toCall);
        } elseif (isset(self::OPEN_BB[$id])) {
            if ((int) ($ctx['preflop_raises'] ?? 0) > 0) {
                $target = $current * 3.0; // ya hay una subida: se interpreta como 3-bet
            } else {
                $target = $bb * self::OPEN_BB[$id] + $bb * (int) ($ctx['limpers'] ?? 0);
            }
        } else {
            $target = max($current, $bb) * self::RAISE_MULT[$id];
        }

        // Redondeo a medias ciegas
        $step = max(1, intdiv($bb, 2));
        $target = (int) (round($target / $step) * $step);

        $min = $legal['raise']['min'];
        $max = $legal['raise']['max'];
        $target = max($min, min($max, $target));

        // Si la apuesta compromete casi todo el stack, se va all-in
        $total = (int) $ctx['stack'] + (int) ($ctx['bet'] ?? 0);
        if ($target >= $max || $target >= 0.8 * $total) {
            return $out('allin', 0, $tag, [], 'tamaño compromete el stack → all-in');
        }

        return $out('raise', $target, $tag);
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
