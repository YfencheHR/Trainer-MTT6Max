<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\Tournament;
use App\Models\TournamentHand;
use App\Models\TournamentPlayer;
use App\Services\Tournament\TournamentRoundService;
use Database\Seeders\BotHudStatsSeeder;
use Database\Seeders\BotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lo que queda guardado de una mano terminada entre bots: bote, resultado y listado del torneo. */
class HandLogSavedTest extends TestCase
{
    use RefreshDatabase;

    private function playBotsHand(): TournamentHand
    {
        $this->seed([BotSeeder::class, BotHudStatsSeeder::class]);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();
        $tournament = Tournament::create([
            'title' => 'MTT-log', 'status' => 'pending',
            'config' => ['basic_settings' => ['starting_stack' => 3000, 'num_players' => 3, 'big_blind' => 100],
                'blind_structure' => ['speed' => 'tipica', 'hands_per_level' => 10, 'antes' => [0]], 'players' => [], 'game_settings' => []],
        ]);
        foreach ([0, 1, 2] as $position) {
            TournamentPlayer::create(['tournament_id' => $tournament->id, 'bot_id' => $bot->id, 'name' => "Bot {$position}", 'starting_stack' => 3000,
                'current_stack' => 3000, 'is_bot' => true, 'position' => $position]);
        }
        $this->post(route('mtt-simulator.start', $tournament));
        $table = $tournament->tables()->firstOrFail();
        app(TournamentRoundService::class)->resolveTable($tournament->fresh(), $table, 1);

        return TournamentHand::where('tournament_id', $tournament->id)->where('status', 'completed')->firstOrFail();
    }

    public function test_a_finished_hand_between_bots_saves_its_pot_and_does_not_count_as_a_loss(): void
    {
        $hand = $this->playBotsHand();

        $this->assertGreaterThan(0, $hand->getTotalPot(), 'el bote de la última calle jugada queda guardado');
        $this->assertSame('bots', $hand->result, 'sin jugador humano no hay victoria ni derrota');
        $this->assertTrue($hand->handActions()->exists());
        $this->assertTrue($hand->handPlayers()->exists());
    }

    public function test_the_tournament_page_lists_the_winner_and_the_pot_of_each_hand(): void
    {
        $hand = $this->playBotsHand();
        $winner = collect($hand->players_in_hand)->firstWhere('id', array_key_first($hand->actions['awards']));

        $this->get(route('mtt-simulator.show', $hand->tournament_id))->assertOk()->assertSee('🏆 ' . $winner['name'], false);
    }
}
