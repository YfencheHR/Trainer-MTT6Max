<?php

namespace Tests\Feature;

use App\Services\Bots\Brain\BoardTexture;
use App\Services\Bots\Brain\BoardTextureStats;
use App\Services\Bots\Brain\Perceiver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

/** La textura del board mueve lo que cada perfil apuesta, foldea y sube (multiplicadores poblacionales de PokerTracker). */
class BoardTextureTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_classifies_the_texture_of_a_board(): void
    {
        $this->assertSame('A|D|S|N', BoardTexture::classify(['A♠', '7♦', '2♣']));
        $this->assertSame('K|D|P|N', BoardTexture::classify(['K♠', 'K♦', '2♣']));
        $this->assertSame('QJT|C|S|N', BoardTexture::classify(['J♠', 'T♦', '8♣']));
        $this->assertSame('LOW|C|S|N', BoardTexture::classify(['9♠', '8♦', '6♣']));
        $this->assertSame('QJT|D|S|F', BoardTexture::classify(['Q♠', '7♠', '2♠']));
        $this->assertSame('A|C|S|N', BoardTexture::classify(['A♠', '3♦', '4♣']), 'el as también cuenta como carta baja de la escalera');
        $this->assertNull(BoardTexture::classify(['A♠', '7♦']));
    }

    public function test_a_turn_or_river_needs_four_connected_cards_to_count_as_connected(): void
    {
        $this->assertSame('K|D|S|N', BoardTexture::classify(['K♠', '7♦', '2♣', '9♥']));
        $this->assertSame('K|C|S|N', BoardTexture::classify(['K♠', 'Q♦', 'J♣', 'T♥']));
    }

    public function test_the_card_ids_of_the_pt4_dump_classify_like_the_real_cards(): void
    {
        // Th 9s 7s del TSV: ids 35, 47, 45 del volcado → rango ((id-1) % 13), palo (id-1) / 13
        $ranks = array_map(fn ($id) => ($id - 1) % 13, [35, 47, 45]);
        $suits = array_map(fn ($id) => intdiv($id - 1, 13), [35, 47, 45]);

        $this->assertSame(BoardTexture::classify(['T♥', '9♠', '7♠']), BoardTexture::fromValues($ranks, $suits));
    }

    private function texture(string $texture, array $counts): void
    {
        DB::table('board_texture_stats')->insert(array_merge([
            'street' => 'flop', 'texture' => $texture, 'created_at' => now(), 'updated_at' => now(),
        ], $counts));
        BoardTextureStats::flush();
    }

    public function test_multipliers_are_relative_to_the_street_average_smoothed_by_the_sample_and_bounded(): void
    {
        // La calle: 1.000 oportunidades de abrir con 300 aperturas (30 %). La clase A: 1.000 con 450 (45 %); la B: 1.000 con 150 (15 %).
        $this->texture('A|D|S|N', ['open_opps' => 1000, 'opens' => 450]);
        $this->texture('LOW|C|S|N', ['open_opps' => 1000, 'opens' => 150]);
        $this->texture('K|D|S|N', ['open_opps' => 1000, 'opens' => 300 * 0]);   // aporta la media

        $a = BoardTextureStats::multipliers('flop', ['A♠', '7♦', '2♣']);
        $low = BoardTextureStats::multipliers('flop', ['9♠', '8♦', '6♣']);

        $this->assertGreaterThan(1.0, $a['open']);
        $this->assertLessThan(1.0, $low['open']);
        $this->assertGreaterThanOrEqual(BoardTextureStats::MIN, $low['open']);
        $this->assertLessThanOrEqual(BoardTextureStats::MAX, $a['open']);
        $this->assertNull(BoardTextureStats::multipliers('flop', ['2♠', '2♦', '2♣']), 'una clase sin datos no cambia nada');
        $this->assertNull(BoardTextureStats::multipliers('turn', ['A♠', '7♦', '2♣', '9♥']), 'sin datos de esa calle');
    }

    private function features(array $board, array $history, int $toCall): array
    {
        $ctx = [
            'street' => count($board) === 3 ? 'flop' : 'turn', 'big_blind' => 100, 'pot' => 600, 'to_call' => $toCall, 'required_equity' => $toCall ? 0.3 : 0.0,
            'board' => $board,
            'me' => ['id' => 1, 'position' => 'BTN', 'stack' => 5000, 'bet' => 0, 'hole_cards' => ['A♠', 'Q♦'], 'flags' => [],
                'stats' => ['vpip' => 24, 'pfr' => 18, 'flop' => ['donk' => 10, 'cbet' => 60, 'f2bet' => 50, 'raise_vs_bet' => 10]]],
            'players' => [
                ['id' => 1, 'position' => 'BTN', 'stack' => 5000, 'folded' => false, 'hole_cards' => ['A♠', 'Q♦'], 'stats' => ['vpip' => 24, 'pfr' => 18]],
                ['id' => 2, 'position' => 'BB', 'stack' => 5000, 'folded' => false, 'stats' => ['vpip' => 30, 'pfr' => 20, 'af' => 2.0, 'hands' => 100], 'postflop_stats' => []],
            ],
            'history' => $history, 'equity_samples' => 100, 'legal' => ['raise' => ['min' => 200, 'max' => 5000]],
        ];

        return app(Perceiver::class)->perceive($ctx, [
            'skill' => ['noise' => ['bluff_sigma' => 0, 'equity_sigma' => 0, 'rank_sigma' => 0], 'equity_samples' => 100],
        ], new Randomizer(new Mt19937(7)))['features'];
    }

    public function test_the_aggressor_bets_more_on_a_texture_that_favours_it_and_less_on_one_that_does_not(): void
    {
        $this->texture('A|D|S|N', ['cbet_opps' => 1000, 'cbets' => 800, 'cdef_opps' => 500, 'cdef_folds' => 200, 'cdef_raises' => 20]);
        $this->texture('LOW|C|S|N', ['cbet_opps' => 1000, 'cbets' => 400, 'cdef_opps' => 500, 'cdef_folds' => 150, 'cdef_raises' => 90]);
        $raised = [['player_id' => 1, 'street' => 'preflop', 'action' => 'raise', 'amount' => 250], ['player_id' => 2, 'street' => 'preflop', 'action' => 'call', 'amount' => 250]];

        $dry = $this->features(['A♣', '7♦', '2♥'], $raised, 0);
        $low = $this->features(['9♣', '8♦', '6♥'], $raised, 0);

        $this->assertSame('A|D|S|N', $dry['board_texture']);
        $this->assertGreaterThan($low['own_bet_freq'], $dry['own_bet_freq'], 'mismo jugador, c-bet distinto según el board');
        $this->assertGreaterThan(60.0, $dry['own_bet_freq']);
        $this->assertLessThan(60.0, $low['own_bet_freq']);
    }

    public function test_the_defender_folds_less_and_raises_more_on_a_connected_low_board(): void
    {
        $this->texture('A|D|S|N', ['cbet_opps' => 1000, 'cbets' => 800, 'cdef_opps' => 500, 'cdef_folds' => 300, 'cdef_raises' => 20]);
        $this->texture('LOW|C|S|N', ['cbet_opps' => 1000, 'cbets' => 400, 'cdef_opps' => 500, 'cdef_folds' => 150, 'cdef_raises' => 90]);
        // El rival (id 2) subió preflop y apuesta: el bot (id 1) defiende
        $raised = [['player_id' => 2, 'street' => 'preflop', 'action' => 'raise', 'amount' => 250], ['player_id' => 1, 'street' => 'preflop', 'action' => 'call', 'amount' => 250]];

        $dry = $this->features(['A♣', '7♦', '2♥'], array_merge($raised, [['player_id' => 2, 'street' => 'flop', 'action' => 'raise', 'amount' => 300]]), 300);
        $low = $this->features(['9♣', '8♦', '6♥'], array_merge($raised, [['player_id' => 2, 'street' => 'flop', 'action' => 'raise', 'amount' => 300]]), 300);

        $this->assertGreaterThan($low['own_f2bet'], $dry['own_f2bet'], 'en el board seco foldea más');
        $this->assertGreaterThan($dry['own_raise_vs_bet'], $low['own_raise_vs_bet'], 'en el bajo y conectado sube más');
    }

    public function test_without_texture_data_the_statistics_are_not_touched(): void
    {
        $raised = [['player_id' => 1, 'street' => 'preflop', 'action' => 'raise', 'amount' => 250], ['player_id' => 2, 'street' => 'preflop', 'action' => 'call', 'amount' => 250]];

        $f = $this->features(['A♣', '7♦', '2♥'], $raised, 0);

        $this->assertSame(60.0, (float) $f['own_bet_freq']);
        $this->assertArrayNotHasKey('board_texture', $f);
    }
}
