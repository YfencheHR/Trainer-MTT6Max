<?php

namespace Tests\Unit;

use App\Models\TournamentPlayerStat;
use App\Services\Ai\BotMemory;
use App\Services\Stats\TournamentStatsService;
use PHPUnit\Framework\TestCase;

/** Lo que se observa de cada jugador por calle (apostar cuando nadie ha apostado, foldear ante una apuesta) y cómo lo usa la memoria de los bots. */
class ObservedStreetStatsTest extends TestCase
{
    private function deltas(array $history): array
    {
        $players = [['id' => 1, 'folded' => false], ['id' => 2, 'folded' => false]];

        return (new TournamentStatsService)->computeDeltas($players, ['history' => $history, 'bb' => 100, 'street' => 'turn'], [], ['K♣', '7♥', '2♦', '9♠']);
    }

    public function test_it_counts_bets_with_nothing_to_face_and_folds_against_a_bet_per_street(): void
    {
        $d = $this->deltas([
            ['street' => 'preflop', 'player_id' => 2, 'action' => 'call', 'amount' => 100],
            ['street' => 'flop', 'player_id' => 1, 'action' => 'check', 'amount' => 0],
            ['street' => 'flop', 'player_id' => 2, 'action' => 'raise', 'amount' => 150],
            ['street' => 'flop', 'player_id' => 1, 'action' => 'call', 'amount' => 150],
            ['street' => 'turn', 'player_id' => 1, 'action' => 'raise', 'amount' => 300],
            ['street' => 'turn', 'player_id' => 2, 'action' => 'fold', 'amount' => 0],
        ]);

        $this->assertSame(1, $d[1]['flop_bet_opp']);
        $this->assertSame(0, $d[1]['flop_bet'], 'pasó en el flop');
        $this->assertSame(1, $d[2]['flop_bet']);
        $this->assertSame(1, $d[1]['flop_f2bet_opp']);
        $this->assertSame(0, $d[1]['flop_f2bet'], 'pagó la apuesta');
        $this->assertSame(1, $d[1]['turn_bet']);
        $this->assertSame(1, $d[2]['turn_f2bet']);
        $this->assertSame(0, $d[1]['river_bet_opp']);
    }

    public function test_the_summary_gives_percentages_with_their_samples(): void
    {
        $stat = new TournamentPlayerStat(['hands' => 10, 'turn_bet_opp' => 4, 'turn_bet' => 1, 'river_f2bet_opp' => 5, 'river_f2bet' => 2, 'aggressive_actions' => 6, 'passive_actions' => 3]);
        $summary = $stat->summary();

        $this->assertSame(['bet' => 25, 'bet_opp' => 4, 'f2bet' => null, 'f2bet_opp' => 0], $summary['streets']['turn']);
        $this->assertSame(40, $summary['streets']['river']['f2bet']);
        $this->assertSame(9, $summary['af_n']);
    }

    public function test_the_memory_corrects_the_fold_to_a_bet_and_the_betting_of_each_street(): void
    {
        $profile = ['preflop' => ['vpip' => 20], 'postflop' => ['turn' => ['f2bet' => 40, 'bet' => 50, 'af' => 2.0], 'vb_ratio' => 2.0]];
        $observed = ['hands' => 50, 'vpip' => 20, 'af' => 4.0, 'af_n' => 25, 'streets' => ['turn' => ['bet' => 10, 'bet_opp' => 25, 'f2bet' => 80, 'f2bet_opp' => 75]]];

        $mixed = (new BotMemory)->apply($profile, $observed, BotMemory::TOURNAMENT);

        $this->assertSame(70.0, $mixed['postflop']['turn']['f2bet'], '75 oportunidades: 75/100 de 80 y el resto de 40');
        $this->assertSame(30.0, $mixed['postflop']['turn']['bet'], '25 oportunidades: la mitad de 10 y la mitad de 50');
        $this->assertSame(3.0, $mixed['postflop']['turn']['af']);
        $this->assertSame(2.0, $mixed['postflop']['vb_ratio'], 'lo que no se observa no se toca');
        $this->assertSame(['af' => 4.0], $mixed['postflop']['river'], 'sin perfil en esa calle solo se usa la agresión observada, que es de todas las calles');
    }
}
