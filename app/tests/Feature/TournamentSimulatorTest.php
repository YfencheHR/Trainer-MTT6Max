<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\Tournament;
use App\Models\TournamentHand;
use App\Services\Tournament\TournamentFactory;
use App\Services\Tournament\TournamentSimulator;
use Database\Seeders\BotHudStatsSeeder;
use Database\Seeders\BotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Simulador sin cabeza: un torneo solo de bots se juega y termina con el mismo motor, y deja el historial. */
class TournamentSimulatorTest extends TestCase
{
    use RefreshDatabase;

    private function bots(): array
    {
        $this->seed([BotSeeder::class, BotHudStatsSeeder::class]);
        // Los perfiles Pool juegan con sus tablas preflop (se generan al decidir); los de los seeds, con las reglas del cerebro
        $bots = Bot::all();
        foreach ($bots as $bot) {
            $bot->update(['metadata' => ['source' => 'pt4_pool', 'template' => $bot->tipo]]);
        }

        return $bots->all();
    }

    private function settings(): array
    {
        return ['starting_stack' => 1000, 'big_blind' => 100, 'speed' => 'muy_rapida', 'hands_per_level' => 2, 'ante_by_level' => [0], 'hand_types' => [1]];
    }

    public function test_the_factory_allocates_exactly_the_requested_players_by_weight(): void
    {
        $bots = collect($this->bots())->keyBy('id');
        $ids = $bots->keys()->take(3)->values();

        $allocated = app(TournamentFactory::class)->allocateBots($bots, [$ids[0] => 50, $ids[1] => 30, $ids[2] => 20], 10);

        $this->assertCount(10, $allocated);
        $counts = collect($allocated)->countBy('id');
        $this->assertSame(5, $counts[$ids[0]]);
        $this->assertSame(3, $counts[$ids[1]]);
        $this->assertSame(2, $counts[$ids[2]]);
    }

    public function test_a_bots_only_tournament_is_played_to_the_end_and_recorded(): void
    {
        $bots = $this->bots();
        $factory = app(TournamentFactory::class);
        $tournament = $factory->create($this->settings(), array_slice($bots, 0, 6), null, false);
        $factory->start($tournament);

        $result = app(TournamentSimulator::class)->run($tournament, null, 400);

        $tournament->refresh();
        $this->assertTrue($result['finished'], 'El torneo termina con un único jugador');
        $this->assertSame('completed', $tournament->status);
        $this->assertSame(1, $tournament->players()->where('placement', 1)->count());
        $this->assertSame(0, $tournament->players()->where('is_bot', false)->count(), 'No hay héroe');
        $this->assertGreaterThan(5, TournamentHand::where('tournament_id', $tournament->id)->count());
        // Todos los jugadores tienen puesto distinto
        $this->assertSame(6, $tournament->players()->pluck('placement')->unique()->count());
        $this->assertDatabaseHas('hand_actions', ['street' => 'preflop']);
    }

    public function test_the_command_creates_simulated_tournaments_and_purge_removes_only_those(): void
    {
        $this->bots();
        $real = Tournament::create(['title' => 'Torneo real', 'status' => 'pending', 'config' => ['players' => []]]);

        $this->artisan('tournament:simulate', ['--players' => 4, '--stack' => 1000, '--speed' => 'muy_rapida', '--hands-per-level' => 2, '--max-rounds' => 400])
            ->assertSuccessful();

        $simulated = Tournament::all()->filter(fn($t) => data_get($t->config, 'simulated'));
        $this->assertCount(1, $simulated);
        $this->assertSame('completed', $simulated->first()->status);

        $this->artisan('tournament:simulate --purge')->assertSuccessful();

        $this->assertSame(0, Tournament::all()->filter(fn($t) => data_get($t->config, 'simulated'))->count());
        $this->assertNotNull(Tournament::find($real->id), 'El torneo real no se toca');
    }
}
