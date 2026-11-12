<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentStartFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_tournament_redirects_directly_to_the_table(): void
    {
        $tournament = Tournament::create([
            'title' => 'MTT-start-flow',
            'status' => 'pending',
            'config' => [
                'basic_settings' => ['starting_stack' => 1000, 'num_players' => 2, 'big_blind' => 20],
                'blind_structure' => ['speed' => 'tipica', 'hands_per_level' => 10, 'antes' => [0]],
                'players' => [],
                'game_settings' => [],
            ],
        ]);

        foreach (['Player', 'Opponent'] as $position => $name) {
            TournamentPlayer::create([
                'tournament_id' => $tournament->id,
                'name' => $name,
                'starting_stack' => 1000,
                'current_stack' => 1000,
                'is_bot' => $position > 0,
                'position' => $position,
            ]);
        }

        $response = $this->post(route('mtt-simulator.start', $tournament));

        $response->assertRedirect(route('mtt-simulator.play', $tournament));
        $this->assertSame('in_progress', $tournament->fresh()->status);
        $this->assertDatabaseHas('tables', [
            'tournament_id' => $tournament->id,
            'is_active' => true,
        ]);
    }
}
