<?php

namespace App\Services\Bots\Brain;

use App\Models\HandGroup;
use App\Models\HandGroupValue;
use App\Models\Bot;
use App\Services\Bots\RivalPreflopTables;

/**
 * Usa las tablas persistidas por mano y posición para los bots Pool. Para los perfiles de referencia,
 * conserva la matriz compartida con profundidad de stack.
 *
 * En los perfiles de referencia, las frecuencias son el prior; los EV en bb inclinan ese prior de forma
 * acotada y las estadísticas del agresor ajustan la defensa cuando hay una muestra suficiente.
 */
class PreflopStrategyMatrix
{
    public const PRIOR_WEIGHT = 0.65;

    private const EV_TEMPERATURE_BB = 4.0;

    private const POSITIONS = ['UTG', 'HJ', 'CO', 'BTN', 'SB', 'BB'];

    public function __construct(private RivalPreflopTables $poolTables)
    {
    }

    public function forDecision(array $ctx, array $features): ?array
    {
        if (($features['street'] ?? null) !== 'preflop') {
            return null;
        }

        $bot = isset($ctx['me']['bot_id']) ? Bot::find($ctx['me']['bot_id']) : null;
        if ($bot?->isPool()) {
            return $this->forPoolDecision($bot, $features);
        }

        $raises = (int) ($features['preflop_raises'] ?? 0);
        if ($raises > 1) {
            return null;
        }
        if ($raises === 0 && (int) ($features['limpers'] ?? 0) > 0) {
            return null;
        }

        $position = $features['position'] ?? null;
        $stackBb = (float) ($features['effective_stack_bb'] ?? $features['stack_bb'] ?? 0);
        $handCode = $features['hand_code'] ?? null;
        if (! in_array($position, self::POSITIONS, true) || $stackBb <= 0 || ! $handCode) {
            return null;
        }

        $scenario = $raises === 0 ? 'Opener' : 'Defense';
        $hand = HandGroup::where('group_name', $handCode)->first();
        if (! $hand) {
            return null;
        }

        $values = HandGroupValue::query()
            ->where('hand_group_id', $hand->id)
            ->where('scenario', $scenario)
            ->where('stack_depth_min', '<=', min(255, $stackBb))
            ->where('stack_depth_max', '>=', min(255, $stackBb))
            ->whereNotNull('actions')
            ->whereJsonContains('positions', $position)
            ->get()
            ->filter(fn (HandGroupValue $value) => in_array($position, $value->positions ?? [], true))
            ->sort(function (HandGroupValue $left, HandGroupValue $right) {
                $positionCount = count($left->positions ?? []) <=> count($right->positions ?? []);
                if ($positionCount !== 0) {
                    return $positionCount;
                }

                $leftWidth = (int) $left->stack_depth_max - (int) $left->stack_depth_min;
                $rightWidth = (int) $right->stack_depth_max - (int) $right->stack_depth_min;

                return $leftWidth <=> $rightWidth;
            });

        $value = $values->first();
        $actions = is_array($value?->actions) ? $value->actions : [];
        $supportedActions = ActionCatalog::all();
        $usable = [];

        foreach ($actions as $action => $data) {
            if (! isset($supportedActions[$action]) || ! is_array($data)) {
                continue;
            }
            $frequency = (float) ($data['frequency_pct'] ?? 0);
            if ($frequency <= 0) {
                continue;
            }
            $usable[$action] = [
                'frequency_pct' => $frequency,
                'ev_bb' => is_numeric($data['ev_bb'] ?? null) ? (float) $data['ev_bb'] : 0.0,
            ];
        }

        if (! $usable) {
            return null;
        }

        $frequencyPrior = [];
        foreach ($usable as $action => $data) {
            $frequencyPrior[$action] = $data['frequency_pct'];
        }
        $frequencyPrior = $this->normalize($frequencyPrior);

        $maxEv = max(array_column($usable, 'ev_bb'));
        $evWeighted = [];
        foreach ($usable as $action => $data) {
            $evDifference = max(-8.0, min(0.0, $data['ev_bb'] - $maxEv));
            $evWeighted[$action] = $data['frequency_pct'] * exp($evDifference / self::EV_TEMPERATURE_BB);
        }

        $evDistribution = $this->normalize($evWeighted);
        [$distribution, $opponentAdjustment] = $this->adjustForOpponent($evDistribution, $features, $scenario);

        return [
            'strategy_value_id' => $value->id,
            'scenario' => $scenario,
            'hand_code' => $handCode,
            'position' => $position,
            'stack_bb' => round((float) ($features['stack_bb'] ?? $stackBb), 2),
            'effective_stack_bb' => round($stackBb, 2),
            'actions' => $usable,
            'frequency_prior' => $frequencyPrior,
            'ev_distribution' => $evDistribution,
            'distribution' => $distribution,
            'opponent_adjustment' => $opponentAdjustment,
            'prior_weight' => self::PRIOR_WEIGHT,
        ];
    }

