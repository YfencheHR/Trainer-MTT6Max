<?php

namespace App\Services\Equity;

use App\Models\HandGroup;
use App\Models\HandGroupValue;
use App\Services\Bots\Brain\PreflopRanking;
use App\Services\Ev\EquityCalculator;
use App\Services\Ev\FastHandEvaluator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Genera un prior heurístico chipEV para abrir sin acción previa y defender una subida.
 * No es una solución GTO/Nash ni modela ICM, antes o acción postflop.
 */
class HeuristicPreflopStrategyGenerator
{
    private const POSITIONS = ['UTG', 'HJ', 'CO', 'BTN', 'SB', 'BB'];

    private const STACK_BUCKETS = [
        ['min' => 0, 'max' => 6, 'depth' => 3, 'label' => '0-6'],
        ['min' => 6, 'max' => 25, 'depth' => 15, 'label' => '6-25'],
        ['min' => 25, 'max' => 50, 'depth' => 37, 'label' => '25-50'],
        ['min' => 50, 'max' => 100, 'depth' => 75, 'label' => '50-100'],
        ['min' => 100, 'max' => 255, 'depth' => 150, 'label' => '100+'],
    ];

    private const OPEN_CUTOFFS = [
        '0-6' => ['UTG' => 12, 'HJ' => 16, 'CO' => 23, 'BTN' => 38, 'SB' => 44, 'BB' => 0],
        '6-25' => ['UTG' => 13, 'HJ' => 18, 'CO' => 27, 'BTN' => 42, 'SB' => 46, 'BB' => 0],
        '25-50' => ['UTG' => 15, 'HJ' => 21, 'CO' => 31, 'BTN' => 47, 'SB' => 48, 'BB' => 0],
        '50-100' => ['UTG' => 16, 'HJ' => 22, 'CO' => 33, 'BTN' => 49, 'SB' => 49, 'BB' => 0],
        '100+' => ['UTG' => 17, 'HJ' => 23, 'CO' => 34, 'BTN' => 50, 'SB' => 50, 'BB' => 0],
    ];

    private const CONTINUE_CUTOFFS = [
        '0-6' => ['UTG' => 24, 'HJ' => 29, 'CO' => 37, 'BTN' => 50, 'SB' => 53, 'BB' => 66],
        '6-25' => ['UTG' => 22, 'HJ' => 28, 'CO' => 38, 'BTN' => 53, 'SB' => 55, 'BB' => 69],
        '25-50' => ['UTG' => 21, 'HJ' => 27, 'CO' => 37, 'BTN' => 52, 'SB' => 54, 'BB' => 68],
        '50-100' => ['UTG' => 20, 'HJ' => 26, 'CO' => 36, 'BTN' => 51, 'SB' => 53, 'BB' => 67],
        '100+' => ['UTG' => 19, 'HJ' => 25, 'CO' => 35, 'BTN' => 50, 'SB' => 52, 'BB' => 66],
    ];

    private const THREE_BET_CUTOFFS = [
        '0-6' => ['UTG' => 5, 'HJ' => 7, 'CO' => 10, 'BTN' => 15, 'SB' => 17, 'BB' => 20],
        '6-25' => ['UTG' => 5, 'HJ' => 7, 'CO' => 10, 'BTN' => 14, 'SB' => 16, 'BB' => 19],
        '25-50' => ['UTG' => 5, 'HJ' => 7, 'CO' => 10, 'BTN' => 13, 'SB' => 15, 'BB' => 18],
        '50-100' => ['UTG' => 5, 'HJ' => 7, 'CO' => 10, 'BTN' => 13, 'SB' => 15, 'BB' => 18],
        '100+' => ['UTG' => 5, 'HJ' => 7, 'CO' => 10, 'BTN' => 13, 'SB' => 15, 'BB' => 18],
    ];

    private const FOLD_EQUITY = ['UTG' => 0.38, 'HJ' => 0.40, 'CO' => 0.43, 'BTN' => 0.46, 'SB' => 0.37, 'BB' => 0.32];

    private const EQUITY_SAMPLES = 50;

    public function __construct(
        private EquityCalculator $equity,
        private PreflopRanking $ranking,
    ) {}

