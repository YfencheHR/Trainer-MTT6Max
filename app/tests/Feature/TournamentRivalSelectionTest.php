<?php

namespace Tests\Feature;

use App\Models\Bot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentRivalSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_lists_pool_profiles_but_not_primary_profiles(): void
    {
        $primary = $this->createBot('Perfil primario', 'tag');
        $pool = $this->createBot('Perfil Pool importado', 'pool', ['source' => 'pt4_import']);

        $response = $this->get(route('mtt-simulator.create'));

        $response->assertOk()
            ->assertSee($pool->nombre)
            ->assertSee('bot-percentage-'.$pool->id)
            ->assertDontSee($primary->nombre)
            ->assertDontSee('bot-percentage-'.$primary->id);
    }

    public function test_store_rejects_primary_profiles_as_opponents(): void
    {
        $primary = $this->createBot('Perfil primario', 'tag');

        $response = $this->post(route('mtt-simulator.store'), [
            'starting_stack' => 1000,
            'big_blind' => 20,
            'num_players' => 2,
            'speed' => 'tipica',
            'hands_per_level' => 10,
            'bot_percentages' => [$primary->id => 100],
            'hand_types' => [1],
            'player_time_mode' => 'unlimited',
        ]);

        $response->assertSessionHasErrors('bot_percentages');
        $this->assertDatabaseCount('tournaments', 0);
    }

    private function createBot(string $name, string $type, ?array $metadata = null): Bot
    {
        return Bot::create([
            'nombre' => $name,
            'tipo' => $type,
            'metadata' => $metadata,
        ]);
    }
}
