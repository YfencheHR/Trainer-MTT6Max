<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotHudStat;
use App\Models\BotPreflopHandRange;
use App\Models\HandGroup;
use App\Models\HandGroupValue;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Services\Bots\BotFreezer;
use App\Services\Bots\Brain\PreflopStrategyMatrix;
use App\Services\Bots\RivalPreflopTables;
use App\Services\Import\PoolBotRetirer;
use App\Services\Tournament\TournamentFactory;
use Database\Seeders\BotHudStatsSeeder;
use Database\Seeders\BotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recalcular una importación (o editar/borrar un perfil) no debe romper torneos ni sus historiales:
 *  A. los perfiles Pool usados en torneos se archivan en vez de borrarse;
 *  B. cada torneo juega con una copia congelada del perfil, creada al crear el torneo.
 */
class FrozenBotsTest extends TestCase
{
    use RefreshDatabase;

    private int $tournaments = 0;

    private function poolBot(string $tipo = 'tag'): Bot
    {
        $this->seed([BotSeeder::class, BotHudStatsSeeder::class]);
        $bot = Bot::where('tipo', $tipo)->firstOrFail();
        // Como las copias que crea el importador: tipo 'pool' y el tipo de plantilla en los metadatos
        $bot->update(['tipo' => 'pool', 'metadata' => ['source' => 'pt4_pool', 'template' => $tipo]]);

        return $bot->fresh();
    }

    private function tournament(): Tournament
    {
        return Tournament::create([
            'title' => 'MTT-frozen-' . ++$this->tournaments,
            'status' => 'pending',
            'config' => ['basic_settings' => ['starting_stack' => 1000, 'num_players' => 2, 'big_blind' => 20], 'players' => [], 'game_settings' => []],
        ]);
    }

    /** Crea los jugadores como lo hace el controlador al crear un torneo. */
    private function seat(Tournament $tournament, array $bots): void
    {
        app(TournamentFactory::class)->seatPlayers($tournament, $bots, 1000, null);
    }

    // ------------------------------------------------------------------ B. copias congeladas

    public function test_freezing_creates_an_archived_copy_with_the_same_stats_and_tables(): void
    {
        $bot = $this->poolBot();

        $copy = app(BotFreezer::class)->frozenFor($bot);

        $this->assertNotSame($bot->id, $copy->id);
        $this->assertTrue($copy->isArchived());
        $this->assertTrue($copy->isPool(), 'La copia de un Pool sigue siendo Pool: el juego usa sus tablas preflop');
        $this->assertSame($bot->nombre, $copy->nombre);
        $this->assertSame($bot->id, $copy->metadata['frozen_from']);
        $this->assertSame($bot->hudStats()->count(), $copy->hudStats()->count());
        $this->assertSame($bot->preflopHandRanges()->count(), $copy->preflopHandRanges()->count());
        $this->assertGreaterThan(0, $copy->preflopHandRanges()->count());
    }

    public function test_the_same_profile_reuses_one_copy_and_a_changed_profile_gets_a_new_one(): void
    {
        $bot = $this->poolBot();
        $freezer = app(BotFreezer::class);

        $first = $freezer->frozenFor($bot);
        $again = $freezer->frozenFor($bot->fresh());
        $this->assertSame($first->id, $again->id, 'Un perfil idéntico reutiliza la misma copia');

        $general = $bot->hudStats()->whereNull('posicion')->whereNull('accion')->first();
        $general->update(['vpip' => ($general->vpip ?? 20) + 7]);

        $second = $freezer->frozenFor($bot->fresh());
        $this->assertNotSame($first->id, $second->id, 'Si el perfil cambió, se congela una copia nueva');
        $this->assertSame(2, Bot::withoutGlobalScope('active')->whereNotNull('archived_at')->count());
    }

