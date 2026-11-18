<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotHudStat;
use App\Services\HandPlayService;
use Database\Seeders\BotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/** El bot decide con SUS estadísticas postflop (fold ante apuesta por calle, subida, c-bet...), no con valores por defecto. */
class BotOwnPostflopStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_bot_gets_its_own_postflop_stats_in_the_decision_context(): void
    {
        $this->seed(BotSeeder::class);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();
        BotHudStat::updateOrCreate(
            ['bot_id' => $bot->id, 'nivel' => 1, 'posicion' => null, 'accion' => null, 'rango_ciegas' => null],
            ['vpip' => 24, 'pfr' => 18, 'f2cbet' => 71, 'f2beturn' => 63, 'f2betriver' => 58, 'cbet' => 66, 'beturn' => 44, 'raise_vs_bet' => 17, 'sample_size' => 3000]
        );

        $stats = (new ReflectionMethod(HandPlayService::class, 'botStatsFor'))
            ->invoke(app(HandPlayService::class), $bot->fresh(), []);

        $this->assertEquals(71, $stats['flop']['f2bet']);
        $this->assertEquals(63, $stats['turn']['f2bet']);
        $this->assertEquals(58, $stats['river']['f2bet']);
        $this->assertEquals(66, $stats['flop']['cbet']);
        $this->assertEquals(44, $stats['turn']['bet'], 'La frecuencia de apuesta de turn y river sale de su estadística');
        $this->assertNotNull($stats['flop']['raise_vs_bet']);
    }
}
