<?php

namespace Tests\Unit;

use App\Services\Bots\Brain\ClearCallGuard;
use App\Services\Bots\Brain\DecisionMatrix;
use PHPUnit\Framework\TestCase;

/** Nadie tira una mano que tiene mucha más equity que la que pide el precio. */
class ClearCallGuardTest extends TestCase
{
    // Mano real de un torneo: A♣4♣ con color al nut en un flop 3♣8♣J♠, ante una apuesta pequeña (14 % de precio) y el bot foldeó un 7 %
    private function features(array $override = []): array
    {
        return array_merge(['street' => 'flop', 'facing_bet' => true, 'equity' => 60.8, 'range_equity' => 52.8, 'pot_odds' => 14.0], $override);
    }

    private function matrix(): DecisionMatrix
    {
        $matrix = new DecisionMatrix('fold');
        $matrix->mix(['call' => 68.33, 'bet_twothirds' => 24.39, 'fold' => 7.28]);

        return $matrix;
    }

    public function test_a_hand_with_far_more_equity_than_the_price_asks_never_folds(): void
    {
        $matrix = $this->matrix();

        $this->assertTrue((new ClearCallGuard())->apply($matrix, $this->features()));
        $this->assertSame(0.0, $matrix->points('fold'));
        $this->assertEqualsWithDelta(75.61, $matrix->points('call'), 0.01, 'el fold pasa a call');
        $this->assertEqualsWithDelta(100.0, array_sum($matrix->distribution()), 0.05);
    }

    public function test_it_uses_the_lower_of_the_equity_and_the_equity_against_his_range(): void
    {
        $this->assertFalse((new ClearCallGuard())->applies($this->features(['equity' => 70.0, 'range_equity' => 25.0])));
    }

    public function test_a_marginal_hand_keeps_its_folds(): void
    {
        $matrix = $this->matrix();

        $this->assertFalse((new ClearCallGuard())->apply($matrix, $this->features(['equity' => 30.0, 'range_equity' => 30.0])));   // por debajo del mínimo
        $this->assertFalse((new ClearCallGuard())->apply($matrix, $this->features(['equity' => 45.0, 'range_equity' => 45.0, 'pot_odds' => 30.0])));   // no llega al doble del precio
        $this->assertSame(7.28, $matrix->points('fold'));
    }

    public function test_it_only_applies_postflop_and_facing_a_bet(): void
    {
        $guard = new ClearCallGuard();

        $this->assertFalse($guard->applies($this->features(['street' => 'preflop'])));
        $this->assertFalse($guard->applies($this->features(['facing_bet' => false])));
        $this->assertFalse($guard->applies(['street' => 'flop', 'facing_bet' => true]));   // sin datos de equity no se toca nada
    }
}
