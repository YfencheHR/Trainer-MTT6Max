<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\HandReplay;
use App\Models\Tournament;
use App\Models\TournamentHand;
use App\Models\TournamentPlayer;
use App\Services\Replay\HandReplayService;
use App\Services\Tournament\TournamentRoundService;
use Database\Seeders\BotHudStatsSeeder;
use Database\Seeders\BotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Repetir una mano jugada: mismas cartas, decisiones nuevas, el torneo no se toca y todo queda guardado para analizarlo. */
class HandReplayTest extends TestCase
{
    use RefreshDatabase;

    private function playedHand(): TournamentHand
    {
        $this->seed([BotSeeder::class, BotHudStatsSeeder::class]);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();
        $tournament = Tournament::create([
            'title' => 'MTT-replay', 'status' => 'pending',
            'config' => ['basic_settings' => ['starting_stack' => 3000, 'num_players' => 3, 'big_blind' => 100],
                'blind_structure' => ['speed' => 'tipica', 'hands_per_level' => 10, 'antes' => [0]], 'players' => [], 'game_settings' => []],
        ]);
        foreach ([0, 1, 2] as $position) {
            TournamentPlayer::create(['tournament_id' => $tournament->id, 'bot_id' => $bot->id, 'name' => "Bot {$position}", 'starting_stack' => 3000,
                'current_stack' => 3000, 'is_bot' => true, 'position' => $position]);
        }
        $this->post(route('mtt-simulator.start', $tournament));
        app(TournamentRoundService::class)->resolveTable($tournament->fresh(), $tournament->tables()->firstOrFail(), 1);

        return TournamentHand::where('tournament_id', $tournament->id)->where('status', 'completed')->firstOrFail();
    }

    public function test_replaying_a_hand_saves_both_versions_and_leaves_the_tournament_untouched(): void
    {
        $hand = $this->playedHand();
        $stacks = TournamentPlayer::where('tournament_id', $hand->tournament_id)->orderBy('id')->pluck('current_stack', 'id')->all();
        $handsBefore = TournamentHand::count();
        $actionsBefore = \DB::table('hand_actions')->count();

        $replays = app(HandReplayService::class)->replay($hand, 3);

        $this->assertCount(3, $replays);
        $this->assertSame($stacks, TournamentPlayer::where('tournament_id', $hand->tournament_id)->orderBy('id')->pluck('current_stack', 'id')->all(), 'las fichas del torneo no cambian');
        $this->assertSame($handsBefore, TournamentHand::count(), 'la copia de la mano se deshace');
        $this->assertSame($actionsBefore, \DB::table('hand_actions')->count(), 'el historial del torneo no recibe las acciones de la repetición');

        $replay = $replays[0]->fresh();
        $this->assertSame($replays[0]->batch_id, $replays[2]->batch_id, 'las repeticiones pedidas de una vez comparten lote');
        $this->assertGreaterThan(0, $replay->original_actions);
        $this->assertGreaterThan(0, $replay->replay_actions);
        $this->assertSame($replay->original_actions, $replay->actions()->where('side', 'original')->count());
        $this->assertSame($replay->replay_actions, $replay->actions()->where('side', 'replay')->count());
        $this->assertNotEmpty($replay->replay_result['winners']);
        $this->assertSame($hand->actions['pot_won'], $replay->original_result['pot']);
    }

    public function test_the_bots_decide_again_with_the_same_cards(): void
    {
        $hand = $this->playedHand();
        $first = $hand->handActions()->whereIn('type', ['fold', 'check', 'call', 'raise', 'allin'])->orderBy('sequence')->firstOrFail();

        $replay = app(HandReplayService::class)->replay($hand, 1)[0];
        $firstReplayed = $replay->actions()->where('side', 'replay')->firstOrFail();

        $this->assertSame((int) $first->tournament_player_id, (int) $firstReplayed->tournament_player_id, 'el primero en hablar es el mismo (mismo botón y asientos)');
        $this->assertNotNull($firstReplayed->distribution, 'queda la distribución con la que decidió el bot');
    }