    public function test_the_frozen_copy_carries_what_the_profile_does_in_limped_and_raised_pots(): void
    {
        $bot = $this->poolBot();
        $row = ['street' => 'flop', 'pot' => 'limp', 'position' => 'any', 'stack_bucket' => 'all', 'open_opps' => 100, 'opens' => 25,
            'facing' => 80, 'folds' => 50, 'calls' => 25, 'raises' => 5, 'created_at' => now(), 'updated_at' => now()];
        \Illuminate\Support\Facades\DB::table('bot_postflop_pot_stats')->insert($row + ['bot_id' => $bot->id]);
        $freezer = app(BotFreezer::class);

        $first = $freezer->frozenFor($bot);
        $copied = \Illuminate\Support\Facades\DB::table('bot_postflop_pot_stats')->where('bot_id', $first->id)->first();
        $this->assertNotNull($copied, 'la copia congelada también lleva esas estadísticas: el Perceiver las lee con su id');
        $this->assertSame(25, (int) $copied->opens);

        \Illuminate\Support\Facades\DB::table('bot_postflop_pot_stats')->where('bot_id', $bot->id)->update(['opens' => 40]);
        $this->assertNotSame($first->id, $freezer->frozenFor($bot->fresh())->id, 'si cambian, se congela una copia nueva');
    }

    public function test_editing_the_live_profile_afterwards_does_not_change_the_frozen_copy(): void
    {
        $bot = $this->poolBot();
        $copy = app(BotFreezer::class)->frozenFor($bot);
        $before = $copy->hudStats()->whereNull('posicion')->whereNull('accion')->first()->vpip;

        $general = $bot->hudStats()->whereNull('posicion')->whereNull('accion')->first();
        $general->update(['vpip' => 99]);

        $this->assertSame($before, $copy->fresh()->hudStats()->whereNull('posicion')->whereNull('accion')->first()->vpip);
    }

    public function test_tournament_players_play_with_the_frozen_copy_and_keep_the_source_profile(): void
    {
        $bot = $this->poolBot();
        $tournament = $this->tournament();

        $this->seat($tournament, [$bot, $bot]);

        $players = TournamentPlayer::where('tournament_id', $tournament->id)->get();
        $this->assertCount(2, $players);
        foreach ($players as $player) {
            $this->assertNotSame($bot->id, $player->bot_id, 'El jugador juega con la copia congelada');
            $this->assertTrue($player->bot->isArchived());
            $this->assertSame($bot->id, $player->source_bot_id);
            $this->assertSame($bot->id, $player->sourceBot->id);
        }
        $this->assertSame($players[0]->bot_id, $players[1]->bot_id, 'El mismo perfil en dos asientos comparte copia');
    }

    public function test_a_tournament_keeps_working_after_its_live_profile_is_deleted(): void
    {
        $bot = $this->poolBot();
        $tournament = $this->tournament();
        $this->seat($tournament, [$bot]);
        $player = TournamentPlayer::where('tournament_id', $tournament->id)->firstOrFail();

        // Es lo que hacía "Recalcular desde cero": borrar el perfil y sus estadísticas
        BotHudStat::where('bot_id', $bot->id)->delete();
        $bot->delete();

        $player = TournamentPlayer::with('bot.hudStats')->findOrFail($player->id);
        $this->assertNotNull($player->bot, 'El juego sigue teniendo bot');
        $this->assertGreaterThan(0, $player->bot->hudStats->count(), 'con sus estadísticas');
        $this->assertNull($player->source_bot_id, 'solo se pierde el enlace al perfil vivo');
        $this->assertNotNull(app(RivalPreflopTables::class)->frequenciesFor($player->bot, 'BTN', 'AA'), 'y con sus tablas preflop');
    }

    public function test_the_brain_can_read_an_archived_bot_by_id(): void
    {
        $bot = $this->poolBot();
        $hand = HandGroup::create(['group_name' => 'AA', 'hand_type' => 'Pair', 'gto_ev' => 50, 'hands_in_group' => [], 'combinatorios' => 6]);
        foreach (['Opener', 'Defense'] as $scenario) {
            HandGroupValue::create([
                'hand_group_id' => $hand->id, 'scenario' => $scenario, 'positions' => ['BTN'], 'stack_depth_min' => 1,
                'stack_depth_max' => 255, 'ev' => 0, 'actions' => ['fold' => ['frequency_pct' => 100, 'ev_bb' => 0]],
            ]);
        }
        BotPreflopHandRange::updateOrCreate(['bot_id' => $bot->id, 'position' => 'BTN', 'hand' => 'AA'], [
            'attack_open' => 40, 'attack_limp' => 20, 'open_reraise' => 10, 'open_call' => 30, 'open_fold' => 60,
            'limp_raise' => 12, 'limp_push' => 20, 'defense_three_bet' => 15, 'defense_call' => 55, 'defense_fold' => 30, 'is_manual' => true,
        ]);
        $copy = app(BotFreezer::class)->frozenFor($bot);

        $decision = app(PreflopStrategyMatrix::class)->forDecision(
            ['me' => ['bot_id' => $copy->id]],
            ['street' => 'preflop', 'position' => 'BTN', 'stack_bb' => 50, 'hand_code' => 'AA', 'preflop_raises' => 0, 'limpers' => 0]
        );

        $this->assertNotNull($decision, 'Una copia archivada se lee por su id: si no, el bot ignoraría sus tablas');
        $this->assertSame('pool_tables', $decision['source']);
    }

