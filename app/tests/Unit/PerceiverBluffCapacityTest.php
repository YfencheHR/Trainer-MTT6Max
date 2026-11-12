<?php

namespace Tests\Unit;

use App\Services\Bots\Brain\Perceiver;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class PerceiverBluffCapacityTest extends TestCase
{
    public function test_opponent_bluff_capacity_is_zero_without_showdown_evidence(): void
    {
        $features = $this->perceive([])['features'];

        $this->assertSame(0.0, $features['opponent_bluff_frequency']);
        $this->assertSame(0.0, $features['bluff_likelihood']);
    }

    public function test_showdown_bluff_stat_caps_the_perceived_bluff_likelihood(): void
    {
        $features = $this->perceive(['river' => ['bluff' => 40, 'sample_size' => 100]])['features'];

        $this->assertSame(40.0, $features['opponent_bluff_frequency']);
        $this->assertGreaterThan(0.0, $features['bluff_likelihood']);
        $this->assertLessThanOrEqual($features['opponent_bluff_frequency'], $features['bluff_likelihood']);
    }

    private function perceive(array $postflopStats): array
    {
        $context = [
            'street' => 'river',
            'big_blind' => 100,
            'pot' => 1000,
            'to_call' => 600,
            'required_equity' => 0.27,
            'board' => ['Q♣', 'J♥', 'T♠', '4♦', '2♣'],
            'me' => [
                'id' => 1,
                'position' => 'BTN',
                'stack' => 5000,
                'bet' => 0,
                'hole_cards' => ['A♠', 'K♦'],
                'flags' => [],
            ],
            'players' => [
                [
                    'id' => 1, 'position' => 'BTN', 'stack' => 5000, 'folded' => false,
                    'hole_cards' => ['A♠', 'K♦'], 'stats' => [],
                ],
                [
                    'id' => 2, 'position' => 'BB', 'stack' => 5000, 'folded' => false,
                    'stats' => ['vpip' => 30, 'pfr' => 20, 'af' => 2.0, 'hands' => 100],
                    'postflop_stats' => $postflopStats,
                ],
            ],
            'history' => [
                ['player_id' => 2, 'street' => 'flop', 'action' => 'check', 'amount' => 0],
                ['player_id' => 2, 'street' => 'turn', 'action' => 'check', 'amount' => 0],
                ['player_id' => 2, 'street' => 'river', 'action' => 'raise', 'amount' => 600, 'pot_before' => 1000],
            ],
            'equity_samples' => 10,
            'legal' => ['raise' => ['min' => 1200, 'max' => 5000]],
        ];

        return app(Perceiver::class)->perceive($context, [
            'skill' => [
                'suspicion' => 1.0,
                'noise' => ['bluff_sigma' => 0, 'equity_sigma' => 0, 'rank_sigma' => 0],
                'equity_samples' => 10,
            ],
        ], new Randomizer(new Mt19937(12)));
    }
}