    public function test_the_divergence_points_at_the_first_action_that_changes(): void
    {
        $service = app(HandReplayService::class);
        $a = fn ($player, $action, $amount = 0, $street = 'preflop') => ['player_id' => $player, 'player_name' => "P{$player}", 'street' => $street, 'action' => $action, 'amount' => $amount];
        $played = [$a(1, 'raise', 300), $a(2, 'call', 300), $a(1, 'raise', 500, 'flop')];

        $this->assertNull($service->divergence($played, $played)['first']);
        $this->assertSame(3, $service->divergence($played, $played)['same_prefix']);

        $other = [$a(1, 'raise', 300), $a(2, 'fold')];
        $result = $service->divergence($played, $other);
        $this->assertSame(1, $result['same_prefix']);
        $this->assertSame(2, $result['first']['index']);
        $this->assertSame('call', $result['first']['played']['action']);
        $this->assertSame('fold', $result['first']['replayed']['action']);

        $shorter = $service->divergence($played, [$a(1, 'raise', 300), $a(2, 'call', 300)]);
        $this->assertSame(3, $shorter['first']['index']);
        $this->assertNull($shorter['first']['replayed'], 'la repetición termina antes');
    }

    public function test_the_page_lists_the_saved_replays_and_the_button_runs_new_ones(): void
    {
        $hand = $this->playedHand();

        $this->get(route('mtt-simulator.hand.replays', $hand))->assertOk()->assertSee(__('tournaments.replay_none'));
        $this->post(route('mtt-simulator.hand.replays.store', $hand), ['times' => 2])->assertRedirect(route('mtt-simulator.hand.replays', $hand));

        $this->assertSame(2, HandReplay::where('tournament_hand_id', $hand->id)->count());
        $this->get(route('mtt-simulator.hand.replays', $hand))->assertOk()->assertSee(__('tournaments.replay_stats'))->assertDontSee(__('tournaments.replay_none'));
        $this->post(route('mtt-simulator.hand.replays.store', $hand), ['times' => 500])->assertSessionHasErrors('times');
    }

    public function test_the_history_links_each_hand_to_its_replays(): void
    {
        $hand = $this->playedHand();

        $this->get(route('mtt-simulator.history', $hand->tournament_id))->assertOk()->assertSee(route('mtt-simulator.hand.replays', $hand), false);
    }

    public function test_the_table_frames_of_the_played_hand_reproduce_it_exactly(): void
    {
        $hand = $this->playedHand();
        $decisions = $hand->handActions()->whereIn('type', ['fold', 'check', 'call', 'raise', 'allin'])->count();
        $handsBefore = TournamentHand::count();

        $frames = $this->getJson(route('mtt-simulator.hand.replays.frames', $hand))->assertOk()->json('data.frames');

        $this->assertCount($decisions + 1, $frames, 'un estado antes de actuar y otro por cada acción');
        $this->assertSame('preflop', $frames[0]['current_street']);
        $this->assertNull($frames[0]['decision']);
        $last = end($frames);
        $this->assertSame($hand->community_cards, $last['board'], 'el board que se vio en la mano jugada');
        $this->assertSame('completed', $last['status']);
        $this->assertSame((int) $hand->actions['pot_won'], (int) $last['result']['total'], 'termina con el mismo bote');
        $this->assertSame(array_keys($hand->actions['awards']), array_column($last['result']['winners'], 'id'), 'y gana quien ganó');
        $this->assertTrue(collect($frames[0]['players'])->every(fn ($p) => count($p['hole_cards']) === 2), 'se ven las cartas de todos');
        $this->assertSame($handsBefore, TournamentHand::count(), 'la copia de trabajo se deshace');
    }

    public function test_the_frames_of_a_saved_replay_follow_its_own_actions(): void
    {
        $hand = $this->playedHand();
        $replay = app(HandReplayService::class)->replay($hand, 1)[0];

        $frames = $this->getJson(route('mtt-simulator.hand.replays.frames', ['hand' => $hand, 'version' => $replay->id]))->assertOk()->json('data.frames');

        $this->assertCount($replay->replay_actions + 1, $frames);
        $this->assertSame(
            $replay->actions()->where('side', 'replay')->orderBy('seq')->pluck('action')->all(),
            collect($frames)->skip(1)->pluck('decision.action')->all(),
        );
        $this->getJson(route('mtt-simulator.hand.replays.frames', ['hand' => $hand, 'version' => 999999]))->assertNotFound();
    }

    public function test_each_finished_hand_in_the_tournament_page_opens_its_detail_with_the_replay_button(): void
    {
        $hand = $this->playedHand();

        $this->get(route('mtt-simulator.show', $hand->tournament_id))->assertOk()
            ->assertSee(route('mtt-simulator.hand.replays', $hand), false)
            ->assertSee(__('tournaments.replay_open'));
    }
}
