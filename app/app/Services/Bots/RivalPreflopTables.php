<?php

namespace App\Services\Bots;

use App\Models\Bot;
use App\Models\BotPreflopHandRange;
use App\Services\Bots\Brain\BotProfile;
use App\Services\Bots\Brain\PreflopRanking;

class RivalPreflopTables
{
    private const RANKS = 'AKQJT98765432';
    private const DB_POSITION = ['HJ' => 'MP'];
    private const PRIOR_OPPORTUNITIES = 12;

    public function __construct(private PreflopRanking $ranking)
    {
    }

    public function hasHand(string $hand): bool
    {
        return in_array($hand, $this->hands(), true);
    }

    public function frequenciesFor(Bot $bot, string $position, string $hand): ?array
    {
        $range = $bot->preflopHandRanges()
            ->where('position', $position)
            ->where('hand', $hand)
            ->first();

        if (! $range) {
            $this->forBot($bot);
            $range = $bot->preflopHandRanges()
                ->where('position', $position)
                ->where('hand', $hand)
                ->first();
        }

        if (! $range) {
            return null;
        }

        return [
            'attack' => [
                'open' => $range->attack_open,
                'limp' => $range->attack_limp,
                'open_response' => [
                    'reraise' => $range->open_reraise,
                    'call' => $range->open_call,
                    'fold' => $range->open_fold,
                ],
                'limp_response' => [
                    'raise' => $range->limp_raise,
                    'push' => $range->limp_push,
                ],
            ],
            'defense' => [
                'three_bet' => $range->defense_three_bet,
                'call' => $range->defense_call,
                'fold' => $range->defense_fold,
            ],
        ];
    }

