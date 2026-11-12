<?php

namespace Tests\Feature;

use App\Services\Bots\Brain\DecisionMatrix;
use App\Services\Bots\Brain\PreflopOpponentDanger;
use Tests\TestCase;

class PreflopOpponentDangerTest extends TestCase
{
    public function test_it_selects_only_active_players_after_the_bot_in_preflop_order(): void
    {
        $ctx = [
            'me' => ['id' => 3, 'position' => 'CO'],
            'players' => [
                ['id' => 1, 'position' => 'UTG', 'folded' => false, 'all_in' => false],
                ['id' => 2, 'position' => 'HJ', 'folded' => false, 'all_in' => false],
                ['id' => 3, 'position' => 'CO', 'folded' => false, 'all_in' => false],
                ['id' => 4, 'position' => 'BTN', 'folded' => false, 'all_in' => false],
                ['id' => 5, 'position' => 'SB', 'folded' => true, 'all_in' => false],
                ['id' => 6, 'position' => 'BB', 'folded' => false, 'all_in' => true],
            ],
        ];

        $players = app(PreflopOpponentDanger::class)->playersStillToAct($ctx, [
            'street' => 'preflop',
            'position' => 'CO',
        ]);

        $this->assertSame([4], array_column($players, 'id'));
    }

    public function test_it_moves_action_frequency_to_fold_when_a_dangerous_rival_is_behind(): void
    {
        $ctx = $this->context([
            'id' => 2,
            'position' => 'BTN',
            'folded' => false,
            'all_in' => false,
            'stats' => ['hands' => 100],
            'preflop_stats' => ['vpip' => 10, 'pfr' => 30, 'three_bet' => 20, 'source' => 'observed'],
        ]);
        $matrix = new DecisionMatrix();
        $matrix->mix(['open_2_5bb' => 50, 'call' => 50]);

        $result = app(PreflopOpponentDanger::class)->apply(
            $matrix,
            $ctx,
            ['street' => 'preflop', 'position' => 'CO'],
            ['vpip' => 20, 'pfr' => 15, 'three_bet' => 5]
        );
        $distribution = $matrix->distribution();

        $this->assertGreaterThan(0, $result['moved_pct']);
        $this->assertArrayHasKey('fold', $distribution);
        $this->assertEqualsWithDelta($result['moved_pct'], $distribution['fold'], 0.02);
        $this->assertLessThan(50, $distribution['open_2_5bb']);
        $this->assertLessThan(50, $distribution['call']);
        $this->assertEqualsWithDelta(100, array_sum($distribution), 0.02);
    }

    public function test_a_very_loose_player_has_negligible_fold_pressure(): void
    {
        $ctx = $this->context([
            'id' => 2,
            'position' => 'BTN',
            'folded' => false,
            'all_in' => false,
            'stats' => ['hands' => 100],
            'preflop_stats' => ['vpip' => 80, 'pfr' => 10, 'three_bet' => 2, 'source' => 'observed'],
        ]);
        $matrix = new DecisionMatrix();
        $matrix->mix(['open_2_5bb' => 50, 'call' => 50]);

        $result = app(PreflopOpponentDanger::class)->apply(
            $matrix,
            $ctx,
            ['street' => 'preflop', 'position' => 'CO'],
            ['vpip' => 20, 'pfr' => 15, 'three_bet' => 5]
        );

        $this->assertSame(0.0, $result['operator_pct']);
        $this->assertSame(0.0, $matrix->points('fold'));
        $this->assertEqualsWithDelta(100, array_sum($matrix->distribution()), 0.02);
    }

    public function test_it_does_not_adjust_when_no_opponent_remains_to_act(): void
    {
        $ctx = $this->context([
            'id' => 2,
            'position' => 'UTG',
            'folded' => false,
            'all_in' => false,
            'stats' => ['hands' => 100],
            'preflop_stats' => ['vpip' => 10, 'pfr' => 30, 'three_bet' => 20, 'source' => 'observed'],
        ]);
        $matrix = new DecisionMatrix();
        $matrix->mix(['open_2_5bb' => 50, 'call' => 50]);

        $result = app(PreflopOpponentDanger::class)->apply(
            $matrix,
            $ctx,
            ['street' => 'preflop', 'position' => 'BTN'],
            ['vpip' => 20, 'pfr' => 15, 'three_bet' => 5]
        );

        $this->assertSame(0.0, $result['operator_pct']);
        $this->assertSame(0.0, $matrix->points('fold'));
    }

    private function context(array $behind): array
    {
        return [
            'me' => ['id' => 1, 'position' => 'CO'],
            'players' => [
                [
                    'id' => 1,
                    'position' => 'CO',
                    'folded' => false,
                    'all_in' => false,
                    'stats' => ['hands' => 100],
                    'preflop_stats' => ['vpip' => 20, 'pfr' => 15, 'three_bet' => 5, 'source' => 'profile'],
                ],
                $behind,
            ],
        ];
    }
}
