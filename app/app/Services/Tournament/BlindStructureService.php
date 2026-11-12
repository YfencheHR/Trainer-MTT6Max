<?php

namespace App\Services\Tournament;

class BlindStructureService
{
    /**
     * @param int[] $antesByLevel Ante amount in chips for each level.
     * @return array{level:int,small_blind:int,big_blind:int,ante:int,hands_per_level:int}
     */
    public function forRound(
        int $baseBigBlind,
        string $speed,
        int $round,
        ?int $handsPerLevel = null,
        array $antesByLevel = [],
    ): array
    {
        $settings = config("blind-levels.{$speed}", config('blind-levels.tipica'));
        $baseBigBlind = max(2, $baseBigBlind);
        $round = max(1, $round);
        $handsPerLevel = max(1, $handsPerLevel ?? $settings['hands_per_level']);
        $level = intdiv($round - 1, $handsPerLevel) + 1;
        $multipliers = $settings['multipliers'];

        if ($level <= count($multipliers)) {
            $bigBlind = $baseBigBlind * $multipliers[$level - 1];
        } else {
            $bigBlind = $baseBigBlind * end($multipliers);
            for ($currentLevel = count($multipliers) + 1; $currentLevel <= $level; $currentLevel++) {
                $bigBlind = $this->roundToPracticalBlind(
                    $bigBlind * $settings['growth_factor'],
                    $baseBigBlind,
                );
            }
        }

        $bigBlind = max($baseBigBlind, (int) $bigBlind);
        $antesByLevel = array_values($antesByLevel);
        $ante = $antesByLevel
            ? (int) ($antesByLevel[min($level - 1, count($antesByLevel) - 1)] ?? 0)
            : 0;

        return [
            'level' => $level,
            'small_blind' => max(1, intdiv($bigBlind, 2)),
            'big_blind' => $bigBlind,
            'ante' => max(0, $ante),
            'hands_per_level' => $handsPerLevel,
        ];
    }

    private function roundToPracticalBlind(float $value, int $baseBigBlind): int
    {
        $step = max(1, (int) round($baseBigBlind / 10));

        return max($step, (int) round($value / $step) * $step);
    }
}