    /**
     * @param  Collection<int, HandGroup>|null  $hands  Limit generation to selected hands (for tests/tools).
     * @return array{created:int,updated:int,preserved:int,simulations:int}
     */
    public function generate(bool $force = false, ?Collection $hands = null): array
    {
        $hands ??= HandGroup::query()->orderBy('id')->get();
        $handRows = [];
        $cells = [];
        $openRange = $this->ranking->rangeCombos(25);
        $defenseRange = $this->ranking->rangeCombos(18);

        foreach ($hands as $hand) {
            $hero = $this->canonicalCombo($hand->group_name);
            if (! $hero) {
                continue;
            }
            $code = $hand->group_name;
            $secondSuit = ($code[2] ?? '') === 's' ? '♠' : '♥';
            $handRank = $this->ranking->percentile([$code[0].'♠', $code[1].$secondSuit]);

            $openEquity = $this->equity->equityVsRanges(
                $hero,
                [$this->unblockedRange($openRange, $hero)],
                [],
                self::EQUITY_SAMPLES,
            );
            $defenseEquity = $this->equity->equityVsRanges(
                $hero,
                [$this->unblockedRange($defenseRange, $hero)],
                [],
                self::EQUITY_SAMPLES,
            );

            foreach (self::STACK_BUCKETS as $bucket) {
                foreach (self::POSITIONS as $position) {
                    $cells[] = $this->openCell($hand->id, $bucket, $position, $openEquity, $handRank);
                    $cells[] = $this->defenseCell($hand->id, $bucket, $position, $defenseEquity, $handRank);
                }
            }
            $handRows[] = $hand->id;
        }

        $existing = HandGroupValue::query()
            ->whereIn('scenario', ['Opener', 'Defense'])
            ->get()
            ->filter(fn (HandGroupValue $value) => count($value->positions ?? []) === 1)
            ->keyBy(fn (HandGroupValue $value) => $this->cellKey(
                $value->hand_group_id,
                $value->scenario,
                $value->positions[0],
                $value->stack_depth_min,
                $value->stack_depth_max,
            ));

        $inserts = [];
        $updates = [];
        $preserved = 0;
        $timestamp = now();

        foreach ($cells as $cell) {
            $key = $this->cellKey(
                $cell['hand_group_id'],
                $cell['scenario'],
                $cell['position'],
                $cell['stack_depth_min'],
                $cell['stack_depth_max'],
            );
            $value = $existing->get($key);

            if ($value && ! $force && $value->actions) {
                $preserved++;

                continue;
            }

            $row = [
                'hand_group_id' => $cell['hand_group_id'],
                'scenario' => $cell['scenario'],
                'positions' => json_encode([$cell['position']], JSON_THROW_ON_ERROR),
                'stack_depth_min' => $cell['stack_depth_min'],
                'stack_depth_max' => $cell['stack_depth_max'],
                'ev' => $cell['ev'],
                'actions' => json_encode($cell['actions'], JSON_THROW_ON_ERROR),
                'updated_at' => $timestamp,
            ];

            if ($value) {
                $row['id'] = $value->id;
                $updates[] = $row;
            } else {
                $row['created_at'] = $timestamp;
                $inserts[] = $row;
            }
        }

        DB::transaction(function () use ($inserts, $updates) {
            foreach (array_chunk($inserts, 500) as $chunk) {
                DB::table('hand_group_values')->insert($chunk);
            }
            foreach (array_chunk($updates, 500) as $chunk) {
                DB::table('hand_group_values')->upsert($chunk, ['id'], [
                    'ev',
                    'actions',
                    'updated_at',
                ]);
            }
        });

        return [
            'created' => count($inserts),
            'updated' => count($updates),
            'preserved' => $preserved,
            'simulations' => count($handRows) * 2 * self::EQUITY_SAMPLES,
        ];
    }

    private function openCell(int $handId, array $bucket, string $position, float $equity, float $handRank): array
    {
        $cutoff = self::OPEN_CUTOFFS[$bucket['label']][$position];
        $openFrequency = $this->frequencyBelowRank($handRank, $cutoff, 8);
        $action = $bucket['max'] <= 6 ? 'allin' : 'open_2_5bb';

        if ($position === 'BB') {
            $actions = ['check' => ['frequency_pct' => 100.0, 'ev_bb' => 0.0]];
        } else {
            $risk = $action === 'allin' ? max(1.0, (float) $bucket['depth']) : 2.5;
            $ev = $this->raiseEv($equity, self::FOLD_EQUITY[$position], 1.5, $risk, $risk);
            $actions = [
                'fold' => ['frequency_pct' => round(100 - $openFrequency, 2), 'ev_bb' => 0.0],
                $action => [
                    'frequency_pct' => round($openFrequency, 2),
                    'ev_bb' => round($ev, 3),
                    'size_bb' => round($risk, 2),
                ],
            ];
        }

        return $this->cell($handId, 'Opener', $bucket, $position, $actions);
    }