    private function forPoolDecision(Bot $bot, array $features): array
    {
        $position = $features['position'] ?? '';
        $handCode = $features['hand_code'] ?? '';
        $table = in_array($position, self::POSITIONS, true) && $this->poolTables->hasHand($handCode)
            ? $this->poolTables->frequenciesFor($bot, $position, $handCode)
            : null;
        $raises = (int) ($features['preflop_raises'] ?? 0);
        $limpedByBot = ! empty($features['i_limped_preflop']);

        if (! $table) {
            $distribution = ['fold' => 100.0];
            $scenario = 'pool_missing_hand';
        } elseif ($raises === 0) {
            $attack = $table['attack'];
            $distribution = [
                'open_2_5bb' => $attack['open'],
                'call' => $attack['limp'],
                'fold' => max(0.0, 100.0 - $attack['open'] - $attack['limp']),
            ];
            $scenario = 'pool_attack';
        } elseif ($raises >= 2 && ! empty($features['i_raised_preflop'])) {
            $response = $table['attack']['open_response'];
            $distribution = [
                'raise_x3' => $response['reraise'],
                'call' => $response['call'],
                'fold' => $response['fold'],
            ];
            $scenario = 'pool_open_response';
        } elseif ($limpedByBot) {
            $response = $table['attack']['limp_response'];
            $distribution = [
                'raise_x3' => $response['raise'],
                'allin' => $response['push'],
                'fold' => max(0.0, 100.0 - $response['raise'] - $response['push']),
            ];
            $scenario = 'pool_limp_response';
        } elseif ($position === 'UTG') {
            $distribution = ['fold' => 100.0];
            $scenario = 'pool_no_utg_defense';
        } else {
            $defense = $table['defense'];
            $distribution = [
                'raise_x3' => $defense['three_bet'],
                'call' => $defense['call'],
                'fold' => $defense['fold'],
            ];
            $scenario = 'pool_defense';
        }

        return [
            'source' => 'pool_tables',
            'scenario' => $scenario,
            'hand_code' => $handCode,
            'position' => $position,
            'actions' => [],
            'distribution' => $this->normalize($distribution) ?: ['fold' => 100.0],
            'prior_weight' => 1.0,
            'opponent_adjustment' => null,
        ];
    }

    private function adjustForOpponent(array $distribution, array $features, string $scenario): array
    {
        $adjustment = null;
        if ($scenario === 'Defense' && (int) ($features['agg_hands'] ?? 0) >= 20 && is_numeric($features['agg_pfr'] ?? null)) {
            $confidence = min(1.0, (int) $features['agg_hands'] / 100);
            $pfrDelta = max(-1.0, min(1.0, ((float) $features['agg_pfr'] - 15.0) / 20.0)) * $confidence;

            foreach ($distribution as $action => &$weight) {
                if ($action === 'fold') {
                    $weight *= 1 - 0.35 * $pfrDelta;
                } elseif (in_array($action, ['call', 'check'], true)) {
                    $weight *= 1 + 0.2 * $pfrDelta;
                } else {
                    $weight *= 1 + 0.3 * $pfrDelta;
                }
            }
            unset($weight);
            $distribution = $this->normalize($distribution);
            $adjustment = [
                'aggressor_pfr' => (float) $features['agg_pfr'],
                'hands' => (int) $features['agg_hands'],
                'confidence' => round($confidence, 2),
                'pfr_delta' => round($pfrDelta, 3),
            ];
        }

        return [$distribution, $adjustment];
    }

    private function normalize(array $weights): array
    {
        $weights = array_filter($weights, fn ($weight) => is_numeric($weight) && $weight > 0);
        $total = array_sum($weights);
        if ($total <= 0) {
            return [];
        }

        return array_map(fn ($weight) => (float) $weight * 100 / $total, $weights);
    }
}
