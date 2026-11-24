<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Services\Tournament\TournamentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** La memoria de los bots con IA se elige al crear el torneo y queda en su configuración. */
class BotMemorySettingTest extends TestCase
{
    use RefreshDatabase;

    private function settings(array $extra = []): array
    {
        return $extra + [
            'starting_stack' => 1000, 'big_blind' => 20, 'num_players' => 2, 'speed' => 'tipica', 'hands_per_level' => 10,
            'ante_by_level' => [0], 'hand_types' => [1],
        ];
    }

    private function config(array $extra = []): array
    {
        $bot = Bot::create(['nombre' => 'B', 'tipo' => 'tag', 'vpip' => 24, 'pfr' => 18]);

        return app(TournamentFactory::class)->create($this->settings($extra), [$bot, $bot], null, false)->config;
    }

    public function test_the_form_offers_the_memory_options(): void
    {
        $this->get(route('mtt-simulator.create'))
            ->assertOk()
            ->assertSee('name="bot_memory"', false)
            ->assertSee(__('tournaments.bot_memory_tournament'));
    }

    public function test_by_default_the_bots_have_no_memory(): void
    {
        $this->assertSame('none', $this->config()['game_settings']['bot_memory']);
    }

    public function test_the_chosen_memory_is_stored_in_the_tournament_configuration(): void
    {
        $this->assertSame('tournament', $this->config(['bot_memory' => 'tournament'])['game_settings']['bot_memory']);
    }
}