    private function defenseCell(int $handId, array $bucket, string $position, float $equity, float $handRank): array
    {
        $label = $bucket['label'];
        $continue = $this->frequencyBelowRank($handRank, self::CONTINUE_CUTOFFS[$label][$position], 10);
        $raise = min(60.0, $this->frequencyBelowRank($handRank, self::THREE_BET_CUTOFFS[$label][$position], 4) * 0.6);
        $raise = min($continue, $raise);
        $call = max(0.0, $continue - $raise);
        $fold = max(0.0, 100 - $continue);
        $raiseAction = $bucket['max'] <= 6 ? 'allin' : 'raise_x3';
        $callCost = min(2.5, max(0.5, (float) $bucket['depth']));
        $raiseRisk = $bucket['max'] <= 6 ? max(1.0, (float) $bucket['depth']) : 7.5;
        $callEv = $equity * (4.0 + $callCost) - $callCost;
        $raiseEv = $this->raiseEv($equity, self::FOLD_EQUITY[$position], 4.0, $raiseRisk, min($raiseRisk, 5.0));

        $actions = [
            'fold' => ['frequency_pct' => round($fold, 2), 'ev_bb' => 0.0],
            'call' => ['frequency_pct' => round($call, 2), 'ev_bb' => round($callEv, 3), 'size_bb' => round($callCost, 2)],
            $raiseAction => [
                'frequency_pct' => round($raise, 2),
                'ev_bb' => round($raiseEv, 3),
                'size_bb' => round($raiseRisk, 2),
            ],
        ];
        $actions = array_filter($actions, fn (array $action) => $action['frequency_pct'] > 0);

        return $this->cell($handId, 'Defense', $bucket, $position, $actions);
    }

    private function cell(int $handId, string $scenario, array $bucket, string $position, array $actions): array
    {
        $actions = array_filter($actions, fn (array $action) => $action['frequency_pct'] > 0);
        $total = array_sum(array_column($actions, 'frequency_pct'));
        if ($total <= 0) {
            throw new \LogicException("La estrategia {$scenario}/{$position}/{$bucket['label']} no tiene frecuencias.");
        }

        foreach ($actions as &$action) {
            $action['frequency_pct'] = round($action['frequency_pct'] * 100 / $total, 2);
        }
        unset($action);
        $lastAction = array_key_last($actions);
        $roundedTotal = array_sum(array_column($actions, 'frequency_pct'));
        $actions[$lastAction]['frequency_pct'] = round(
            $actions[$lastAction]['frequency_pct'] + 100 - $roundedTotal,
            2,
        );

        $ev = 0.0;
        foreach ($actions as $action) {
            $ev += $action['frequency_pct'] / 100 * $action['ev_bb'];
        }

        return [
            'hand_group_id' => $handId,
            'scenario' => $scenario,
            'position' => $position,
            'stack_depth_min' => $bucket['min'],
            'stack_depth_max' => $bucket['max'],
            'ev' => round($ev, 2),
            'actions' => $actions,
        ];
    }

    private function raiseEv(float $equity, float $foldEquity, float $pot, float $risk, float $opponentCall): float
    {
        $calledPot = $pot + $risk + $opponentCall;

        return $foldEquity * $pot + (1 - $foldEquity) * ($equity * $calledPot - $risk);
    }

    private function frequencyBelowRank(float $rank, float $cutoff, float $width): float
    {
        if ($cutoff <= 0) {
            return 0.0;
        }

        $lower = max(0.0, $cutoff - $width / 2);
        $upper = min(100.0, $cutoff + $width / 2);
        if ($rank <= $lower) {
            return 100.0;
        }
        if ($rank >= $upper) {
            return 0.0;
        }

        return ($upper - $rank) / max(0.001, $upper - $lower) * 100;
    }

    private function canonicalCombo(string $code): ?array
    {
        $ranks = FastHandEvaluator::RANKS;
        $high = strpos($ranks, $code[0] ?? '');
        $low = strpos($ranks, $code[1] ?? '');
        if ($high === false || $low === false) {
            return null;
        }
        if ($high === $low) {
            return [$high, 13 + $low];
        }
        if (($code[2] ?? '') === 's') {
            return [$high, $low];
        }

        return [$high, 13 + $low];
    }

    private function unblockedRange(array $range, array $hero): array
    {
        $dead = array_fill_keys($hero, true);

        return array_values(array_filter(
            $range,
            fn (array $combo) => ! isset($dead[$combo[0]]) && ! isset($dead[$combo[1]])
        ));
    }

    private function cellKey(int $handId, string $scenario, string $position, int $min, int $max): string
    {
        return implode(':', [$handId, $scenario, $position, $min, $max]);
    }
}
