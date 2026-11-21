<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotPreflopHandRange;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Services\Ai\DecisionContract;
use App\Services\Ai\FlopRanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El rango con el que cada bot llega al flop sale de sus tablas preflop y de lo que hizo en la mano. */
class FlopRangesTest extends TestCase
{
    use RefreshDatabase;

    private int $opener;

    private int $defender;

    protected function setUp(): void
    {
        parent::setUp();
        $tournament = Tournament::create([
            'title' => 'MTT-ranges', 'status' => 'pending',
            'config' => ['basic_settings' => ['starting_stack' => 10000, 'num_players' => 2, 'big_blind' => 100], 'players' => [], 'game_settings' => []],
        ]);
        $a = Bot::create(['nombre' => 'Abre', 'tipo' => 'tag', 'vpip' => 24, 'pfr' => 18]);
        $b = Bot::create(['nombre' => 'Defiende', 'tipo' => 'tag', 'vpip' => 30, 'pfr' => 10]);
        $this->opener = $this->player($tournament, $a)->id;
        $this->defender = $this->player($tournament, $b)->id;

        $this->row($a, 'CO', 'AKo', ['attack_open' => 80, 'open_call' => 50, 'open_reraise' => 10]);
        $this->row($a, 'CO', 'QJs', ['attack_open' => 100, 'open_call' => 100]);
        $this->row($a, 'CO', '72o', ['attack_open' => 0]);
        $this->row($b, 'BB', 'QQ', ['defense_call' => 50, 'defense_three_bet' => 40]);
        $this->row($b, 'BB', '72o', ['defense_call' => 0, 'defense_three_bet' => 0]);
    }

    private function player(Tournament $tournament, Bot $bot): TournamentPlayer
    {
        return TournamentPlayer::create(['tournament_id' => $tournament->id, 'bot_id' => $bot->id, 'name' => $bot->nombre, 'is_bot' => true, 'starting_stack' => 10000, 'current_stack' => 10000]);
    }

    private function row(Bot $bot, string $position, string $hand, array $values): void
    {
        BotPreflopHandRange::create($values + [
            'bot_id' => $bot->id, 'position' => $position, 'hand' => $hand, 'attack_open' => 0, 'attack_limp' => 0, 'open_reraise' => 0,
            'open_call' => 0, 'open_fold' => 0, 'limp_raise' => 0, 'limp_push' => 0, 'defense_three_bet' => 0, 'defense_call' => 0,
            'defense_fold' => 0, 'is_manual' => true,
        ]);
    }

    private function ctx(array $history): array
    {
        return [
            'big_blind' => 100,
            'players' => [
                ['id' => $this->opener, 'position' => 'CO', 'stack' => 9000, 'total_in_hand' => 1000],
                ['id' => $this->defender, 'position' => 'BB', 'stack' => 9000, 'total_in_hand' => 1000],
            ],
            'history' => $history,
        ];
    }

    private function action(int $player, string $action): array
    {
        return ['player_id' => $player, 'street' => 'preflop', 'action' => $action, 'amount' => 250];
    }

    public function test_the_opener_arrives_with_the_hands_it_opens_and_the_caller_with_the_hands_it_defends(): void
    {
        $ranges = app(FlopRanges::class)->forHand($this->ctx([$this->action($this->opener, 'raise'), $this->action($this->defender, 'call')]));

        $this->assertSame(['AKo' => 0.8, 'QJs' => 1.0], $ranges[$this->opener], 'solo las manos con las que abre, con el peso con que las abre');
        $this->assertSame(['QQ' => 0.5], $ranges[$this->defender], 'QQ la paga el 50 %; 72o nunca');
    }

    public function test_each_action_of_the_hand_multiplies_the_frequency_of_the_hand(): void
    {
        $history = [$this->action($this->opener, 'raise'), $this->action($this->defender, 'raise'), $this->action($this->opener, 'call')];

        $ranges = app(FlopRanges::class)->forHand($this->ctx($history));

        $this->assertSame(['AKo' => 0.4, 'QJs' => 1.0], $ranges[$this->opener], 'abre (80 %) y paga el 3-bet (50 %) = 40 %');
        $this->assertSame(['QQ' => 0.4], $ranges[$this->defender], '3-bet con el 40 % de QQ');
    }

    public function test_players_without_a_bot_table_have_no_range_and_the_contract_carries_the_ranges(): void
    {
        $ctx = $this->ctx([$this->action($this->opener, 'raise'), $this->action($this->defender, 'call')]);
        $ctx['players'][] = ['id' => 999, 'position' => 'BTN', 'stack' => 9000, 'total_in_hand' => 0];

        $ranges = app(FlopRanges::class)->forHand($ctx);

        $this->assertArrayNotHasKey(999, $ranges);

        $state = app(DecisionContract::class)->fromBotContext($this->decisionCtx(), $ranges);
        $this->assertSame(['AKo' => 0.8, 'QJs' => 1.0], $state['range']);
        $this->assertSame(['QQ' => 0.5], $state['rivals'][0]['range']);
    }

    private function decisionCtx(): array
    {
        return [
            'street' => 'flop', 'big_blind' => 100, 'pot' => 500, 'to_call' => 0, 'board' => ['K♣', '7♥', '2♦'],
            'me' => ['id' => $this->opener, 'position' => 'CO', 'stack' => 9000, 'hole_cards' => ['A♠', 'K♦'], 'stats' => []],
            'players' => [
                ['id' => $this->opener, 'position' => 'CO', 'stack' => 9000, 'bet' => 0, 'folded' => false],
                ['id' => $this->defender, 'position' => 'BB', 'stack' => 9000, 'bet' => 0, 'folded' => false],
            ],
            'history' => [],
        ];
    }
}
