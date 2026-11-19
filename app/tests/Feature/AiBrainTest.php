<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Services\Bots\BotDecisionService;
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
            'required_equity' => 0.33, 'board' => array_slice($board, 0, $street === 'flop' ? 3 : 4),
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

    public function test_the_model_file_written_by_powershell_with_a_bom_is_read(): void
    {
        file_put_contents(config('ai.model_file'), "\xEF\xBB\xBF".json_encode(['model' => 'con-bom']));

        $this->assertSame('con-bom', app(\App\Services\Ai\AiClient::class)->model());
    }

    public function test_the_same_seed_gives_the_same_action(): void
    {
        Http::fake(['ia.test/ai/decide' => Http::response(['distribution' => ['call' => 0.5, 'fold' => 0.5]])]);
        $brain = app(\App\Services\Ai\AiBrain::class);
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
}