    public function forBot(Bot $bot): array
    {
        $profile = BotProfile::forBot($bot);
        $handStats = $bot->preflopHandStats()
            ->get()
            ->keyBy(fn($row) => $row->posicion . ':' . $row->mano);
        $storedRanges = $bot->preflopHandRanges()
            ->get()
            ->keyBy(fn($row) => $row->position . ':' . $row->hand);
        $tables = [];

        foreach (BotProfile::POSITIONS as $position) {
            $stats = $profile['positions'][$position];
            $global = $profile['global'];
            $hands = $this->hands();
            $percentiles = [];
            $weights = [];
            $samples = [];
            foreach ($hands as $hand) {
                $percentiles[$hand] = $this->ranking->percentileForCode($hand);
                $weights[$hand] = $this->combos($hand);
                $samples[$hand] = $handStats->get((self::DB_POSITION[$position] ?? $position) . ':' . $hand);
            }

            $width = max(4.0, min(10.0, 5.0 + max(0.0, $global['vpip'] - $global['pfr']) / 10));
            $openTarget = $position === 'BB' ? 0.0 : $stats['open'];
            $limpTarget = $position === 'BB' ? 0.0 : $stats['limp'];
            $trapPrior = $limpTarget > 0
                ? $this->trapPrior($bot, $global, $percentiles, $openTarget, $width)
                : array_fill_keys($hands, 0.0);

            $openPrior = $this->softTop($percentiles, $openTarget, $width, $weights);
            foreach ($hands as $hand) {
                $sample = $samples[$hand];
                $openPrior[$hand] = min(
                    100.0 - $trapPrior[$hand],
                    $this->blendObserved(
                        $openPrior[$hand],
                        (int) ($sample->open_raises ?? 0),
                        (int) ($sample->open_opportunities ?? 0)
                    )
                );
            }
            $openCaps = array_map(fn($freq) => max(0.0, 100.0 - $freq), $trapPrior);
            $open = $this->calibrate($openPrior, $openTarget, $weights, $openCaps);

            $limpPrior = $this->softBand($percentiles, $openTarget, $openTarget + $limpTarget, $width, $weights);
            foreach ($hands as $hand) {
                $sample = $samples[$hand];
                $limpPrior[$hand] = $this->blendObserved(
                    min(100.0, $limpPrior[$hand] + $trapPrior[$hand]),
                    (int) ($sample->limps ?? 0),
                    (int) ($sample->open_opportunities ?? 0)
                );
            }
            $limpCaps = array_map(fn($freq) => max(0.0, 100.0 - $freq), $open);
            $limp = $this->calibrate($limpPrior, $limpTarget, $weights, $limpCaps);

            $threeBetTarget = $position === 'UTG' ? 0.0 : $stats['vs_open']['three_bet'];
            $callTarget = $position === 'UTG' ? 0.0 : $stats['vs_open']['call'];
            $defenseTarget = max($threeBetTarget, $callTarget + $threeBetTarget);
            $threeBet = $this->softTop($percentiles, $threeBetTarget, $width, $weights);
            foreach ($hands as $hand) {
                $sample = $samples[$hand];
                $threeBet[$hand] = $this->blendObserved(
                    $threeBet[$hand],
                    (int) ($sample->three_bets ?? 0),
                    (int) ($sample->vs_open_opportunities ?? 0)
                );
            }
            $threeBet = $this->calibrate($threeBet, $threeBetTarget, $weights);

            $callPrior = $this->softBand($percentiles, $threeBetTarget, $defenseTarget, $width, $weights);
            $call = $this->calibrate($callPrior, $callTarget, $weights, array_map(fn($freq) => 100 - $freq, $threeBet));

            $response = $this->openResponses($stats, $hands, $percentiles, $weights, $open, $samples, $width);
            $limpResponse = $this->limpResponses($bot, $global, $stats['limp_response'], $hands, $percentiles, $weights, $limp, $samples, $width);

            $attack = [];
            $defense = [];
            foreach ($hands as $hand) {
                $attack[] = [
                    'hand' => $hand,
                    'percentile' => $percentiles[$hand],
                    'open' => $open[$hand],
                    'limp' => $limp[$hand],
                    'open_response' => [
                        'reraise' => $response['reraise'][$hand],
                        'call' => $response['call'][$hand],
                        'fold' => $response['fold'][$hand],
                    ],
                    'limp_response' => [
                        'raise' => $limpResponse['raise'][$hand],
                        'push' => $limpResponse['push'][$hand],
                    ],
                ];
                $defense[] = [
                    'hand' => $hand,
                    'percentile' => $percentiles[$hand],
                    'three_bet' => $threeBet[$hand],
                    'call' => $call[$hand],
                    'fold' => max(0.0, 100.0 - $threeBet[$hand] - $call[$hand]),
                ];
            }

            $tables[$position] = [
                'attack' => $attack,
                'defense' => $defense,
                'stats' => [
                    'open' => $this->weightedAverage($open, $weights),
                    'limp' => $this->weightedAverage($limp, $weights),
                    'three_bet' => $this->weightedAverage($threeBet, $weights),
                    'defense' => $this->weightedAverage($threeBet, $weights) + $this->weightedAverage($call, $weights),
                    'open_response' => [
                        'reraise' => $response['summary']['reraise'],
                        'call' => $response['summary']['call'],
                        'fold' => $response['summary']['fold'],
                    ],
                    'limp_response' => [
                        'raise' => $limpResponse['summary']['raise'],
                        'push' => $limpResponse['summary']['push'],
                    ],
                ],
            ];
        }

        $tables = $this->persistGeneratedRanges($bot, $tables, $storedRanges);

        return $tables;
    }

