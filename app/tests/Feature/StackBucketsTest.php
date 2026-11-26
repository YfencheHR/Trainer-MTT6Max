<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotHudStat;
use App\Models\BotPreflopHandRange;
use App\Services\Bots\BotFreezer;
use App\Services\Bots\Brain\BotProfile;
use App\Services\Bots\Brain\PreflopStrategyMatrix;
use App\Services\Bots\RivalPreflopTables;
use Database\Seeders\BotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tablas preflop por tramo de stack: se generan con las estadísticas de cada tramo y el bot usa la de su stack. */
class StackBucketsTest extends TestCase
{
    use RefreshDatabase;

    private function bot(): Bot
    {
        $this->seed(BotSeeder::class);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();
        $bot->update(['metadata' => ['source' => 'pt4_pool']]);
        $key = ['bot_id' => $bot->id, 'nivel' => 1, 'posicion' => null, 'accion' => null, 'rango_ciegas' => null];
        BotHudStat::updateOrCreate($key, ['vpip' => 30, 'pfr' => 20, 'three_bet' => 8, 'rfi' => 30, 'cold_call' => 12, 'fold_to_3bet' => 60, 'four_bet' => 8, 'shove' => 5, 'sample_size' => 5000]);
        // Más 3-bet, más apertura y shoves con stack corto; lo contrario con stack profundo (la tendencia que se ve en los datos)
        foreach ([
            '0-10' => ['vpip' => 40, 'pfr' => 25, 'three_bet' => 24, 'rfi' => 52, 'cold_call' => 3, 'fold_to_3bet' => 50, 'four_bet' => 16, 'shove' => 28],
            '10-20' => ['vpip' => 36, 'pfr' => 21, 'three_bet' => 13, 'rfi' => 40, 'cold_call' => 7, 'fold_to_3bet' => 58, 'four_bet' => 10, 'shove' => 11],
            '20-40' => ['vpip' => 31, 'pfr' => 19, 'three_bet' => 9, 'rfi' => 32, 'cold_call' => 11, 'fold_to_3bet' => 61, 'four_bet' => 8, 'shove' => 4],
            '40+' => ['vpip' => 28, 'pfr' => 17, 'three_bet' => 6, 'rfi' => 26, 'cold_call' => 15, 'fold_to_3bet' => 64, 'four_bet' => 6, 'shove' => 2],
        ] as $bucket => $values) {
            BotHudStat::create($values + ['bot_id' => $bot->id, 'nivel' => 1, 'posicion' => null, 'accion' => BotProfile::STACK_ACTION, 'rango_ciegas' => $bucket, 'sample_size' => 1000]);
        }

        return $bot;
    }

    private function mass(RivalPreflopTables $tables, Bot $bot, string $position, string $bucket, callable $pick): float
    {
        $total = $sum = 0.0;
        foreach (collect($tables->generate($bot, false, $bucket)[$position]['attack'])->pluck('hand') as $hand) {
            $weight = strlen($hand) === 2 ? 6 : (str_ends_with($hand, 's') ? 4 : 12);
            $sum += $weight * $pick($tables->frequenciesFor($bot, $position, $hand, $bucket));
            $total += $weight;
        }

        return $sum / $total;
    }

    public function test_it_generates_a_table_per_stack_bucket_and_short_stacks_open_and_three_bet_more(): void
    {
        $bot = $this->bot();
        $tables = app(RivalPreflopTables::class);
        $tables->forBot($bot);

        foreach (['all', '0-10', '10-20', '20-40', '40+'] as $bucket) {
            $this->assertSame(6 * 169, BotPreflopHandRange::where(['bot_id' => $bot->id, 'stack_bucket' => $bucket])->count(), $bucket);
        }

        $open = fn (string $bucket) => $this->mass($tables, $bot, 'BTN', $bucket, fn ($f) => $f['attack']['open']);
        $three = fn (string $bucket) => $this->mass($tables, $bot, 'CO', $bucket, fn ($f) => $f['defense']['three_bet']);
        $this->assertGreaterThan($open('40+'), $open('0-10'));
        $this->assertGreaterThan($three('40+') * 2, $three('0-10'));
        $this->assertGreaterThan($three('20-40'), $three('10-20'));
    }

    public function test_the_decision_uses_the_table_of_the_stack_and_pushes_all_in_when_short(): void
    {
        $bot = $this->bot();
        app(RivalPreflopTables::class)->forBot($bot);
        $matrix = app(PreflopStrategyMatrix::class);
        $decide = fn (float $stack) => $matrix->forDecision(['me' => ['bot_id' => $bot->id]], [
            'street' => 'preflop', 'position' => 'BTN', 'hand_code' => 'A5s', 'preflop_raises' => 0, 'limpers' => 0, 'stack_bb' => $stack,
        ]);

        $this->assertSame('0-10', $decide(8)['stack_bucket']);
        $this->assertSame('40+', $decide(60)['stack_bucket']);
        $short = $decide(8)['distribution'];
        $this->assertArrayHasKey('allin', $short, 'Con 8 bb las subidas son all-in');
        $this->assertArrayNotHasKey('allin', $decide(60)['distribution']);
    }

    public function test_a_manual_edit_of_the_general_table_wins_at_every_stack(): void
    {
        $bot = $this->bot();
        $tables = app(RivalPreflopTables::class);
        $tables->forBot($bot);
        BotPreflopHandRange::where(['bot_id' => $bot->id, 'stack_bucket' => 'all', 'position' => 'BTN', 'hand' => '72o'])
            ->update(['attack_open' => 77, 'is_manual' => true]);

        $this->assertEqualsWithDelta(77, $tables->frequenciesFor($bot, 'BTN', '72o', '0-10')['attack']['open'], 0.01);
    }

    public function test_frozen_copies_keep_the_tables_of_every_stack(): void
    {
        $bot = $this->bot();
        app(RivalPreflopTables::class)->forBot($bot);

        $copy = app(BotFreezer::class)->frozenFor($bot);

        $this->assertSame(5 * 6 * 169, BotPreflopHandRange::where('bot_id', $copy->id)->count());
        $this->assertSame(4, BotHudStat::where('bot_id', $copy->id)->where('accion', BotProfile::STACK_ACTION)->count());
    }
}
