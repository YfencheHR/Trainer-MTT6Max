<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Services\Bots\Brain\Perceiver;
use App\Services\Bots\Brain\PostflopPotStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

/** Lo que el perfil hace en botes limpeados (importado de PokerTracker) sustituye al donk / c-bet cuando nadie subió preflop. */
class PostflopPotStatsTest extends TestCase
{
    use RefreshDatabase;

    private function cell(int $botId, string $street, string $position, string $bucket, array $counts): void
    {
        DB::table('bot_postflop_pot_stats')->insert(array_merge([
            'bot_id' => $botId, 'street' => $street, 'pot' => 'limp', 'position' => $position, 'stack_bucket' => $bucket,
            'open_opps' => 0, 'opens' => 0, 'facing' => 0, 'folds' => 0, 'calls' => 0, 'raises' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ], $counts));
    }

    private function rows(int $botId): array
    {
        PostflopPotStats::flush();

        return PostflopPotStats::forBot($botId);
    }

    private function bot(): Bot
    {
        return Bot::create(['nombre' => 'B', 'tipo' => 'tag', 'vpip' => 24, 'pfr' => 18]);
    }

    public function test_without_enough_sample_there_is_no_data(): void
    {
        $bot = $this->bot();
        $this->cell($bot->id, 'flop', 'any', 'all', ['open_opps' => 29, 'opens' => 20, 'facing' => 29, 'folds' => 10, 'calls' => 10, 'raises' => 9]);
        $rows = $this->rows($bot->id);

        $this->assertNull(PostflopPotStats::open($rows, 'flop', 'limp', true, 50));
        $this->assertNull(PostflopPotStats::facing($rows, 'flop', 'limp', true, 50));
        $this->assertNull(PostflopPotStats::open($rows, 'turn', 'limp', true, 50), 'sin celda no hay dato');
    }

    public function test_each_level_pulls_toward_the_one_above_in_proportion_to_its_sample(): void
    {
        $bot = $this->bot();
        $this->cell($bot->id, 'flop', 'any', 'all', ['open_opps' => 200, 'opens' => 40]);                // 20 %
        $this->cell($bot->id, 'flop', 'ip', 'all', ['open_opps' => 40, 'opens' => 20]);                  // 50 % con 40 manos
        $this->cell($bot->id, 'flop', 'ip', '0-10', ['open_opps' => 160, 'opens' => 0]);                 // 0 % con 160 manos
        $rows = $this->rows($bot->id);

        $this->assertSame(20.0, PostflopPotStats::open($rows, 'flop', 'limp', false, 50)['bet'], 'oop: solo la base');
        // ip: (20 + 40 × 0,2) / (40 + 40) = 0,35 → tramo 0-10: (0 + 40 × 0,35) / (160 + 40) = 0,07
        $this->assertSame(35.0, PostflopPotStats::open($rows, 'flop', 'limp', true, 50)['bet'], 'ip con stack profundo: sin celda de ese tramo');
        $this->assertSame(7.0, PostflopPotStats::open($rows, 'flop', 'limp', true, 5)['bet']);
        $this->assertSame(200, PostflopPotStats::open($rows, 'flop', 'limp', true, 5)['n']);
    }

    public function test_the_answer_to_a_bet_adds_up_to_one_hundred(): void
    {
        $bot = $this->bot();
        $this->cell($bot->id, 'flop', 'any', 'all', ['facing' => 100, 'folds' => 60, 'calls' => 32, 'raises' => 8]);
        $this->cell($bot->id, 'flop', 'oop', 'all', ['facing' => 50, 'folds' => 40, 'calls' => 8, 'raises' => 2]);

        $answer = PostflopPotStats::facing($this->rows($bot->id), 'flop', 'limp', false, 30);

        $this->assertEqualsWithDelta(100.0, $answer['fold'] + $answer['call'] + $answer['raise'], 0.2);
        $this->assertGreaterThan(60.0, $answer['fold'], 'oop foldea más que la media');
        $this->assertSame(100, $answer['n']);
    }

