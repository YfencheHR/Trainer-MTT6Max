<?php

namespace App\Services\Ai;

use App\Models\Bot;
use App\Services\Bots\Brain\Perceiver;
use App\Services\Bots\Brain\PreflopStrategyMatrix;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Lo que el perfil del bot hace preflop en la situación de la decisión: el objetivo que se le da a la IA (como el `target` del flop).
 *
 * Sale de las tablas preflop del bot (las mismas con las que deciden las reglas) y tiene dos niveles:
 *   - spot: el reparto con TODAS las manos con las que llega a esta situación (según lo que ya hizo en la mano), en %
 *   - hand: el reparto de la mano exacta que tiene
 * Cada nivel agrupa las acciones en subir (raise), igualar (call) y foldear (fold; check si es gratis).
 * Solo hay objetivo de los bots con tablas (perfiles de la importación): el resto devuelve null y la IA decide solo con el perfil.
 */
class PreflopTargets
{
    private const RANKS = 'AKQJT98765432';

    public function __construct(
        private PreflopStrategyMatrix $matrix,
        private Perceiver $perceiver,
        private FlopRanges $ranges,
    ) {
    }

    /** @return array{spot: array<string, float>, hand: array<string, float>}|null */
    public function forDecision(array $ctx): ?array
    {
        // Sin el filtro de archivados: en torneos nuevos el bot es una copia congelada (archivada) del perfil
        $bot = isset($ctx['me']['bot_id']) ? Bot::withoutGlobalScope('active')->find($ctx['me']['bot_id']) : null;
        if (! $bot?->isPool()) {
            return null;
        }
        $features = $this->perceiver->perceive($ctx, [], new Randomizer(new Mt19937(1)))['features'];
        $free = ! empty($ctx['legal']['check']);

        $hand = $this->group($this->matrix->forPoolDecision($bot, $features)['distribution'], $free);
        // Con qué manos llega a esta situación: todas si todavía no ha actuado y, si ya actuó (abrió, limpeó), las de su línea
        $line = $this->ranges->forHand($ctx)[$ctx['me']['id']] ?? null;
        $totals = [];
        $weights = 0.0;
        foreach ($this->hands() as $code) {
            $weight = $this->combos($code) * ($line === null ? 1.0 : (float) ($line[$code] ?? 0));
            if ($weight <= 0) {
                continue;
            }
            $distribution = $this->matrix->forPoolDecision($bot, ['hand_code' => $code] + $features)['distribution'];
            foreach ($this->group($distribution, $free) as $action => $percent) {
                $totals[$action] = ($totals[$action] ?? 0.0) + $weight * $percent;
            }
            $weights += $weight;
        }
        if ($weights <= 0) {
            return null;
        }

        return [
            'spot' => array_map(fn ($sum) => round($sum / $weights, 1), $totals),
            'hand' => $hand,
        ];
    }

    /**
     * Agrupa un reparto del motor (open_*, raise_*, allin… en %) en raise, call y fold.
     *
     * @return array<string, float>
     */
    public function group(array $distribution, bool $free = false): array
    {
        $out = [];
        foreach ($distribution as $action => $percent) {
            $key = match (true) {
                $action === 'call' => 'call',
                $action === 'fold', $action === 'check' => $free ? 'check' : 'fold',
                default => 'raise',
            };
            $out[$key] = round(($out[$key] ?? 0.0) + (float) $percent, 1);
        }

        return $out;
    }

    /** @return list<string> las 169 manos iniciales (AA, AKs, AKo…) */
    private function hands(): array
    {
        $ranks = str_split(self::RANKS);
        $hands = [];
        foreach ($ranks as $row => $high) {
            foreach ($ranks as $column => $low) {
                $hands[] = match (true) {
                    $row === $column => $high.$low,
                    $row < $column => $high.$low.'s',
                    default => $low.$high.'o',
                };
            }
        }

        return $hands;
    }

    private function combos(string $hand): int
    {
        return strlen($hand) === 2 ? 6 : (str_ends_with($hand, 's') ? 4 : 12);
    }
}
