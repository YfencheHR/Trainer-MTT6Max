<?php

namespace Tests\Unit;

use App\Services\Ai\HandHistoryParser;
use PHPUnit\Framework\TestCase;

/** Manos reales de PokerTracker (tourney_hand_histories, texto de PokerStars). */
class HandHistoryParserTest extends TestCase
{
    public const HAND = <<<'TXT'
PokerStars Hand #258968858937: Tournament #3955015312, €9+€1 EUR Hold'em No Limit - Level III (75/150) - 2025/12/20 15:24:10 CET [2025/12/20 9:24:10 ET]
Table '3955015312 4' 8-max Seat #6 is the button
Seat 1: chti59260 (9765 in chips) is sitting out
Seat 3: O_Ppescador (9777 in chips)
Seat 4: AntiHestamine (18273 in chips)
Seat 5: pedraxix (16275 in chips)
Seat 6: Fofito2003 (9800 in chips)
Seat 7: Chetitos123 (10000 in chips) out of hand (moved from another table into small blind)
Seat 8: games13140 (7461 in chips)
Chetitos123 will be allowed to play after the button
O_Ppescador: posts the ante 15
AntiHestamine: posts the ante 15
pedraxix: posts the ante 15
Fofito2003: posts the ante 15
games13140: posts the ante 15
games13140: posts small blind 75
O_Ppescador: posts big blind 150
*** HOLE CARDS ***
Dealt to Fofito2003 [Jd 6s]
AntiHestamine: raises 150 to 300
pedraxix: folds
Fofito2003: folds
games13140: calls 225
O_Ppescador: folds
*** FLOP *** [3c Kc Js]
games13140: bets 420
AntiHestamine: calls 420
*** TURN *** [3c Kc Js] [6c]
games13140: bets 150
AntiHestamine: raises 1188 to 1338
games13140: calls 1188
*** RIVER *** [3c Kc Js 6c] [3d]
games13140: bets 2178
AntiHestamine: folds
Uncalled bet (2178) returned to games13140
games13140 collected 4356 from pot
games13140: doesn't show hand
*** SUMMARY ***
Total pot 4356 | Rake 0
Board [3c Kc Js 6c 3d]
TXT;

    public function test_it_reads_players_positions_and_hero_cards(): void
    {
        $hand = (new HandHistoryParser)->parse(self::HAND);

        $this->assertSame('258968858937', $hand['hand_id']);
        $this->assertSame(150, $hand['big_blind']);
        $this->assertCount(5, $hand['players'], 'sentado fuera y «out of hand» no juegan');
        $pos = array_column($hand['players'], 'position', 'name');
        $this->assertSame('BTN', $pos['Fofito2003']);
        $this->assertSame('SB', $pos['games13140']);
        $this->assertSame('BB', $pos['O_Ppescador']);
        $this->assertSame('CO', $pos['pedraxix']);
        $this->assertSame('Fofito2003', $hand['hero']);
        $this->assertSame(['J♦', '6♠'], $hand['players'][3]['hole_cards']);
        $this->assertSame(['3♣', 'K♣', 'J♠', '6♣', '3♦'], $hand['board']);
    }

    public function test_each_action_carries_the_state_before_it(): void
    {
        $actions = (new HandHistoryParser)->parse(self::HAND)['actions'];

        // Apertura del UTG: bote = 5 antes (75) + ciegas (225) = 300, a igualar 150, sin nada puesto aún
        $open = $actions[0];
        $this->assertSame('raise', $open['action']);
        $this->assertSame(300, $open['pot_before']);
        $this->assertSame(150, $open['to_call']);
        $this->assertSame(18258, $open['stack_before'], 'fondos tras el ante');
        $this->assertSame(300, $open['amount']);

        // La ciega pequeña iguala 225 (ya puso 75)
        $call = $actions[3];
        $this->assertSame('call', $call['action']);
        $this->assertSame(225, $call['amount']);
        $this->assertSame(225, $call['to_call']);

        // En el flop apuesta la ciega pequeña: bote = antes 75 + apertura 300 + su call 300 + ciega grande 150 = 825
        $flopBet = $actions[5];
        $this->assertSame(825, $flopBet['pot_before']);
        $this->assertSame('flop', $flopBet['street']);
        $this->assertSame('bet', $flopBet['action']);
        $this->assertSame(0, $flopBet['to_call']);
        $this->assertSame(['3♣', 'K♣', 'J♠'], $flopBet['board']);

        // La subida del turno: apuesta 150, sube a 1338 (pone 1338 desde 0)
        $raise = array_values(array_filter($actions, fn ($a) => $a['action'] === 'raise' && $a['street'] === 'turn'))[0];
        $this->assertSame(150, $raise['to_call']);
        $this->assertSame(1338, $raise['amount']);

        // El bote que se devuelve sin igualar no cuenta: el fold del river llega con la apuesta de 2178 por igualar
        $fold = end($actions);
        $this->assertSame('fold', $fold['action']);
        $this->assertSame(2178, $fold['to_call']);
    }

    public function test_it_ignores_text_that_is_not_a_tournament_hand(): void
    {
        $this->assertNull((new HandHistoryParser)->parse('nada que ver'));
    }
}
