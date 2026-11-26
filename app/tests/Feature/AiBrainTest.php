<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Services\Ai\AiBrain;
use App\Services\Ai\AiClient;
use App\Services\Bots\BotDecisionService;
use App\Services\Bots\Brain\BrainRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** La IA decide el flop de los bots con motor 'ai'; el resto de calles y los bots con reglas no cambian. */
class AiBrainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $file = sys_get_temp_dir().'/ai-model-test.json';
        file_put_contents($file, json_encode(['model' => 'modelo-de-prueba']));
        config(['ai.model_file' => $file, 'ai.url' => 'http://ia.test', 'ai.streets' => ['flop']]);
    }

    private function bot(string $engine): Bot
    {
        return Bot::create(['nombre' => 'B '.$engine, 'tipo' => 'tag', 'vpip' => 24, 'pfr' => 18, 'metadata' => ['engine' => $engine]]);
    }

    private function ctx(Bot $bot, string $street = 'flop', int $toCall = 300): array
    {
        $board = ['K♣', '7♥', '2♦', '9♠', '3♣'];
        $legal = ['fold' => $toCall > 0, 'check' => $toCall === 0, 'call' => $toCall > 0 ? $toCall : null,
            'raise' => ['min' => 600, 'max' => 5000], 'allin' => 5000];

        return [
            'hand_id' => 1, 'hand_number' => 1, 'street' => $street, 'big_blind' => 100, 'pot' => 600, 'current_bet' => $toCall, 'to_call' => $toCall,
            'required_equity' => 0.33, 'board' => array_slice($board, 0, ['flop' => 3, 'turn' => 4][$street] ?? 5),
            'me' => ['id' => 1, 'bot_id' => $bot->id, 'position' => 'BTN', 'stack' => 5000, 'bet' => 0, 'hole_cards' => ['A♠', 'K♦'], 'flags' => [], 'stats' => ['vpip' => 24, 'pfr' => 18]],
            'players' => [
                ['id' => 1, 'position' => 'BTN', 'stack' => 5000, 'bet' => 0, 'folded' => false, 'preflop_stats' => ['vpip' => 24], 'postflop_stats' => ['flop' => ['f2bet' => 60]]],
                ['id' => 2, 'position' => 'BB', 'stack' => 5000, 'bet' => $toCall, 'folded' => false, 'preflop_stats' => ['vpip' => 40], 'postflop_stats' => []],
            ],
            'history' => [['player_id' => 2, 'street' => $street, 'action' => 'bet', 'amount' => $toCall, 'pot_before' => 600]],
            'legal' => $legal,
        ];
    }

    public function test_the_ai_decides_the_flop_for_a_bot_with_the_ai_engine(): void
    {
        Http::fake(['ia.test/ai/decide' => Http::response(['status' => 'success', 'distribution' => ['call' => 0.7, 'fold' => 0.3]])]);

        $decision = app(BotDecisionService::class)->decide($this->ctx($this->bot('ai')));

        $this->assertSame('ai', $decision['trace']['engine']);
        $this->assertContains($decision['action'], ['call', 'fold']);
        $this->assertSame('modelo-de-prueba', $decision['trace']['model']);
        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['model'] === 'modelo-de-prueba'
                && $body['state']['street'] === 'flop'
                && $body['state']['in_position'] === true
                && in_array('bet_pot', $body['legal_actions'], true)
                && $body['state']['history'][0] === ['flop', 'BB', 'bet', 3.0];
        });
    }

    public function test_the_ai_decides_the_preflop_when_the_street_is_covered_and_sizes_the_raise_over_the_open(): void
    {
        config(['ai.streets' => ['preflop', 'flop']]);
        Http::fake(['ia.test/ai/decide' => Http::response(['distribution' => ['raise_x3' => 1.0]])]);
        $ctx = $this->ctx($this->bot('ai'), 'preflop', 250);
        $ctx['board'] = [];
        $ctx['pot'] = 400;
        $ctx['history'] = [['player_id' => 2, 'street' => 'preflop', 'action' => 'raise', 'amount' => 250, 'pot_before' => 150]];

        $decision = app(BotDecisionService::class)->decide($ctx);

        $this->assertSame('raise', $decision['action']);
        $this->assertSame(750, $decision['amount'], 'x3 la apuesta actual de 250');
        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['state']['street'] === 'preflop'
                && in_array('raise_x3', $body['legal_actions'], true)
                && ! in_array('bet_pot', $body['legal_actions'], true)
                && $body['state']['history'][0] === ['preflop', 'BB', 'raise', 2.5]
                && ! isset($body['state']['table']);   // el bot de la prueba no tiene tablas de la importación
        });
    }

    public function test_the_ai_decides_the_turn_with_the_whole_history_of_the_hand(): void
    {
        config(['ai.streets' => ['turn']]);
        Http::fake(['ia.test/ai/decide' => Http::response(['distribution' => ['call' => 0.6, 'fold' => 0.4]])]);
        $ctx = $this->ctx($this->bot('ai'), 'turn');
        $ctx['history'] = [
            ['player_id' => 2, 'street' => 'preflop', 'action' => 'raise', 'amount' => 250],
            ['player_id' => 1, 'street' => 'preflop', 'action' => 'call', 'amount' => 150],
            ['player_id' => 1, 'street' => 'flop', 'action' => 'check', 'amount' => 0],
            ['player_id' => 2, 'street' => 'flop', 'action' => 'raise', 'amount' => 300],
            ['player_id' => 1, 'street' => 'flop', 'action' => 'call', 'amount' => 300],
            ['player_id' => 2, 'street' => 'turn', 'action' => 'raise', 'amount' => 600],
        ];

        $decision = app(BotDecisionService::class)->decide($ctx);

        $this->assertSame('turn', $decision['trace']['street']);
        $this->assertContains($decision['action'], ['call', 'fold']);
        Http::assertSent(function ($request) {
            $state = $request->data()['state'];

            return $state['street'] === 'turn'
                && count($state['board']) === 4
                && $state['history'][3] === ['flop', 'BB', 'bet', 3.0]       // la primera subida de una calle postflop es una apuesta
                && $state['history'][5] === ['turn', 'BB', 'bet', 6.0]
                && in_array('bet_pot', $request->data()['legal_actions'], true);
        });
    }

    public function test_the_ai_decides_the_river_with_the_whole_history_of_the_hand(): void
    {
        config(['ai.streets' => ['river']]);
        Http::fake(['ia.test/ai/decide' => Http::response(['distribution' => ['fold' => 0.6, 'call' => 0.4]])]);
        $ctx = $this->ctx($this->bot('ai'), 'river');
        $ctx['history'] = [
            ['player_id' => 2, 'street' => 'preflop', 'action' => 'raise', 'amount' => 250],
            ['player_id' => 1, 'street' => 'preflop', 'action' => 'call', 'amount' => 150],
            ['player_id' => 1, 'street' => 'flop', 'action' => 'check', 'amount' => 0],
            ['player_id' => 2, 'street' => 'flop', 'action' => 'raise', 'amount' => 300],
            ['player_id' => 1, 'street' => 'flop', 'action' => 'call', 'amount' => 300],
            ['player_id' => 2, 'street' => 'turn', 'action' => 'raise', 'amount' => 600],
            ['player_id' => 1, 'street' => 'turn', 'action' => 'call', 'amount' => 600],
            ['player_id' => 2, 'street' => 'river', 'action' => 'raise', 'amount' => 1200],
        ];

        $decision = app(BotDecisionService::class)->decide($ctx);

        $this->assertSame('river', $decision['trace']['street']);
        $this->assertContains($decision['action'], ['call', 'fold']);
        Http::assertSent(function ($request) {
            $state = $request->data()['state'];

            return $state['street'] === 'river'
                && count($state['board']) === 5
                && $state['history'][5] === ['turn', 'BB', 'bet', 6.0]
                && $state['history'][7] === ['river', 'BB', 'bet', 12.0];
        });
    }

    public function test_a_bot_adapts_to_the_rival_as_much_as_its_skill_allows(): void
    {
        $this->mock(BrainRepository::class, function ($mock) {
            $mock->shouldReceive('forPlayer')->with(7)->andReturn(['skill' => ['tier' => 'pro']]);
            $mock->shouldReceive('forPlayer')->with(8)->andReturn(['skill' => ['tier' => 'whale']]);
            $mock->shouldReceive('forPlayer')->with(9)->andThrow(new \RuntimeException('sin cerebro'));
        });
        $brain = app(AiBrain::class);

        $this->assertSame(1.0, $brain->adaptationFor(['me' => ['id' => 7]]));
        $this->assertSame(0.1, $brain->adaptationFor(['me' => ['id' => 8]]));
        $this->assertSame(0.5, $brain->adaptationFor(['me' => ['id' => 9]]), 'sin cerebro conocido, el valor por defecto');
    }

    public function test_the_model_file_written_by_powershell_with_a_bom_is_read(): void
    {
        file_put_contents(config('ai.model_file'), "\xEF\xBB\xBF".json_encode(['model' => 'con-bom']));

        $this->assertSame('con-bom', app(AiClient::class)->model());
    }

    public function test_the_same_seed_gives_the_same_action(): void
    {
        Http::fake(['ia.test/ai/decide' => Http::response(['distribution' => ['call' => 0.5, 'fold' => 0.5]])]);
        $brain = app(AiBrain::class);
        $ctx = $this->ctx($this->bot('ai'));

        $this->assertSame($brain->decide($ctx, 99)['action'], $brain->decide($ctx, 99)['action']);
    }

    public function test_illegal_actions_the_model_gives_are_dropped(): void
    {
        // Sin apuesta que igualar no se puede foldear: aunque el modelo lo pida, sale check
        Http::fake(['ia.test/ai/decide' => Http::response(['distribution' => ['fold' => 0.9, 'check' => 0.1]])]);

        $decision = app(BotDecisionService::class)->decide($this->ctx($this->bot('ai'), 'flop', 0));

        $this->assertSame('check', $decision['action']);
    }

    public function test_other_streets_and_bots_with_rules_do_not_call_the_ai(): void
    {
        Http::fake();
        $service = app(BotDecisionService::class);

        $service->decide($this->ctx($this->bot('ai'), 'turn'));
        $service->decide($this->ctx($this->bot('rules')));

        Http::assertNothingSent();
    }

    public function test_when_the_ai_fails_the_decision_falls_back_to_the_rules(): void
    {
        Http::fake(['ia.test/ai/decide' => Http::response(['message' => 'Ollama caído'], 503)]);

        $decision = app(BotDecisionService::class)->decide($this->ctx($this->bot('ai')));

        $this->assertStringContainsString('IA no disponible', $decision['reason']);
        $this->assertContains($decision['action'], ['fold', 'call', 'raise', 'allin', 'check']);
    }

    public function test_in_a_replay_the_adaptation_comes_from_the_bots_brain_not_from_an_invented_player_id(): void
    {
        $bot = $this->bot('ai');   // tag: nivel «pro» → adaptación completa
        $ctx = $this->ctx($bot);
        $ctx['me']['id'] = 987654;   // en la réplica este id no existe en la base de datos

        $this->assertSame(0.5, app(AiBrain::class)->adaptationFor($ctx), 'sin ser réplica, un jugador desconocido cae al valor por defecto');
        $this->assertSame(1.0, app(AiBrain::class)->adaptationFor($ctx + ['replay' => true]));
    }
}
