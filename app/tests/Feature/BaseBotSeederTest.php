<?php

namespace Tests\Feature;

use App\Models\Bot;
use Database\Seeders\BotHudStatsSeeder;
use Database\Seeders\BotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaseBotSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_rivals_and_stats_are_seeded_without_duplicates(): void
    {
        $this->seed(BotSeeder::class);
        $this->seed(BotHudStatsSeeder::class);
        $this->seed(BotSeeder::class);
        $this->seed(BotHudStatsSeeder::class);

        $this->assertDatabaseCount('bots', 9);
        $this->assertDatabaseCount('bot_hud_stats', 9);
        $this->assertSame(
            'setminer-v001',
            Bot::where('tipo', 'setminer')->value('version_rango'),
        );
    }
}