    private function persistGeneratedRanges(Bot $bot, array $tables, $storedRanges): array
    {
        $generatedRows = [];
        $now = now();

        foreach ($tables as $position => &$table) {
            foreach ($table['attack'] as $index => &$attack) {
                $defense = &$table['defense'][$index];
                $range = $storedRanges->get($position . ':' . $attack['hand']);
                if ($range && $range->is_manual) {
                    $attack['open'] = $range->attack_open;
                    $attack['limp'] = $range->attack_limp;
                    $attack['open_response'] = [
                        'reraise' => $range->open_reraise,
                        'call' => $range->open_call,
                        'fold' => $range->open_fold,
                    ];
                    $attack['limp_response'] = [
                        'raise' => $range->limp_raise,
                        'push' => $range->limp_push,
                    ];
                    $defense['three_bet'] = $range->defense_three_bet;
                    $defense['call'] = $range->defense_call;
                    $defense['fold'] = $range->defense_fold;
                    $attack['manual'] = $defense['manual'] = true;
                } else {
                    $row = [
                        'bot_id' => $bot->id,
                        'position' => $position,
                        'hand' => $attack['hand'],
                        'attack_open' => round($attack['open'], 2),
                        'attack_limp' => round($attack['limp'], 2),
                        'open_reraise' => round($attack['open_response']['reraise'], 2),
                        'open_call' => round($attack['open_response']['call'], 2),
                        'open_fold' => round($attack['open_response']['fold'], 2),
                        'limp_raise' => round($attack['limp_response']['raise'], 2),
                        'limp_push' => round($attack['limp_response']['push'], 2),
                        'defense_three_bet' => round($defense['three_bet'], 2),
                        'defense_call' => round($defense['call'], 2),
                        'defense_fold' => round($defense['fold'], 2),
                        'is_manual' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $changed = ! $range;
                    if ($range) {
                        foreach ([
                            'attack_open',
                            'attack_limp',
                            'open_reraise',
                            'open_call',
                            'open_fold',
                            'limp_raise',
                            'limp_push',
                            'defense_three_bet',
                            'defense_call',
                            'defense_fold',
                        ] as $field) {
                            if (round((float) $range->{$field}, 2) !== $row[$field]) {
                                $changed = true;
                                break;
                            }
                        }
                    }
                    if ($changed) {
                        $generatedRows[] = $row;
                    }
                    $attack['manual'] = $defense['manual'] = false;
                }
                unset($defense);
            }
            unset($attack);
            $table['stats'] = $this->summarize($table);
        }
        unset($table);

        foreach (array_chunk($generatedRows, 100) as $chunk) {
            BotPreflopHandRange::upsert(
                $chunk,
                ['bot_id', 'position', 'hand'],
                [
                    'attack_open',
                    'attack_limp',
                    'open_reraise',
                    'open_call',
                    'open_fold',
                    'limp_raise',
                    'limp_push',
                    'defense_three_bet',
                    'defense_call',
                    'defense_fold',
                    'is_manual',
                    'updated_at',
                ]
            );
        }

        return $tables;
    }

    private function summarize(array $table): array
    {
        $weights = [];
        $openWeights = [];
        $limpWeights = [];
        $open = $limp = $threeBet = $defense = [];
        $reraise = $openCall = $openFold = $limpRaise = $limpPush = [];

        foreach ($table['attack'] as $index => $attack) {
            $hand = $attack['hand'];
            $weights[$hand] = $this->combos($hand);
            $openWeights[$hand] = $weights[$hand] * $attack['open'];
            $limpWeights[$hand] = $weights[$hand] * $attack['limp'];
            $open[$hand] = $attack['open'];
            $limp[$hand] = $attack['limp'];
            $reraise[$hand] = $attack['open_response']['reraise'];
            $openCall[$hand] = $attack['open_response']['call'];
            $openFold[$hand] = $attack['open_response']['fold'];
            $limpRaise[$hand] = $attack['limp_response']['raise'];
            $limpPush[$hand] = $attack['limp_response']['push'];
            $threeBet[$hand] = $table['defense'][$index]['three_bet'];
            $defense[$hand] = $table['defense'][$index]['three_bet'] + $table['defense'][$index]['call'];
        }

        return [
            'open' => $this->weightedAverage($open, $weights),
            'limp' => $this->weightedAverage($limp, $weights),
            'three_bet' => $this->weightedAverage($threeBet, $weights),
            'defense' => $this->weightedAverage($defense, $weights),
            'open_response' => [
                'reraise' => $this->weightedAverage($reraise, $openWeights),
                'call' => $this->weightedAverage($openCall, $openWeights),
                'fold' => $this->weightedAverage($openFold, $openWeights),
            ],
            'limp_response' => [
                'raise' => $this->weightedAverage($limpRaise, $limpWeights),
                'push' => $this->weightedAverage($limpPush, $limpWeights),
            ],
        ];
    }

    private function openResponses(array $stats, array $hands, array $percentiles, array $weights, array $open, $samples, float $width): array
    {
        $targets = $stats['vs_3bet'];
        $responseWeights = [];
        foreach ($hands as $hand) {
            $responseWeights[$hand] = $weights[$hand] * $open[$hand];
        }
        if (array_sum($responseWeights) <= 0) {
            $zero = array_fill_keys($hands, 0.0);

            return ['reraise' => $zero, 'call' => $zero, 'fold' => $zero, 'summary' => ['reraise' => 0.0, 'call' => 0.0, 'fold' => 0.0]];
        }

        $reraise = $this->softTop($percentiles, $targets['reraise'], $width, $responseWeights);
        $fold = $this->softBottom($percentiles, $targets['fold'], $width, $responseWeights);
        foreach ($hands as $hand) {
            $sample = $samples[$hand];
            $opportunities = (int) ($sample->vs_3bet_opportunities ?? 0);
            if ($opportunities > 0) {
                $reraise[$hand] = $this->blendObserved($reraise[$hand], (int) $sample->open_reraises, $opportunities);
                $fold[$hand] = $this->blendObserved($fold[$hand], (int) $sample->open_folds, $opportunities);
            }
        }

        $reraise = $this->calibrate($reraise, $targets['reraise'], $responseWeights);
        $foldTarget = min($targets['fold'], 100.0 - $targets['reraise']);
        $fold = $this->calibrate($fold, $foldTarget, $responseWeights, array_map(fn($freq) => 100 - $freq, $reraise));
        $call = [];
        foreach ($hands as $hand) {
            $call[$hand] = max(0.0, 100.0 - $reraise[$hand] - $fold[$hand]);
        }

        return [
            'reraise' => $reraise,
            'call' => $call,
            'fold' => $fold,
            'summary' => [
                'reraise' => $this->weightedAverage($reraise, $responseWeights),
                'call' => $this->weightedAverage($call, $responseWeights),
                'fold' => $this->weightedAverage($fold, $responseWeights),
            ],
        ];
    }

    private function limpResponses(Bot $bot, array $global, array $targets, array $hands, array $percentiles, array $weights, array $limp, $samples, float $width): array
    {
        [$fallbackRaise, $fallbackPush] = $this->fallbackLimpResponse($bot, $global);
        $raiseTarget = $targets['raise'] ?? $fallbackRaise;
        $pushTarget = $targets['push'] ?? $fallbackPush;
        $responseWeights = [];
        foreach ($hands as $hand) {
            $responseWeights[$hand] = $weights[$hand] * $limp[$hand];
        }
        if (array_sum($responseWeights) <= 0) {
            $zero = array_fill_keys($hands, 0.0);

            return ['raise' => $zero, 'push' => $zero, 'summary' => ['raise' => 0.0, 'push' => 0.0]];
        }
        $raise = $this->softTop($percentiles, $raiseTarget, $width, $responseWeights);
        $push = $this->softTop($percentiles, $pushTarget, $width, $responseWeights);
        foreach ($hands as $hand) {
            $sample = $samples[$hand];
            $opportunities = (int) ($sample->limp_raise_opportunities ?? 0);
            if ($opportunities > 0) {
                $raise[$hand] = $this->blendObserved($raise[$hand], (int) $sample->limp_raises, $opportunities);
                $push[$hand] = $this->blendObserved($push[$hand], (int) $sample->limp_pushes, $opportunities);
            }
        }
        $raise = $this->calibrate($raise, $raiseTarget, $responseWeights);
        $push = $this->calibrate($push, $pushTarget, $responseWeights, array_map(fn($freq) => 100 - $freq, $raise));

        return [
            'raise' => $raise,
            'push' => $push,
            'summary' => [
                'raise' => $this->weightedAverage($raise, $responseWeights),
                'push' => $this->weightedAverage($push, $responseWeights),
            ],
        ];
    }

    private function fallbackLimpResponse(Bot $bot, array $global): array
    {
        $gap = max(0.0, $global['vpip'] - $global['pfr']);
        $base = match ($bot->templateTipo()) {
            'setminer', 'roca' => [1.0, 2.0],
            'nit' => [3.0, 3.0],
            'omc' => [1.0, 12.0],
            'tag' => [5.0, 1.0],
            'lag' => [9.0, 2.0],
            'calling_station' => [1.0, 7.0],
            'maniaco' => [10.0, 5.0],
            'ballena' => [1.0, 10.0],
            default => [4.0, 2.0],
        };

        return [
            min(25.0, $base[0] + $gap * 0.08),
            min(25.0, $base[1] + $gap * 0.12),
        ];
    }

    private function trapPrior(Bot $bot, array $global, array $percentiles, float $openTarget, float $width): array
    {
        $base = match ($bot->templateTipo()) {
            'setminer', 'roca' => 2.0,
            'nit' => 3.0,
            'omc', 'ballena' => 12.0,
            'tag' => 5.0,
            'lag', 'maniaco' => 8.0,
            'calling_station' => 6.0,
            default => 4.0,
        };
        $topRange = $this->softTop($percentiles, min(5.0, max(1.0, $openTarget)), $width);
        $gapAdjustment = min(5.0, max(0.0, $global['vpip'] - $global['pfr']) / 10);
        $prior = [];
        foreach ($percentiles as $hand => $percentile) {
            $prior[$hand] = min(25.0, ($base + $gapAdjustment) * $topRange[$hand] / 100);
        }

        return $prior;
    }

    private function softBand(array $percentiles, float $start, float $end, float $width, ?array $weights = null): array
    {
        $upper = $this->softTop($percentiles, min(100.0, $end), $width, $weights);
        $lower = $this->softTop($percentiles, min(100.0, $start), $width, $weights);
        $band = [];
        foreach ($percentiles as $hand => $_) {
            $band[$hand] = max(0.0, $upper[$hand] - $lower[$hand]);
        }

        return $band;
    }

    private function softTop(array $percentiles, float $target, float $width, ?array $weights = null): array
    {
        $target = max(0.0, min(100.0, $target));
        $values = [];
        foreach ($percentiles as $hand => $percentile) {
            $values[$hand] = max(0.0, min(100.0, 50.0 + ($target - $percentile) * 100.0 / $width));
        }

        return $this->calibrate($values, $target, $weights ?? $this->handWeights($percentiles));
    }

    private function softBottom(array $percentiles, float $target, float $width, array $weights): array
    {
        return array_map(
            fn($value) => 100.0 - $value,
            $this->softTop($percentiles, 100.0 - max(0.0, min(100.0, $target)), $width, $weights)
        );
    }

    private function calibrate(array $values, float $target, array $weights, ?array $caps = null): array
    {
        $target = max(0.0, min(100.0, $target));
        $low = -100.0;
        $high = 100.0;
        for ($i = 0; $i < 42; $i++) {
            $shift = ($low + $high) / 2;
            $adjusted = [];
            foreach ($values as $hand => $value) {
                $cap = $caps[$hand] ?? 100.0;
                $adjusted[$hand] = max(0.0, min($cap, $value + $shift));
            }
            if ($this->weightedAverage($adjusted, $weights) < $target) {
                $low = $shift;
            } else {
                $high = $shift;
            }
        }

        $shift = ($low + $high) / 2;
        foreach ($values as $hand => $value) {
            $values[$hand] = max(0.0, min($caps[$hand] ?? 100.0, $value + $shift));
        }

        return $values;
    }

    private function blendObserved(float $prior, int $successes, int $opportunities): float
    {
        if ($opportunities <= 0) {
            return $prior;
        }

        $observed = 100.0 * max(0, min($opportunities, $successes)) / $opportunities;
        $confidence = $opportunities / ($opportunities + self::PRIOR_OPPORTUNITIES);

        return $prior * (1.0 - $confidence) + $observed * $confidence;
    }

    private function weightedAverage(array $values, array $weights): float
    {
        $weightedSum = 0.0;
        $weightSum = 0.0;
        foreach ($values as $hand => $value) {
            $weight = max(0.0, (float) ($weights[$hand] ?? 0));
            $weightedSum += $value * $weight;
            $weightSum += $weight;
        }

        return $weightSum > 0 ? $weightedSum / $weightSum : 0.0;
    }

    private function handWeights(array $percentiles): array
    {
        $weights = [];
        foreach ($percentiles as $hand => $_) {
            $weights[$hand] = $this->combos($hand);
        }

        return $weights;
    }

    private function combos(string $hand): int
    {
        return strlen($hand) === 2 ? 6 : (str_ends_with($hand, 's') ? 4 : 12);
    }

    private function hands(): array
    {
        $hands = [];
        $ranks = str_split(self::RANKS);

        foreach ($ranks as $row => $high) {
            foreach ($ranks as $column => $low) {
                if ($row === $column) {
                    $hands[] = $high . $low;
                } elseif ($row < $column) {
                    $hands[] = $high . $low . 's';
                } else {
                    $hands[] = $low . $high . 'o';
                }
            }
        }

        return $hands;
    }
}