    private function perceive(Bot $bot, array $history, int $toCall = 0, string $position = 'BTN'): array
    {
        $ctx = [
            'street' => 'flop', 'big_blind' => 100, 'pot' => 400, 'to_call' => $toCall, 'required_equity' => $toCall ? 0.3 : 0.0,
            'board' => ['K♣', '7♥', '2♦'],
            'me' => ['id' => 1, 'bot_id' => $bot->id, 'position' => $position, 'stack' => 5000, 'bet' => 0, 'hole_cards' => ['A♠', 'Q♦'], 'flags' => [],
                'stats' => ['vpip' => 24, 'pfr' => 18, 'flop' => ['donk' => 4, 'cbet' => 65, 'f2bet' => 40, 'raise_vs_bet' => 10]]],
            'players' => [
                ['id' => 1, 'position' => $position, 'stack' => 5000, 'folded' => false, 'hole_cards' => ['A♠', 'Q♦'], 'stats' => ['vpip' => 24, 'pfr' => 18]],
                ['id' => 2, 'position' => 'BB', 'stack' => 5000, 'folded' => false, 'stats' => ['vpip' => 30, 'pfr' => 20, 'af' => 2.0, 'hands' => 100], 'postflop_stats' => []],
            ],
            'history' => $history,
            'equity_samples' => 100,
            'legal' => ['raise' => ['min' => 200, 'max' => 5000]],
        ];

        return app(Perceiver::class)->perceive($ctx, [
            'skill' => ['noise' => ['bluff_sigma' => 0, 'equity_sigma' => 0, 'rank_sigma' => 0], 'equity_samples' => 100],
        ], new Randomizer(new Mt19937(7)))['features'];
    }

    public function test_in_a_limped_pot_the_bot_bets_with_its_limped_pot_frequency_instead_of_the_donk(): void
    {
        $bot = $this->bot();
        $this->cell($bot->id, 'flop', 'any', 'all', ['open_opps' => 100, 'opens' => 25, 'facing' => 100, 'folds' => 60, 'calls' => 34, 'raises' => 6]);
        PostflopPotStats::flush();
        $limped = [['player_id' => 2, 'street' => 'preflop', 'action' => 'call', 'amount' => 100], ['player_id' => 1, 'street' => 'preflop', 'action' => 'call', 'amount' => 100]];

        $f = $this->perceive($bot, $limped);

        $this->assertSame(25.0, $f['own_bet_freq'], 'su frecuencia en botes limpeados, no su donk del 4 %');
        $this->assertSame('bote_limpeado_ip', $f['stats_source']);

        $facing = $this->perceive($bot, array_merge($limped, [['player_id' => 2, 'street' => 'flop', 'action' => 'raise', 'amount' => 200]]), 200);
        $this->assertSame(60.0, $facing['own_f2bet']);
        $this->assertSame(15.0, $facing['own_raise_vs_bet'], '6 de cada 40 que continúan');
    }

    public function test_in_a_raised_pot_or_without_data_nothing_changes(): void
    {
        $bot = $this->bot();
        $this->cell($bot->id, 'flop', 'any', 'all', ['open_opps' => 100, 'opens' => 25]);
        PostflopPotStats::flush();
        $raised = [['player_id' => 2, 'street' => 'preflop', 'action' => 'raise', 'amount' => 250], ['player_id' => 1, 'street' => 'preflop', 'action' => 'call', 'amount' => 250]];
        $limped = [['player_id' => 2, 'street' => 'preflop', 'action' => 'call', 'amount' => 100]];
        $other = Bot::create(['nombre' => 'Otro', 'tipo' => 'tag', 'vpip' => 24, 'pfr' => 18]);

        $this->assertNotSame(25.0, $this->perceive($bot, $raised)['own_bet_freq']);
        $this->assertSame(4.0, (float) $this->perceive($other, $limped)['own_bet_freq'], 'sin datos: el donk de siempre');
    }
}
