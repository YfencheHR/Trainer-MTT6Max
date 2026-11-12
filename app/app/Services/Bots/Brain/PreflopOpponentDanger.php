<?php

namespace App\Services\Bots\Brain;

/**
 * Reduce proporcionalmente las acciones no-fold ante rivales agresivos que aún pueden actuar.
 */
class PreflopOpponentDanger
{
    private const ACTION_ORDER = ['UTG', 'HJ', 'CO', 'BTN', 'SB', 'BB'];
    private const SCORE_WEIGHTS = ['vpip' => 0.35, 'pfr' => 0.30, 'three_bet' => 0.35];
    private const MAX_OPERATOR = 40.0;
    private const THREAT_POINTS = 25.0;

    /**
     * @return array{operator_pct:float,moved_pct:float,players:array<int,array>}
     */
    public function apply(DecisionMatrix $matrix, array $ctx, array $features, array $ownStats): array
    {
        if (($features['street'] ?? null) !== 'preflop') {
            return ['operator_pct' => 0.0, 'moved_pct' => 0.0, 'players' => []];
        }

        $behind = $this->playersStillToAct($ctx, $features);
        $population = $this->population($ctx, $ownStats);
        $remaining = 1.0;
        $details = [];

        foreach ($behind as $player) {
            $stats = $this->statsFor($player);
            if ($stats === null) {
                continue;
            }

            $confidence = $this->confidence($stats, $player);
            if ($confidence <= 0) {
                continue;
            }

            $baseline = $this->baseline($population, (int) $player['id']);
            $score = $this->dangerScore($stats, $baseline) * $confidence;
            if ($score <= 0) {
                continue;
            }

            $playerThreat = min(1.0, $score);
            $remaining *= 1.0 - $playerThreat * self::THREAT_POINTS / 100.0;
            $details[] = [
                'id' => (int) $player['id'],
                'position' => $player['position'] ?? null,
                'vpip' => $stats['vpip'],
                'pfr' => $stats['pfr'],
                'three_bet' => $stats['three_bet'],
                'confidence' => round($confidence, 2),
                'score' => round($score, 3),
            ];
        }

        $operator = min(self::MAX_OPERATOR, (1.0 - $remaining) * 100.0);
        $moved = $matrix->add('fold', $operator);

        return [
            'operator_pct' => round($operator, 2),
            'moved_pct' => round($moved, 2),
            'players' => $details,
        ];
    }

    /**
     * @return array<int,array>
     */
    public function playersStillToAct(array $ctx, array $features): array
    {
        $myPosition = $features['position'] ?? $ctx['me']['position'] ?? null;
        $myOrder = array_search($myPosition, self::ACTION_ORDER, true);
        if ($myOrder === false) {
            return [];
        }

        $players = array_filter(
            $ctx['players'] ?? [],
            fn ($player) => ($player['id'] ?? null) !== ($ctx['me']['id'] ?? null)
                && empty($player['folded'])
                && empty($player['all_in'])
                && ($playerOrder = array_search($player['position'] ?? null, self::ACTION_ORDER, true)) !== false
                && $playerOrder > $myOrder
        );
        usort($players, fn ($left, $right) =>
            array_search($left['position'], self::ACTION_ORDER, true)
            <=> array_search($right['position'], self::ACTION_ORDER, true)
        );

        return array_values($players);
    }

    private function population(array $ctx, array $ownStats): array
    {
        $population = [];
        $own = $this->normalizeStats($ownStats);
        if ($own !== null) {
            $population[] = ['id' => (int) ($ctx['me']['id'] ?? 0), 'stats' => $own, 'confidence' => 1.0];
        }

        foreach ($ctx['players'] ?? [] as $player) {
            if (($player['id'] ?? null) === ($ctx['me']['id'] ?? null) || !empty($player['folded']) || !empty($player['all_in'])) {
                continue;
            }
            $stats = $this->statsFor($player);
            if ($stats === null) {
                continue;
            }
            $population[] = [
                'id' => (int) $player['id'],
                'stats' => $stats,
                'confidence' => $this->confidence($stats, $player),
            ];
        }

        return $population;
    }

    private function baseline(array $population, int $excludeId): array
    {
        $baseline = [];
        foreach (array_keys(self::SCORE_WEIGHTS) as $metric) {
            $weighted = 0.0;
            $weight = 0.0;
            foreach ($population as $entry) {
                if ($entry['id'] === $excludeId || !is_numeric($entry['stats'][$metric] ?? null)) {
                    continue;
                }
                $confidence = (float) $entry['confidence'];
                $weighted += (float) $entry['stats'][$metric] * $confidence;
                $weight += $confidence;
            }
            $baseline[$metric] = $weight > 0 ? $weighted / $weight : null;
        }

        return $baseline;
    }

    private function dangerScore(array $stats, array $baseline): float
    {
        $score = 0.0;
        $weight = 0.0;
        foreach (self::SCORE_WEIGHTS as $metric => $metricWeight) {
            if (!is_numeric($stats[$metric] ?? null) || !is_numeric($baseline[$metric] ?? null)) {
                continue;
            }

            $difference = match ($metric) {
                'vpip' => max(0.0, $baseline[$metric] - $stats[$metric]) / 30.0,
                'pfr' => max(0.0, $stats[$metric] - $baseline[$metric]) / 25.0,
                'three_bet' => max(0.0, $stats[$metric] - $baseline[$metric]) / 20.0,
            };
            $score += min(1.0, $difference) * $metricWeight;
            $weight += $metricWeight;
        }
        if ($weight <= 0) {
            return 0.0;
        }

        $score /= $weight;
        if (is_numeric($stats['vpip'] ?? null)) {
            $looseDiscount = max(0.0, 1.0 - max(0.0, (float) $stats['vpip'] - 35.0) / 45.0);
            $score *= $looseDiscount;
        }

        return min(1.0, $score);
    }

    private function statsFor(array $player): ?array
    {
        return $this->normalizeStats($player['preflop_stats'] ?? $player['stats'] ?? []);
    }

    private function normalizeStats(array $stats): ?array
    {
        $normalized = [];
        foreach (array_keys(self::SCORE_WEIGHTS) as $metric) {
            $normalized[$metric] = is_numeric($stats[$metric] ?? null)
                ? max(0.0, min(100.0, (float) $stats[$metric]))
                : null;
        }

        return count(array_filter($normalized, fn ($value) => $value !== null)) > 0 ? $normalized : null;
    }

    private function confidence(array $stats, array $player): float
    {
        if (($player['preflop_stats']['source'] ?? null) === 'profile') {
            return 1.0;
        }

        $hands = (int) ($stats['hands'] ?? $player['stats']['hands'] ?? 0);

        return min(1.0, max(0.0, $hands / 50.0));
    }
}
