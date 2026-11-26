<?php

namespace Tests\Unit;

use App\Services\Ai\BotMemory;
use App\Services\Ai\DecisionContract;
use PHPUnit\Framework\TestCase;

class BotMemoryTest extends TestCase
{
    private const PROFILE = ['preflop' => ['vpip' => 20, 'pfr' => 15, 'three_bet' => 6], 'postflop' => ['flop' => ['f2bet' => 40, 'cbet' => 60]]];

    private function observed(int $hands, int $vpip = 50): array
    {
        return ['hands' => $hands, 'vpip' => $vpip, 'pfr' => 30, 'three_bet' => 20, 'three_bet_opp' => 10, 'cbet' => 90, 'cbet_opp' => 5];
    }

    public function test_without_memory_the_profile_is_what_the_bot_knows(): void
    {
        $this->assertSame(self::PROFILE, (new BotMemory)->apply(self::PROFILE, $this->observed(100), BotMemory::NONE));
    }

    public function test_with_tournament_memory_the_observed_hands_weigh_more_as_they_grow(): void
    {
        $few = (new BotMemory)->apply(self::PROFILE, $this->observed(5), BotMemory::TOURNAMENT);
        $many = (new BotMemory)->apply(self::PROFILE, $this->observed(100), BotMemory::TOURNAMENT);

        $this->assertSame(25.0, $few['preflop']['vpip'], '5 manos: 5/30 de lo observado');
        $this->assertSame(44.0, $many['preflop']['vpip'], '100 manos: 100/125 de lo observado');
        $this->assertSame(5, $few['preflop']['observed_hands']);
        $this->assertSame(40, $few['postflop']['flop']['f2bet'], 'lo que no se observa queda como estaba');
    }

    public function test_each_statistic_is_weighed_by_its_own_sample(): void
    {
        $mixed = (new BotMemory)->apply(self::PROFILE, $this->observed(100), BotMemory::TOURNAMENT);

        $this->assertSame(10.0, $mixed['preflop']['three_bet'], '10 oportunidades de 3-bet: 10/35 de 20 y el resto de 6');
        $this->assertSame(65.0, $mixed['postflop']['flop']['cbet'], '5 oportunidades de c-bet: 5/30 de 90 y el resto de 60');
    }

    public function test_a_statistic_missing_in_the_profile_is_taken_from_what_was_observed_and_never_invented(): void
    {
        $bare = ['preflop' => ['vpip' => 20], 'postflop' => []];
        $mixed = (new BotMemory)->apply($bare, ['hands' => 40, 'vpip' => 30, 'pfr' => 12, 'three_bet' => null, 'three_bet_opp' => 0, 'cbet' => null, 'cbet_opp' => 0], BotMemory::TOURNAMENT);

        $this->assertSame(12.0, $mixed['preflop']['pfr']);
        $this->assertArrayNotHasKey('three_bet', $mixed['preflop']);
        $this->assertSame([], $mixed['postflop']);
    }

    public function test_no_observed_hands_leave_the_profile_untouched(): void
    {
        $this->assertSame(self::PROFILE, (new BotMemory)->apply(self::PROFILE, ['hands' => 0], BotMemory::TOURNAMENT));
    }

    public function test_the_contract_shows_the_bot_the_configured_profile_or_the_corrected_one_depending_on_the_tournament(): void
    {
        $ctx = fn (string $memory) => [
            'bot_memory' => $memory, 'hand_id' => 1, 'hand_number' => 1, 'street' => 'flop', 'big_blind' => 100, 'pot' => 600, 'current_bet' => 0, 'to_call' => 0,
            'board' => ['K♣', '7♥', '2♦'],
            'me' => ['id' => 1, 'bot_id' => 1, 'position' => 'BTN', 'stack' => 5000, 'bet' => 0, 'hole_cards' => ['A♠', 'K♦'], 'stats' => ['vpip' => 24, 'pfr' => 18]],
            'players' => [
                ['id' => 1, 'position' => 'BTN', 'stack' => 5000, 'bet' => 0, 'folded' => false, 'preflop_stats' => ['vpip' => 24], 'profile_preflop' => ['vpip' => 24], 'postflop_stats' => []],
                ['id' => 2, 'position' => 'BB', 'stack' => 5000, 'bet' => 0, 'folded' => false, 'stats' => $this->observed(100),
                    'preflop_stats' => ['vpip' => 50, 'source' => 'observed'], 'profile_preflop' => ['vpip' => 20], 'postflop_stats' => []],
            ],
            'history' => [], 'legal' => ['fold' => false, 'check' => true, 'call' => null, 'raise' => ['min' => 100, 'max' => 5000], 'allin' => 5000],
        ];
        $contract = new DecisionContract;

        $none = $contract->fromBotContext($ctx('none'));
        $tournament = $contract->fromBotContext($ctx('tournament'));

        $this->assertSame(20, $none['rivals'][0]['profile']['preflop']['vpip'], 'sin memoria, el perfil configurado aunque ya se hayan visto manos');
        $this->assertSame(44.0, $tournament['rivals'][0]['profile']['preflop']['vpip']);
        $this->assertSame(24, $tournament['profile']['preflop']['vpip'], 'un bot no corrige su propio perfil con lo que observan de él');
    }
}
