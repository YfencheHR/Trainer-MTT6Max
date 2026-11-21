<?php

namespace Tests\Unit;

use App\Services\Ai\DecisionContract;
use App\Services\Ai\HandHistoryParser;
use PHPUnit\Framework\TestCase;

class DecisionContractTest extends TestCase
{
    private function hand(): array
    {
        return (new HandHistoryParser)->parse(HandHistoryParserTest::HAND);
    }

    public function test_history_state_only_exists_when_the_player_cards_are_known(): void
    {
        $contract = new DecisionContract;
        $hand = $this->hand();

        $this->assertNull($contract->fromHistory($hand, 0), 'el rival que abre no enseña cartas');

        // El héroe (botón, J♦6♠) foldea ante la apertura: acción nº 2
        $row = $contract->fromHistory($hand, 2);
        $this->assertSame('fold', $row['action']);
        $this->assertSame(['J♦', '6♠'], $row['state']['hole_cards']);
        $this->assertSame('BTN', $row['state']['position']);
        $this->assertSame(2.0, $row['state']['to_call_bb'], 'a igualar 300 = 2bb de 150');
        $this->assertSame(1, $row['state']['raises_this_street']);
        $this->assertSame(4, $row['state']['players_left'], 'cinco en la mano menos el que ya foldeó');
        $this->assertSame(DecisionContract::VERSION, $row['state']['contract']);
    }

    public function test_the_player_personality_is_its_stats_and_the_model_only_decides_postflop(): void
    {
        $c = new DecisionContract;
        $hand = $this->hand();
        $profiles = ['Fofito2003' => ['preflop_stats' => ['vpip' => 28], 'postflop_stats' => ['flop' => ['fold_to_cbet' => 55]]]];

        $row = $c->fromHistory($hand, 2, $profiles);

        $this->assertSame(['vpip' => 28], $row['state']['profile']['preflop']);
        $this->assertSame(55, $row['state']['profile']['postflop']['flop']['fold_to_cbet']);
        $this->assertFalse($c->supports($row['state']), 'preflop sigue con la tabla del perfil');
        $this->assertTrue($c->supports(['street' => 'turn']));
    }

    public function test_the_simulator_state_names_the_first_postflop_bet_a_bet_and_caps_the_call_to_the_stack(): void
    {
        $ctx = [
            'street' => 'flop', 'big_blind' => 100, 'pot' => 900, 'to_call' => 300, 'board' => ['K♣', '7♥', '2♦'],
            'me' => ['id' => 1, 'position' => 'BTN', 'stack' => 200, 'hole_cards' => ['A♠', 'K♦'], 'stats' => []],
            'players' => [
                ['id' => 1, 'position' => 'BTN', 'stack' => 200, 'bet' => 0, 'folded' => false],
                ['id' => 2, 'position' => 'BB', 'stack' => 5000, 'bet' => 300, 'folded' => false],
            ],
            'history' => [
                ['player_id' => 2, 'street' => 'preflop', 'action' => 'raise', 'amount' => 250],
                ['player_id' => 2, 'street' => 'flop', 'action' => 'raise', 'amount' => 300],
                ['player_id' => 1, 'street' => 'flop', 'action' => 'raise', 'amount' => 900],
            ],
        ];

        $state = (new DecisionContract)->fromBotContext($ctx);

        $this->assertSame(['preflop', 'BB', 'raise', 2.5], $state['history'][0], 'preflop la primera subida sigue siendo raise');
        $this->assertSame(['flop', 'BB', 'bet', 3.0], $state['history'][1]);
        $this->assertSame(['flop', 'BTN', 'raise', 9.0], $state['history'][2]);
        $this->assertSame(2.0, $state['to_call_bb'], 'con 200 de stack solo puede igualar 200');
    }

    public function test_real_amounts_become_the_nearest_vocabulary_action(): void
    {
        $c = new DecisionContract;
        $base = ['street' => 'preflop', 'action' => 'raise', 'all_in' => false, 'stack_before' => 9000, 'committed_before' => 0, 'current_bet' => 150, 'to_call' => 150, 'pot_before' => 300];

        $this->assertSame('open_2bb', $c->actionLabel(['amount' => 300] + $base, 150));
        $this->assertSame('open_3bb', $c->actionLabel(['amount' => 450] + $base, 150));
        $this->assertSame('raise_x3', $c->actionLabel(['amount' => 900, 'current_bet' => 300] + $base, 150));
        $this->assertSame('allin', $c->actionLabel(['amount' => 9000] + $base, 150));

        $flop = ['street' => 'flop', 'action' => 'bet', 'all_in' => false, 'stack_before' => 9000, 'committed_before' => 0, 'current_bet' => 0, 'to_call' => 0, 'pot_before' => 1000];
        $this->assertSame('bet_third', $c->actionLabel(['amount' => 330] + $flop, 150));
        $this->assertSame('bet_pot', $c->actionLabel(['amount' => 1000] + $flop, 150));
        $this->assertSame('bet_overbet', $c->actionLabel(['amount' => 1600] + $flop, 150));
    }

    public function test_sampling_is_reproducible_and_only_returns_possible_actions(): void
    {
        $c = new DecisionContract;
        $dist = ['fold' => 0.5, 'call' => 0.3, 'bet_pot' => 0.2, 'inventada' => 9.0];

        $this->assertSame($c->sample($dist, 42), $c->sample($dist, 42));
        for ($seed = 1; $seed <= 200; $seed++) {
            $this->assertContains($c->sample($dist, $seed), ['fold', 'call', 'bet_pot']);
        }
    }

    public function test_illegal_actions_are_removed_and_the_rest_renormalised(): void
    {
        $c = new DecisionContract;
        $legal = ['fold' => false, 'check' => true, 'call' => null, 'raise' => null];

        $out = $c->restrictToLegal(['fold' => 0.5, 'check' => 0.2, 'bet_pot' => 0.3], $legal);

        $this->assertSame(['check' => 1.0], $out);
    }
}
