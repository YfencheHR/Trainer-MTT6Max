<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotPreflopHandRange;
use App\Services\Bots\Brain\PreflopStrategyMatrix;
use App\Services\Bots\BotFreezer;
use App\Services\Bots\RivalPreflopTables;
use Database\Seeders\BotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Tras limpear y recibir una subida el bot puede igualar (la mitad de la gente lo hace), no solo subir o foldear. */
class LimpResponseTest extends TestCase
{
    use RefreshDatabase;

    private function bot(array $stats = []): Bot
    {
        $this->seed(BotSeeder::class);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();
        $bot->update(['metadata' => ['source' => 'pt4_pool']]);
        foreach ($stats as $position => [$opportunities, $folds, $calls, $raises]) {
            DB::table('bot_limp_response_stats')->insert(['bot_id' => $bot->id, 'position' => $position, 'opportunities' => $opportunities,
                'folds' => $folds, 'calls' => $calls, 'raises' => $raises, 'created_at' => now(), 'updated_at' => now()]);
        }
        // Rango de limp del bot en la ciega pequeña: todas las manos que usa el test
        foreach (['AA' => 40, 'T9s' => 40, '72o' => 40] as $hand => $limp) {
            BotPreflopHandRange::create(['bot_id' => $bot->id, 'position' => 'SB', 'hand' => $hand, 'attack_open' => 0, 'attack_limp' => $limp, 'open_reraise' => 0,
                'open_call' => 0, 'open_fold' => 100, 'limp_raise' => 0, 'limp_push' => 0, 'defense_three_bet' => 0, 'defense_call' => 0, 'defense_fold' => 100, 'is_manual' => true]);
        }

        return $bot;
    }

    private function tables(): RivalPreflopTables
    {
        return app(RivalPreflopTables::class);
    }

    public function test_without_data_everything_that_does_not_raise_is_a_fold_as_before(): void
    {
        $bot = $this->bot();

        $this->assertSame(['call' => 0.0, 'fold' => 90.0], $this->tables()->limpContinuation($bot, 'SB', '72o', 7.0, 3.0));
    }

    public function test_the_worst_hands_of_the_limp_range_fold_and_the_best_ones_call(): void
    {
        $bot = $this->bot(['SB' => [1000, 450, 520, 30]]);   // foldea el 45 %, iguala el 52 %

        $best = $this->tables()->limpContinuation($bot, 'SB', 'AA', 0.0, 0.0);
        $worst = $this->tables()->limpContinuation($bot, 'SB', '72o', 0.0, 0.0);

        $this->assertSame(['call' => 100.0, 'fold' => 0.0], $best, 'la mejor mano de su rango de limp iguala');
        $this->assertSame(['call' => 0.0, 'fold' => 100.0], $worst, 'la peor foldea');
    }

    public function test_what_already_raises_or_shoves_is_not_split_again(): void
    {
        $bot = $this->bot(['SB' => [1000, 450, 520, 30]]);

        $answer = $this->tables()->limpContinuation($bot, 'SB', 'T9s', 20.0, 10.0);

        $this->assertEqualsWithDelta(70.0, $answer['call'] + $answer['fold'], 0.01, 'solo se reparte lo que queda tras subir y hacer all-in');
    }

    public function test_archived_frozen_copies_do_not_weigh_in_the_population(): void
    {
        $this->bot(['SB' => [1000, 450, 520, 30]]);   // la población viva foldea el 45 %
        $newcomer = Bot::create(['nombre' => 'Nuevo', 'tipo' => 'pool', 'vpip' => 30, 'pfr' => 20]);
        $alone = $this->tables()->limpContinuation($newcomer, 'SB', 'T9s', 0.0, 0.0);

        $copy = Bot::create(['nombre' => 'Copia', 'tipo' => 'pool', 'vpip' => 30, 'pfr' => 20]);
        $copy->forceFill(['archived_at' => now()])->save();
        DB::table('bot_limp_response_stats')->insert(['bot_id' => $copy->id, 'position' => 'SB', 'opportunities' => 100000, 'folds' => 100000,
            'calls' => 0, 'raises' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame($alone, $this->tables()->limpContinuation($newcomer, 'SB', 'T9s', 0.0, 0.0));
        $this->assertEqualsWithDelta(55.0, $alone['call'], 0.5, 'sin muestra propia se reparte como la población viva: fold 45 %, call 55 %');
    }

    public function test_the_decision_after_limping_now_includes_calling(): void
    {
        $bot = $this->bot(['SB' => [1000, 450, 520, 30]]);
        BotPreflopHandRange::where(['bot_id' => $bot->id, 'position' => 'SB', 'hand' => 'AA'])->update(['limp_raise' => 10, 'limp_push' => 5]);

        $d = app(PreflopStrategyMatrix::class)->forPoolDecision($bot, ['position' => 'SB', 'hand_code' => 'AA', 'stack_bb' => 50, 'preflop_raises' => 1, 'limpers' => 1, 'i_limped_preflop' => true]);

        $this->assertSame('pool_limp_response', $d['scenario']);
        $this->assertArrayHasKey('call', $d['distribution']);
        $this->assertEqualsWithDelta(85.0, $d['distribution']['call'], 0.5, 'AA: lo que no sube ni hace all-in, lo iguala');
        $this->assertEqualsWithDelta(100.0, array_sum($d['distribution']), 0.1);
    }

    public function test_the_frozen_copy_carries_the_responses_and_a_change_makes_a_new_copy(): void
    {
        $bot = $this->bot(['SB' => [1000, 450, 520, 30]]);
        $freezer = app(BotFreezer::class);

        $first = $freezer->frozenFor($bot);
        $this->assertSame(1000, (int) DB::table('bot_limp_response_stats')->where('bot_id', $first->id)->value('opportunities'));

        DB::table('bot_limp_response_stats')->where('bot_id', $bot->id)->update(['calls' => 600]);
        $this->assertNotSame($first->id, $freezer->frozenFor($bot->fresh())->id);
    }
}