    // ------------------------------------------------------------------ A. archivar en vez de borrar

    public function test_retire_archives_used_pool_profiles_and_deletes_the_unused_ones(): void
    {
        $this->seed([BotSeeder::class, BotHudStatsSeeder::class]);
        $primary = Bot::where('tipo', 'nit')->firstOrFail();
        $used = Bot::where('tipo', 'tag')->firstOrFail();
        $unused = Bot::where('tipo', 'lag')->firstOrFail();
        $used->update(['tipo' => 'pool', 'metadata' => ['source' => 'pt4_pool', 'template' => 'tag']]);
        $unused->update(['tipo' => 'pool', 'metadata' => ['source' => 'pt4_import']]);

        // Un torneo anterior a las copias congeladas: el jugador apunta directamente al perfil
        $tournament = $this->tournament();
        TournamentPlayer::create(['tournament_id' => $tournament->id, 'bot_id' => $used->id, 'name' => 'Rival', 'is_bot' => true, 'starting_stack' => 1000, 'current_stack' => 1000]);

        $result = (new PoolBotRetirer())->retire();

        $this->assertSame(['archived' => 1, 'deleted' => 1], $result);
        $this->assertNotNull(Bot::withoutGlobalScope('active')->find($used->id)?->archived_at, 'El usado se archiva');
        $this->assertGreaterThan(0, BotHudStat::where('bot_id', $used->id)->count(), 'y conserva sus estadísticas');
        $this->assertNull(Bot::withoutGlobalScope('active')->find($unused->id), 'El que nadie usa se borra');
        $this->assertNotNull(Bot::find($primary->id), 'Los bots primarios no se tocan');

        $player = TournamentPlayer::firstOrFail();
        $this->assertSame($used->id, $player->bot_id, 'El torneo conserva su enlace al perfil');
        $this->assertNotNull($player->bot);
    }

    public function test_archived_bots_are_hidden_from_lists_and_the_tournament_selection(): void
    {
        $bot = $this->poolBot();
        $copy = app(BotFreezer::class)->frozenFor($bot);

        $this->assertTrue(Bot::all()->doesntContain('id', $copy->id));
        $this->assertNull(Bot::find($copy->id));
        $this->assertNotNull(Bot::withoutGlobalScope('active')->find($copy->id));

        $this->get(route('mtt-simulator.create'))->assertOk()->assertDontSee('bot-percentage-' . $copy->id);
    }

    public function test_deleting_a_used_profile_from_the_interface_archives_it(): void
    {
        $bot = $this->poolBot();
        $tournament = $this->tournament();
        TournamentPlayer::create(['tournament_id' => $tournament->id, 'bot_id' => $bot->id, 'name' => 'Rival', 'is_bot' => true, 'starting_stack' => 1000, 'current_stack' => 1000]);

        $this->delete(route('bots.destroy', $bot))->assertRedirect(route('bots.index'));

        $this->assertNotNull(Bot::withoutGlobalScope('active')->find($bot->id)?->archived_at);
        $this->assertNotNull(TournamentPlayer::first()->bot);

        $free = Bot::where('tipo', 'lag')->firstOrFail();
        $this->delete(route('bots.destroy', $free));
        $this->assertNull(Bot::withoutGlobalScope('active')->find($free->id), 'Un perfil sin torneos sí se borra');
    }

    public function test_it_counts_the_tournaments_that_use_the_current_pool_profiles(): void
    {
        $bot = $this->poolBot();
        $first = $this->tournament();
        $second = $this->tournament();
        foreach ([$first, $first, $second] as $tournament) {
            TournamentPlayer::create(['tournament_id' => $tournament->id, 'bot_id' => $bot->id, 'name' => 'Rival', 'is_bot' => true, 'starting_stack' => 1000, 'current_stack' => 1000]);
        }

        $this->assertSame(2, (new PoolBotRetirer())->tournamentsAffected(), 'Cuenta torneos, no jugadores');
    }
}
