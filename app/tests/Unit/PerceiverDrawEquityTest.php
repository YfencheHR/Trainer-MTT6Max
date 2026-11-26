<?php

namespace Tests\Unit;

use App\Services\Bots\Brain\Perceiver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

/** range_equity sale de la simulación (que ya incluye los proyectos): no se le suman las salidas otra vez. */
class PerceiverDrawEquityTest extends TestCase
{
    use RefreshDatabase;

    private function perceive(array $hole, array $board): array
    {
        $context = [
            'street' => count($board) === 3 ? 'flop' : 'turn',
            'big_blind' => 100,
            'pot' => 600,
            'to_call' => 300,
            'required_equity' => 0.33,
            'board' => $board,
            'me' => [
                'id' => 1, 'position' => 'BTN', 'stack' => 5000, 'bet' => 0, 'hole_cards' => $hole, 'flags' => [],
                'stats' => ['vpip' => 30, 'pfr' => 20],
            ],
            'players' => [
                ['id' => 1, 'position' => 'BTN', 'stack' => 5000, 'folded' => false, 'hole_cards' => $hole, 'stats' => ['vpip' => 30, 'pfr' => 20]],
                ['id' => 2, 'position' => 'BB', 'stack' => 5000, 'folded' => false, 'stats' => ['vpip' => 30, 'pfr' => 20, 'af' => 2.0, 'hands' => 100], 'postflop_stats' => []],
            ],
            'history' => [['player_id' => 2, 'street' => count($board) === 3 ? 'flop' : 'turn', 'action' => 'raise', 'amount' => 300, 'pot_before' => 600]],
            'equity_samples' => 400,
            'legal' => ['raise' => ['min' => 600, 'max' => 5000]],
        ];

        return app(Perceiver::class)->perceive($context, [
            'skill' => ['noise' => ['bluff_sigma' => 0, 'equity_sigma' => 0, 'rank_sigma' => 0], 'equity_samples' => 400],
        ], new Randomizer(new Mt19937(7)));
    }

    public function test_a_flush_draw_does_not_add_its_outs_on_top_of_the_simulated_equity(): void
    {
        // Proyecto de color a la nuts en el flop (9 outs): antes range_equity superaba a la simulación en outs × 4 = 36 puntos
        $result = $this->perceive(['A♠', '5♠'], ['K♠', '9♠', '2♦']);

        $this->assertGreaterThanOrEqual(8, $result['features']['draw_outs']);
        $this->assertEqualsWithDelta($result['real']['range_equity'], $result['features']['range_equity'], 0.11);
    }

    public function test_a_straight_draw_on_the_turn_does_not_add_its_outs_either(): void
    {
        $result = $this->perceive(['J♥', 'T♣'], ['9♦', '8♠', '2♣', 'K♥']);

        $this->assertGreaterThanOrEqual(8, $result['features']['draw_outs']);
        $this->assertEqualsWithDelta($result['real']['range_equity'], $result['features']['range_equity'], 0.11);
    }
}
