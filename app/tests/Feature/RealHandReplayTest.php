<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Services\Replay\RealHandReplay;
use Database\Seeders\BotHudStatsSeeder;
use Database\Seeders\BotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Réplica de decisiones reales: motor, muestreo y resumen. */
class RealHandReplayTest extends TestCase
{
    use RefreshDatabase;

    private const HAND = <<<'TXT'
PokerStars Hand #%d: Tournament #2, $1 USD Hold'em No Limit - Level I (10/20) - 2025/12/20 15:23:15 CET [2025/12/20 9:23:15 ET]
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

    private function bots(): array
    {
        $this->seed([BotSeeder::class, BotHudStatsSeeder::class]);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();
        $bot->update(['tipo' => 'pool', 'metadata' => ['source' => 'pt4_pool', 'template' => 'tag']]);

        return ['Alice' => $bot->fresh(), 'Carol' => $bot->fresh()];
    }

    private function hands(int $count): array
    {
        return array_map(fn($i) => sprintf(self::HAND, 1000 + $i), range(1, $count));
    }

    private function replay(): RealHandReplay
    {
        return app(RealHandReplay::class);
    }

    public function test_it_compares_every_decision_of_a_mapped_player_with_what_the_person_did(): void
    {
        $result = $this->replay()->run($this->hands(1), $this->bots(), ['candidates' => 4, 'limit' => 100]);

        $this->assertSame(0, $result['errors'], (string) $result['first_error']);
        $alice = array_values(array_filter($result['rows'], fn($r) => $r['player'] === 'Alice'));
        $this->assertSame(['preflop', 'flop'], array_column($alice, 'street'));
        $this->assertSame(['aggr', 'aggr'], array_column($alice, 'real'), 'subió preflop y apostó el flop');
        $this->assertTrue($alice[0]['known'], 'las cartas de Alice se conocen');
        foreach ($result['rows'] as $row) {
            $this->assertEqualsWithDelta(1.0, array_sum($row['dist']), 0.001);
        }
    }

    public function test_marginal_mode_ignores_the_known_cards_and_uses_candidate_hands_for_everyone(): void
    {
        $result = $this->replay()->run($this->hands(1), $this->bots(), ['candidates' => 4, 'marginal' => true]);

        $this->assertSame([false], array_values(array_unique(array_column($result['rows'], 'known'))));
    }

    public function test_it_samples_one_of_every_n_hands_by_hash_instead_of_stopping_at_the_beginning(): void
    {
        $bots = $this->bots();
        $hands = $this->hands(60);

        $all = $this->replay()->run($hands, $bots, ['candidates' => 0, 'every' => 1]);
        $some = $this->replay()->run($hands, $bots, ['candidates' => 0, 'every' => 6]);

        $this->assertGreaterThan(count($some['rows']), count($all['rows']));
        $this->assertGreaterThan(0, count($some['rows']));
        $this->assertSame($some['rows'], $this->replay()->run($hands, $bots, ['candidates' => 0, 'every' => 6])['rows'], 'la muestra es repetible');
    }

    public function test_the_limit_stops_the_run(): void
    {
        $result = $this->replay()->run($this->hands(30), $this->bots(), ['candidates' => 0, 'limit' => 4]);

        $this->assertGreaterThanOrEqual(4, count($result['rows']));
        $this->assertLessThan(8, count($result['rows']));
    }

    public function test_it_does_not_reseed_the_global_random_generator(): void
    {
        mt_srand(12345);
        $expected = (function () {
            mt_srand(12345);

            return [mt_rand(), mt_rand()];
        })();
        mt_srand(12345);

        $this->replay()->run($this->hands(1), $this->bots(), ['candidates' => 6, 'marginal' => true]);

        // Si la réplica hubiera llamado a mt_srand, el generador global ya no seguiría la secuencia de la semilla 12345 desde su inicio
        $this->assertSame($expected, [mt_rand(), mt_rand()]);
    }

    public function test_the_scenario_of_a_row_is_the_most_frequent_one_among_its_candidate_hands(): void
    {
        $result = $this->replay()->run($this->hands(1), $this->bots(), ['candidates' => 6, 'marginal' => true, 'streets' => ['preflop']]);

        foreach ($result['rows'] as $row) {
            $this->assertNotNull($row['scenario']);
        }
    }

    public function test_the_summary_gives_accuracy_with_its_confidence_interval(): void
    {
        $hit = ['street' => 'flop', 'spot' => 'ante apuesta', 'pot' => 'limpeado', 'known' => false, 'real' => 'fold', 'dist' => ['fold' => 0.8, 'check' => 0.0, 'call' => 0.2, 'aggr' => 0.0]];
        $miss = ['real' => 'call'] + $hit;
        $rows = array_merge(array_fill(0, 30, $hit), array_fill(0, 10, $miss));

        $lines = RealHandReplay::summarize($rows);

        $this->assertSame('TODAS', $lines[0]['name']);
        $this->assertSame(40, $lines[0]['n']);
        $this->assertSame(75.0, $lines[0]['accuracy']);
        $this->assertEqualsWithDelta(13.4, $lines[0]['ci'], 0.1, '1,96 × √(0,75 × 0,25 / 40)');
        $this->assertContains('calle flop', array_column($lines, 'name'));
        $this->assertSame([], RealHandReplay::summarize([]));
    }
}
