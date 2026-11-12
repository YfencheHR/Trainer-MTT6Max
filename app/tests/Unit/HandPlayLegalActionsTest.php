<?php

namespace Tests\Unit;

use App\Services\HandPlayService;
use ReflectionMethod;
use Tests\TestCase;

class HandPlayLegalActionsTest extends TestCase
{
    public function test_fold_is_unavailable_when_check_is_free_postflop(): void
    {
        $players = $this->players([0, 0, 0, 0, 0, 0]);

        $legal = $this->legalActions($players, 0, 'flop');

        $this->assertFalse($legal['fold']);
        $this->assertTrue($legal['check']);
    }

    public function test_preflop_players_can_fold_but_big_blind_can_check_after_limp(): void
    {
        $players = $this->players([100, 100, 100, 100, 100, 100]);

        $earlyPosition = $this->legalActions($players, 0, 'preflop');
        $bigBlind = $this->legalActions($players, 2, 'preflop');

        $this->assertTrue($earlyPosition['fold']);
        $this->assertFalse($bigBlind['fold']);
        $this->assertTrue($bigBlind['check']);
    }

    public function test_fold_is_available_when_a_bet_must_be_faced(): void
    {
        $players = $this->players([0, 0, 50, 0, 0, 0]);

        $legal = $this->legalActions($players, 0, 'flop');

        $this->assertTrue($legal['fold']);
        $this->assertFalse($legal['check']);
    }

    public function test_small_blinds_raise_max_includes_the_posted_blind_once(): void
    {
        $players = $this->players([50, 100, 0, 0, 0, 0]);
        $players[0]['stack'] = 8950;
        for ($index = 1; $index < count($players); $index++) {
            $players[$index]['stack'] = 10000;
        }

        $legal = $this->legalActions($players, 0, 'preflop');

        // The engine validates a cumulative target, including the posted blind.
        $this->assertSame(9000, $legal['raise']['max']);
    }

    public function test_big_blinds_raise_max_includes_the_posted_blind_once(): void
    {
        $players = $this->players([50, 100, 0, 0, 0, 0]);
        $players[1]['stack'] = 8900;
        foreach ($players as $index => &$player) {
            if ($index !== 1) {
                $player['stack'] = 10000;
            }
        }
        unset($player);

        $legal = $this->legalActions($players, 1, 'preflop');

        // The engine validates a cumulative target, including the posted blind.
        $this->assertSame(9000, $legal['raise']['max']);
    }

    public function test_hero_raise_range_is_additional_amount_and_adds_existing_blind_to_target(): void
    {
        $players = $this->players([100, 100, 0, 0, 0, 0]);
        $players[0]['stack'] = 4280;
        for ($index = 1; $index < count($players); $index++) {
            $players[$index]['stack'] = 10000;
        }

        $service = new HandPlayService();
        $method = new ReflectionMethod($service, 'heroLegal');
        $legal = $method->invoke($service, $players, [
            'turn' => 1,
            'street' => 'preflop',
            'btn' => 0,
            'bb' => 100,
            'last_raise' => 400,
            'pot' => 200,
        ], false);

        $this->assertSame(400, $legal['raise']['min']);
        $this->assertSame(4280, $legal['raise']['max']);
        $this->assertSame(500, $players[0]['bet'] + $legal['raise']['min']);
    }

    public function test_hero_reraise_range_adds_the_previous_wager_to_the_new_raise(): void
    {
        $players = $this->players([500, 1000, 0, 0, 0, 0]);
        $players[0]['stack'] = 4280;
        for ($index = 1; $index < count($players); $index++) {
            $players[$index]['stack'] = 10000;
        }

        $service = new HandPlayService();
        $method = new ReflectionMethod($service, 'heroLegal');
        $legal = $method->invoke($service, $players, [
            'turn' => 1,
            'street' => 'preflop',
            'btn' => 0,
            'bb' => 100,
            'last_raise' => 500,
            'pot' => 1500,
        ], false);

        $this->assertSame(1000, $legal['raise']['min']);
        $this->assertSame(4280, $legal['raise']['max']);
        $this->assertSame(1500, $players[0]['bet'] + $legal['raise']['min']);
    }

    public function test_raise_is_unavailable_when_all_remaining_opponents_are_all_in_or_folded(): void
    {
        $players = $this->players([100, 100, 100, 100, 100, 100]);
        for ($index = 1; $index < count($players); $index++) {
            $players[$index]['all_in'] = true;
        }

        $legal = $this->legalActions($players, 0, 'preflop');

        $this->assertNull($legal['raise']);
    }

    public function test_ante_enters_the_pot_without_counting_toward_the_players_bet(): void
    {
        $player = ['stack' => 100, 'bet' => 10, 'total' => 10, 'all_in' => false];
        $meta = ['pot' => 30];
        $service = new HandPlayService();
        $method = new ReflectionMethod($service, 'postAnte');
        $arguments = [&$player, &$meta, 15];

        $method->invokeArgs($service, $arguments);

        $this->assertSame(85, $player['stack']);
        $this->assertSame(10, $player['bet']);
        $this->assertSame(25, $player['total']);
        $this->assertSame(45, $meta['pot']);
        $this->assertFalse($player['all_in']);
    }

    private function legalActions(array $players, int $playerIndex, string $street): array
    {
        $service = new HandPlayService();
        $method = new ReflectionMethod($service, 'legalActions');

        return $method->invoke($service, $players, [
            'street' => $street,
            'btn' => 0,
            'bb' => 100,
            'last_raise' => 100,
        ], $players[$playerIndex]);
    }

    private function players(array $bets): array
    {
        return array_map(fn(int $bet, int $index) => [
            'id' => $index + 1,
            'bet' => $bet,
            'stack' => 1000,
            'locked' => false,
            'folded' => false,
            'all_in' => false,
            'is_bot' => false,
        ], $bets, array_keys($bets));
    }
}
