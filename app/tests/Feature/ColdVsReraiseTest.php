<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotHudStat;
use App\Models\BotPreflopHandStat;
use App\Services\Bots\Brain\PreflopStrategyMatrix;
use App\Services\Bots\RivalPreflopTables;
use Database\Seeders\BotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Un bot Pool que no abrió y se encuentra un 3-bet o 4-bet continúa con mucho menos que contra una sola apertura. */
class ColdVsReraiseTest extends TestCase
{
    use RefreshDatabase;

    private function bot(): Bot
    {
        $this->seed(BotSeeder::class);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();
        $bot->update(['metadata' => ['source' => 'pt4_pool']]);
        app(RivalPreflopTables::class)->forBot($bot);

        return $bot;
    }

    private function decide(Bot $bot, string $hand, int $raises): array
    {
        return app(PreflopStrategyMatrix::class)->forDecision(['me' => ['bot_id' => $bot->id]], [
            'street' => 'preflop', 'position' => 'BTN', 'hand_code' => $hand, 'preflop_raises' => $raises,
            'i_raised_preflop' => false, 'i_limped_preflop' => false,
        ]);
    }

    public function test_it_uses_the_cold_response_instead_of_the_defense_against_an_open(): void
    {
        $bot = $this->bot();

        $this->assertSame('pool_defense', $this->decide($bot, 'AA', 1)['scenario']);
        $this->assertSame('pool_cold_vs_reraise', $this->decide($bot, 'AA', 2)['scenario']);
        $this->assertSame('pool_cold_vs_reraise', $this->decide($bot, 'AA', 3)['scenario']);
    }

    public function test_how_much_it_continues_comes_from_the_profile_stats_and_observed_hands_shift_the_hand(): void
    {
        $bot = $this->bot();
        $tables = app(RivalPreflopTables::class);
        $continuing = fn() => collect(["AA","KK","QQ","JJ","TT","99","88","AKs","AKo","AQs","AQo","AJs","KQs","A5s","76s"])
            ->sum(fn($h) => 100 - $tables->coldVsReraise($bot, "BTN", $h, 2)["fold"]);

        BotHudStat::updateOrCreate(["bot_id" => $bot->id, "nivel" => 1, "posicion" => null, "accion" => null, "rango_ciegas" => null], ["cold3_fold" => 97, "cold3_call" => 1, "cold3_raise" => 2]);
        $bot->unsetRelation("hudStats");
        $tables->forBot($bot);
        $tight = $continuing();

        BotHudStat::updateOrCreate(["bot_id" => $bot->id, "nivel" => 1, "posicion" => null, "accion" => null, "rango_ciegas" => null], ["cold3_fold" => 70, "cold3_call" => 12, "cold3_raise" => 18]);
        $bot->unsetRelation("hudStats");
        $tables->forBot($bot);
        $wide = $continuing();
        $this->assertGreaterThan($tight, $wide, "Un perfil que sigue más veces contra un 3-bet continúa con más manos");

        // Un perfil que casi nunca sigue, pero al que se le han visto subir AJs varias veces en esa situación, lo hace más que sin esa evidencia
        BotHudStat::updateOrCreate(["bot_id" => $bot->id, "nivel" => 1, "posicion" => null, "accion" => null, "rango_ciegas" => null], ["cold3_fold" => 97, "cold3_call" => 1, "cold3_raise" => 2]);
        $bot->unsetRelation("hudStats");
        $tables->forBot($bot);
        $before = $tables->coldVsReraise($bot, "BTN", "AJs", 2)["raise"];
        BotPreflopHandStat::create(["bot_id" => $bot->id, "posicion" => "BTN", "mano" => "AJs", "cold3_opportunities" => 12, "cold3_calls" => 0, "cold3_raises" => 12]);
        $bot->unsetRelation("hudStats");
        $tables->forBot($bot);
        $this->assertGreaterThan($before, $tables->coldVsReraise($bot, "BTN", "AJs", 2)["raise"]);
    }

    public function test_premium_continue_and_marginal_hands_fold_and_four_bet_is_tighter_than_three_bet(): void
    {
        $bot = $this->bot();
        $tables = app(RivalPreflopTables::class);

        foreach (['AA', 'KK'] as $hand) {
            $fold = $this->decide($bot, $hand, 2)['distribution']['fold'] ?? 0;
            $this->assertLessThan(1, $fold, "$hand no foldea contra un 3-bet");
        }

        $continuing = fn(int $raises) => collect(array_keys((array) json_decode(file_get_contents(storage_path('app/brain/preflop-ranking.json')), true)))
            ->sum(fn($h) => 100 - $tables->coldVsReraise($bot, 'BTN', (string) $h, $raises)['fold']);

        $this->assertGreaterThan($continuing(3), $continuing(2), 'Continúa con menos manos contra un 4-bet que contra un 3-bet');
        // Mucho menos que contra una apertura
        $defendOpen = collect($tables->generate($bot)['BTN']['defense'])->sum(fn($c) => $c['three_bet'] + $c['call']);
        $this->assertLessThan($defendOpen, $continuing(2));
        // Una mano marginal que sí defiende una apertura ya foldea
        $this->assertGreaterThan(50, $tables->coldVsReraise($bot, 'BTN', 'KJo', 2)['fold']);
    }
}
