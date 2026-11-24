<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Services\Ai\PreflopTargets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El objetivo que se le da a la IA preflop sale de las tablas del perfil del bot. */
class PreflopTargetsTest extends TestCase
{
    use RefreshDatabase;

    private function player(Bot $bot, int $id = 1): TournamentPlayer
    {
        $tournament = Tournament::create([
            'title' => 'MTT-preflop', 'status' => 'pending',
            'config' => ['basic_settings' => ['starting_stack' => 10000, 'num_players' => 2, 'big_blind' => 100], 'players' => [], 'game_settings' => []],
        ]);

        return TournamentPlayer::create(['tournament_id' => $tournament->id, 'bot_id' => $bot->id, 'name' => $bot->nombre, 'is_bot' => true, 'starting_stack' => 10000, 'current_stack' => 10000]);
    }

    /** Decisión de un bot que abre desde el CO con todos delante foldeados. */
    private function openingCtx(Bot $bot, TournamentPlayer $player, array $hole): array
    {
        return [
            'hand_id' => 1, 'hand_number' => 1, 'street' => 'preflop', 'big_blind' => 100, 'pot' => 150, 'current_bet' => 100, 'to_call' => 100,
            'required_equity' => 0.4, 'board' => [],
            'me' => ['id' => $player->id, 'bot_id' => $bot->id, 'position' => 'CO', 'stack' => 5000, 'bet' => 0, 'total_in_hand' => 0, 'hole_cards' => $hole, 'flags' => [], 'stats' => ['vpip' => 24, 'pfr' => 18]],
            'players' => [
                ['id' => $player->id, 'position' => 'CO', 'stack' => 5000, 'total_in_hand' => 0, 'bet' => 0, 'folded' => false, 'all_in' => false],
                ['id' => 999, 'position' => 'BB', 'stack' => 5000, 'total_in_hand' => 100, 'bet' => 100, 'folded' => false, 'all_in' => false],
            ],
            'history' => [],
            'legal' => ['fold' => true, 'check' => false, 'call' => 100, 'raise' => ['min' => 200, 'max' => 5000], 'allin' => 5000],
        ];
    }

    public function test_a_bot_with_tables_gives_what_its_profile_does_with_all_its_hands_and_with_this_one(): void
    {
        $bot = Bot::create(['nombre' => 'Pool', 'tipo' => 'tag', 'vpip' => 24, 'pfr' => 18, 'metadata' => ['source' => 'pt4_pool']]);
        $player = $this->player($bot);

        $target = app(PreflopTargets::class)->forDecision($this->openingCtx($bot, $player, ['A♠', 'K♦']));

        $this->assertNotNull($target);
        $this->assertEqualsWithDelta(100.0, array_sum($target['spot']), 0.5, 'el reparto sobre todas sus manos suma 100');
        $this->assertGreaterThan(5, $target['spot']['raise'], 'un bot con pfr 18 abre bastantes manos desde el CO');
        $this->assertLessThan(60, $target['spot']['raise']);
        $this->assertGreaterThan(50, $target['hand']['raise'], 'AKo se abre casi siempre');
        $trash = app(PreflopTargets::class)->forDecision($this->openingCtx($bot, $player, ['7♣', '2♦']));
        $this->assertGreaterThan(95, $trash['hand']['fold'], '72o desde el CO se foldea');
    }

    public function test_a_bot_without_tables_has_no_target(): void
    {
        $bot = Bot::create(['nombre' => 'Plantilla', 'tipo' => 'tag', 'vpip' => 24, 'pfr' => 18]);

        $this->assertNull(app(PreflopTargets::class)->forDecision($this->openingCtx($bot, $this->player($bot), ['A♠', 'K♦'])));
    }

    public function test_the_actions_of_the_engine_are_grouped_in_raise_call_and_fold(): void
    {
        $targets = app(PreflopTargets::class);

        $this->assertSame(['raise' => 30.0, 'call' => 20.0, 'fold' => 50.0], $targets->group(['open_2_5bb' => 20, 'allin' => 10, 'call' => 20, 'fold' => 50]));
        $this->assertSame(['raise' => 40.0, 'check' => 60.0], $targets->group(['raise_x3' => 40, 'fold' => 60], true), 'si el check es gratis, foldear es pasar');
    }
}
