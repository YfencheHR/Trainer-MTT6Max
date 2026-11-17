<?php

namespace Tests\Unit;

use App\Services\Import\StatBounds;
use PHPUnit\Framework\TestCase;

class StatBoundsTest extends TestCase
{
    public function test_fold_to_3bet_of_100_is_pulled_down_and_the_difference_goes_to_call(): void
    {
        $out = StatBounds::apply(['fold_to_3bet' => 100, 'call_vs_3bet' => 0.0, 'reraise_vs_3bet' => 0.0, 'four_bet' => 0]);

        $this->assertSame(90, $out['fold_to_3bet']);
        $this->assertEqualsWithDelta(10.0, $out['call_vs_3bet'], 0.001);
        $this->assertEqualsWithDelta(100.0, $out['fold_to_3bet'] + $out['call_vs_3bet'] + $out['reraise_vs_3bet'], 0.001);
    }

    public function test_a_fold_that_never_happens_is_raised_to_the_floor(): void
    {
        $this->assertSame(5, StatBounds::apply(['fold_to_4bet' => 0])['fold_to_4bet']);
        $this->assertSame(5, StatBounds::apply(['f2raise_turn' => 0])['f2raise_turn']);
    }

    public function test_realistic_values_are_not_touched(): void
    {
        $in = ['fold_to_3bet' => 64, 'call_vs_3bet' => 32.4, 'reraise_vs_3bet' => 3.6, 'cbet' => 58, 'f2cbet' => 54];

        $this->assertSame($in, StatBounds::apply($in));
    }

    public function test_stats_that_can_legitimately_be_zero_or_hundred_are_never_modified(): void
    {
        // Hay jugadores que nunca abren y solo limpean, o que nunca suben: 0 y 100 son datos válidos
        $in = ['rfi' => 0, 'pfr' => 0, 'limp' => 100.0, 'three_bet' => 0, 'four_bet' => 0, 'squeeze' => 0, 'cr_flop' => 0, 'raise_vs_bet' => 0, 'vpip' => 100];

        $this->assertSame($in, StatBounds::apply($in));
    }

    public function test_missing_or_null_stats_are_ignored(): void
    {
        $this->assertSame(['vpip' => 40, 'fold_to_3bet' => null], StatBounds::apply(['vpip' => 40, 'fold_to_3bet' => null]));
        $this->assertSame([], StatBounds::apply([]));
    }

    public function test_postflop_extremes_are_bounded(): void
    {
        $out = StatBounds::apply(['f2cbet' => 100, 'cbet' => 0, 'bb_defense' => 100, 'sb_defense' => 0]);

        $this->assertSame(['f2cbet' => 90, 'cbet' => 5, 'bb_defense' => 95, 'sb_defense' => 5], $out);
    }
}
