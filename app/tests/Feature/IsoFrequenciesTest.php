<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotPreflopHandRange;
use App\Services\Bots\Brain\PreflopStrategyMatrix;
use App\Services\Bots\RivalPreflopTables;
use Database\Seeders\BotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Con limpers delante y nadie que haya subido, el bot sube según lo que su perfil hace ante limpers (no según su frecuencia de abrir). */
class IsoFrequenciesTest extends TestCase
{
    use RefreshDatabase;

    private function bot(array $iso): Bot
    {
        $this->seed(BotSeeder::class);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();
        $bot->update(['metadata' => ['source' => 'pt4_pool']]);
        foreach ($iso as $position => [$opportunities, $raises, $calls]) {
            DB::table('bot_iso_stats')->insert(['bot_id' => $bot->id, 'position' => $position, 'opportunities' => $opportunities, 'raises' => $raises,
                'calls' => $calls, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $bot;
    }

    private function tables(): RivalPreflopTables
    {
        return app(RivalPreflopTables::class);
    }

    public function test_from_the_big_blind_the_best_hands_raise_and_the_rest_check(): void
    {
        $bot = $this->bot(['BB' => [400, 80, 0]]);   // sube el 20 % de las veces

        $best = $this->tables()->isoFrequencies($bot, 'BB', 'AA');
        $worst = $this->tables()->isoFrequencies($bot, 'BB', '72o');

        $this->assertSame(100.0, $best['raise']);
        $this->assertSame(0.0, $best['call']);
        $this->assertSame(0.0, $worst['raise']);
        $this->assertSame(100.0, $worst['call'], 'desde la ciega grande lo que no sube pasa (check)');
    }

    public function test_from_other_positions_the_middle_of_the_range_calls_and_the_rest_folds(): void
    {
        $bot = $this->bot(['SB' => [400, 100, 200]]);   // sube el 25 %, iguala el 50 %

        $best = $this->tables()->isoFrequencies($bot, 'SB', 'AA');
        $worst = $this->tables()->isoFrequencies($bot, 'SB', '72o');

        $this->assertSame(100.0, $best['raise']);
        $this->assertSame(0.0, $worst['raise']);
        $this->assertSame(0.0, $worst['call'], 'la peor mano ni sube ni iguala');
    }

    public function test_archived_frozen_copies_do_not_weigh_in_the_population(): void
    {
        $this->bot(['BB' => [400, 80, 0]]);   // la población viva sube el 20 %
        $newcomer = Bot::create(['nombre' => 'Nuevo', 'tipo' => 'pool', 'vpip' => 30, 'pfr' => 20]);
        $alone = $this->tables()->isoFrequencies($newcomer, 'BB', 'A5o');

        // Una copia congelada (archivada) con muchísima muestra que sube siempre: no debe mover la población
        $copy = Bot::create(['nombre' => 'Copia', 'tipo' => 'pool', 'vpip' => 30, 'pfr' => 20]);
        $copy->forceFill(['archived_at' => now()])->save();
        DB::table('bot_iso_stats')->insert(['bot_id' => $copy->id, 'position' => 'BB', 'opportunities' => 100000, 'raises' => 100000, 'calls' => 0,
            'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame($alone, $this->tables()->isoFrequencies($newcomer, 'BB', 'A5o'));

        // Lo mismo en un perfil vivo sí la mueve (la prueba detecta el cambio)
        $live = Bot::create(['nombre' => 'Vivo', 'tipo' => 'pool', 'vpip' => 30, 'pfr' => 20]);
        DB::table('bot_iso_stats')->insert(['bot_id' => $live->id, 'position' => 'BB', 'opportunities' => 100000, 'raises' => 100000, 'calls' => 0,
            'created_at' => now(), 'updated_at' => now()]);
        $this->assertNotSame($alone, $this->tables()->isoFrequencies($newcomer, 'BB', 'A5o'));
    }

    public function test_a_small_sample_is_pulled_toward_what_the_population_does_and_no_data_gives_nothing(): void
    {
        $bot = $this->bot(['BB' => [400, 80, 0]]);    // la población (solo este bot) sube el 20 %
        $other = Bot::create(['nombre' => 'Otro', 'tipo' => 'pool', 'vpip' => 30, 'pfr' => 20]);
        DB::table('bot_iso_stats')->insert(['bot_id' => $other->id, 'position' => 'BB', 'opportunities' => 2, 'raises' => 2, 'calls' => 0,
            'created_at' => now(), 'updated_at' => now()]);

        // 2 de 2: sin suavizar sería 100 %; con la población (20 %) y 20 «oportunidades» de peso queda cerca de 27 %
        $weak = $this->tables()->isoFrequencies($other, 'BB', 'AA');
        $this->assertSame(100.0, $weak['raise']);
        $middle = $this->tables()->isoFrequencies($other, 'BB', 'K9o');
        $this->assertLessThan(100.0, $middle['raise'], 'una mano media ya no sube con certeza');

        $this->assertNull($this->tables()->isoFrequencies(Bot::create(['nombre' => 'Sin', 'tipo' => 'pool', 'vpip' => 30, 'pfr' => 20]), 'UTG', 'AA'));
    }

    public function test_the_decision_uses_the_limp_table_only_when_there_are_limpers(): void
    {
        $bot = $this->bot(['BB' => [400, 80, 0]]);
        BotPreflopHandRange::create(['bot_id' => $bot->id, 'position' => 'BB', 'hand' => 'AA', 'attack_open' => 0, 'attack_limp' => 0, 'open_reraise' => 0,
            'open_call' => 0, 'open_fold' => 100, 'limp_raise' => 0, 'limp_push' => 0, 'defense_three_bet' => 10, 'defense_call' => 60, 'defense_fold' => 30, 'is_manual' => true]);
        $matrix = app(PreflopStrategyMatrix::class);
        $features = ['position' => 'BB', 'hand_code' => 'AA', 'stack_bb' => 50, 'preflop_raises' => 0];

        $limped = $matrix->forPoolDecision($bot, $features + ['limpers' => 2]);
        $first = $matrix->forPoolDecision($bot, $features + ['limpers' => 0]);

        $this->assertSame('pool_vs_limp', $limped['scenario']);
        $this->assertSame(['open_2_5bb' => 100.0], $limped['distribution'], 'AA desde la ciega grande con limpers: sube');
        $this->assertSame('pool_attack', $first['scenario'], 'sin limpers sigue siendo abrir la acción');
    }
}
