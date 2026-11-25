<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Services\Ai\HandHistoryParser;
use App\Services\Replay\RealHandContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Una decisión de una mano real se convierte en el contexto que recibe el cerebro de un bot. */
class RealHandContextTest extends TestCase
{
    use RefreshDatabase;

    private const HAND = <<<'TXT'
PokerStars Hand #1: Tournament #2, $1 USD Hold'em No Limit - Level I (10/20) - 2025/12/20 15:23:15 CET [2025/12/20 9:23:15 ET]
Table '2 1' 6-max Seat #1 is the button
Seat 1: Alice (1000 in chips)
Seat 2: Bob (1000 in chips)
Seat 3: Carol (1000 in chips)
Bob: posts small blind 10
Carol: posts big blind 20
*** HOLE CARDS ***
Dealt to Alice [As Kd]
Alice: raises 40 to 60
Bob: folds
Carol: calls 40
*** FLOP *** [Ks 7h 2d]
Carol: checks
Alice: bets 80
Carol: folds
TXT;

    private function builder(): array
    {
        $hand = (new HandHistoryParser())->parse(self::HAND);
        $bot = Bot::create(['nombre' => 'Pool X', 'tipo' => 'pool', 'vpip' => 30, 'pfr' => 20]);

        return [$hand, new RealHandContext(['Alice' => $bot, 'Carol' => $bot]), $bot];
    }

    public function test_it_rebuilds_what_the_bot_could_know_when_it_bet_the_flop(): void
    {
        [$hand, $builder, $bot] = $this->builder();
        $index = array_key_first(array_filter($hand['actions'], fn ($a) => $a['player'] === 'Alice' && $a['street'] === 'flop'));

        $ctx = $builder->build($hand, $index);

        $this->assertSame('flop', $ctx['street']);
        $this->assertSame(['K♠', '7♥', '2♦'], $ctx['board']);
        $this->assertSame(130, $ctx['pot']);
        $this->assertSame(0, $ctx['to_call']);
        $this->assertSame('BTN', $ctx['me']['position']);
        $this->assertSame(['A♠', 'K♦'], $ctx['me']['hole_cards']);
        $this->assertSame($bot->id, $ctx['me']['bot_id']);
        $this->assertTrue($ctx['legal']['check']);
        $this->assertFalse($ctx['legal']['fold']);
        $this->assertCount(4, $ctx['history'], 'subida, fold, call y check anteriores');
        $this->assertSame('raise', $ctx['history'][0]['action']);
        $this->assertTrue(collect($ctx['players'])->firstWhere('name', 'Bob')['folded'], 'Bob ya había foldeado');
    }

    public function test_it_uses_the_given_cards_instead_of_the_real_ones_and_refuses_players_without_a_bot(): void
    {
        [$hand, $builder] = $this->builder();
        $index = array_key_first(array_filter($hand['actions'], fn ($a) => $a['player'] === 'Alice' && $a['street'] === 'preflop'));

        $ctx = $builder->build($hand, $index, ['7♣', '2♦']);

        $this->assertSame(['7♣', '2♦'], $ctx['me']['hole_cards']);
        $this->assertSame(20, $ctx['to_call'], 'la ciega grande que tiene delante');
        $this->assertTrue($ctx['legal']['fold']);
        $bobFold = array_key_first(array_filter($hand['actions'], fn ($a) => $a['player'] === 'Bob'));
        $this->assertNull($builder->build($hand, $bobFold), 'Bob no tiene perfil asignado');
    }

    public function test_a_player_whose_cards_are_unknown_needs_candidate_cards(): void
    {
        [$hand, $builder] = $this->builder();
        $carolCall = array_key_first(array_filter($hand['actions'], fn ($a) => $a['player'] === 'Carol'));

        $this->assertNull($builder->build($hand, $carolCall), 'sin cartas conocidas ni candidatas no se puede preguntar');
        $this->assertNotNull($builder->build($hand, $carolCall, ['Q♣', 'J♣']));
    }
}
