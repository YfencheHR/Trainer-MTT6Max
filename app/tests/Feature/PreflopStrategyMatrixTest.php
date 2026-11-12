<?php

namespace Tests\Feature;

use App\Models\HandGroup;
use App\Models\HandGroupValue;
use App\Models\Bot;
use App\Models\BotPreflopHandRange;
use App\Services\Bots\Brain\DecisionMatrix;
use App\Services\Bots\Brain\BrainRunner;
use App\Services\Bots\Brain\PreflopStrategyMatrix;
use App\Services\Bots\Brain\RuleEngine;
use App\Services\Equity\HeuristicPreflopStrategyGenerator;
use Database\Seeders\BotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class PreflopStrategyMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_generator_creates_balanced_action_rows_and_preserves_existing_strategies(): void
    {
        $hand = $this->createHand('AA');
        $generator = app(HeuristicPreflopStrategyGenerator::class);

        $first = $generator->generate(false, HandGroup::whereKey($hand->id)->get());
        $this->assertSame(60, $first['created']);
        $this->assertSame(0, $first['preserved']);
        $this->assertSame(100, $first['simulations']);
        $this->assertDatabaseCount('hand_group_values', 60);

        $value = HandGroupValue::where('hand_group_id', $hand->id)
            ->where('scenario', 'Opener')
            ->where('stack_depth_min', 25)
            ->whereJsonContains('positions', 'BTN')
            ->firstOrFail();
        $this->assertEqualsWithDelta(100, array_sum(array_column($value->actions, 'frequency_pct')), 0.001);
        $this->assertArrayHasKey('open_2_5bb', $value->actions);
        $this->assertArrayHasKey('ev_bb', $value->actions['open_2_5bb']);

        $second = $generator->generate(false, HandGroup::whereKey($hand->id)->get());
        $this->assertSame(0, $second['created']);
        $this->assertSame(60, $second['preserved']);
        $this->assertDatabaseCount('hand_group_values', 60);
    }

    public function test_runtime_lookup_blends_a_hand_matrix_prior_and_modulates_it_for_the_aggressor(): void
    {
        $hand = $this->createHand('AA');
        HandGroupValue::create([
            'hand_group_id' => $hand->id,
            'scenario' => 'Defense',
            'positions' => ['BTN'],
            'stack_depth_min' => 6,
            'stack_depth_max' => 25,
            'ev' => 1.2,
            'actions' => [
                'fold' => ['frequency_pct' => 20, 'ev_bb' => 0],
                'call' => ['frequency_pct' => 40, 'ev_bb' => 0.5],
                'raise_x3' => ['frequency_pct' => 40, 'ev_bb' => 1.5],
            ],
        ]);

        $strategyMatrix = app(PreflopStrategyMatrix::class);
        $features = [
            'street' => 'preflop',
            'preflop_raises' => 1,
            'position' => 'BTN',
            'stack_bb' => 15,
            'hand_code' => 'AA',
            'agg_hands' => 0,
            'agg_pfr' => null,
        ];
        $baseline = $strategyMatrix->forDecision([], $features);
        $this->assertNotNull($baseline);
        $this->assertSame('Defense', $baseline['scenario']);
        $this->assertNull($baseline['opponent_adjustment']);

        $features['agg_hands'] = 100;
        $features['agg_pfr'] = 35;
        $adjusted = $strategyMatrix->forDecision([], $features);
        $this->assertNotNull($adjusted);
        $this->assertEqualsWithDelta(100, array_sum($adjusted['distribution']), 0.001);
        $this->assertGreaterThan($baseline['distribution']['raise_x3'], $adjusted['distribution']['raise_x3']);
        $this->assertNotNull($adjusted['opponent_adjustment']);

        $matrix = new DecisionMatrix('fold');
        $fired = [];
        app(RuleEngine::class)->apply([[
            'id' => 'preflop_matrix',
            'when' => ['f' => 'street', 'op' => '=', 'v' => 'preflop'],
            'paint' => [[
                'blend' => $adjusted['distribution'],
                'weight' => $adjusted['prior_weight'],
            ]],
        ]], $features, $matrix, new Randomizer(new Mt19937(1)), $fired);

        $this->assertGreaterThan(0, $matrix->points('raise_x3'));
        $this->assertEqualsWithDelta(100, array_sum($matrix->distribution()), 0.02);
    }

    public function test_strategy_lookup_falls_back_when_its_preflop_scenario_is_not_applicable(): void
    {
        $hand = $this->createHand('AKs');
        HandGroupValue::create([
            'hand_group_id' => $hand->id,
            'scenario' => 'Opener',
            'positions' => ['CO'],
            'stack_depth_min' => 6,
            'stack_depth_max' => 25,
            'ev' => 0,
            'actions' => ['open_2_5bb' => ['frequency_pct' => 100, 'ev_bb' => 0.5]],
        ]);

        $strategyMatrix = app(PreflopStrategyMatrix::class);
        $features = [
            'street' => 'preflop',
            'preflop_raises' => 0,
            'limpers' => 1,
            'position' => 'CO',
            'stack_bb' => 15,
            'hand_code' => 'AKs',
        ];

        $this->assertNull($strategyMatrix->forDecision([], $features));

        $features['limpers'] = 0;
        $this->assertNotNull($strategyMatrix->forDecision([], $features));

        $features['preflop_raises'] = 2;
        $this->assertNull($strategyMatrix->forDecision([], $features));
    }

    public function test_pool_bot_uses_persisted_ranges_for_attack_defense_and_responses(): void
    {
        $this->seed(BotSeeder::class);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();
        $bot->update(['metadata' => ['source' => 'pt4_pool']]);
        $hand = $this->createHand('AA');
        HandGroupValue::create([
            'hand_group_id' => $hand->id,
            'scenario' => 'Opener',
            'positions' => ['BTN'],
            'stack_depth_min' => 1,
            'stack_depth_max' => 255,
            'ev' => 0,
            'actions' => ['fold' => ['frequency_pct' => 100, 'ev_bb' => 0]],
        ]);
        HandGroupValue::create([
            'hand_group_id' => $hand->id,
            'scenario' => 'Defense',
            'positions' => ['BTN'],
            'stack_depth_min' => 1,
            'stack_depth_max' => 255,
            'ev' => 0,
            'actions' => ['fold' => ['frequency_pct' => 100, 'ev_bb' => 0]],
        ]);
        BotPreflopHandRange::create([
            'bot_id' => $bot->id,
            'position' => 'BTN',
            'hand' => 'AA',
            'attack_open' => 40,
            'attack_limp' => 20,
            'open_reraise' => 10,
            'open_call' => 30,
            'open_fold' => 60,
            'limp_raise' => 12,
            'limp_push' => 20,
            'defense_three_bet' => 15,
            'defense_call' => 55,
            'defense_fold' => 30,
            'is_manual' => true,
        ]);

        $strategyMatrix = app(PreflopStrategyMatrix::class);
        $ctx = ['me' => ['bot_id' => $bot->id]];
        $features = [
            'street' => 'preflop',
            'position' => 'BTN',
            'stack_bb' => 50,
            'hand_code' => 'AA',
            'preflop_raises' => 0,
            'limpers' => 0,
        ];

        $attack = $strategyMatrix->forDecision($ctx, $features);
        $this->assertSame('pool_tables', $attack['source']);
        $this->assertEqualsWithDelta(40, $attack['distribution']['open_2_5bb'], 0.001);
        $this->assertEqualsWithDelta(20, $attack['distribution']['call'], 0.001);
        $this->assertEqualsWithDelta(40, $attack['distribution']['fold'], 0.001);

        $features['preflop_raises'] = 1;
        $defense = $strategyMatrix->forDecision($ctx, $features);
        $this->assertSame('pool_tables', $defense['source']);
        $this->assertEqualsWithDelta(15, $defense['distribution']['raise_x3'], 0.001);
        $this->assertEqualsWithDelta(55, $defense['distribution']['call'], 0.001);

        $features['i_limped_preflop'] = true;
        $limpResponse = $strategyMatrix->forDecision($ctx, $features);
        $this->assertSame('pool_limp_response', $limpResponse['scenario']);
        $this->assertEqualsWithDelta(12, $limpResponse['distribution']['raise_x3'], 0.001);
        $this->assertEqualsWithDelta(20, $limpResponse['distribution']['allin'], 0.001);
        $this->assertEqualsWithDelta(68, $limpResponse['distribution']['fold'], 0.001);

        $features['i_limped_preflop'] = false;
        $features['i_raised_preflop'] = true;
        $features['preflop_raises'] = 2;
        $openResponse = $strategyMatrix->forDecision($ctx, $features);
        $this->assertSame('pool_open_response', $openResponse['scenario']);
        $this->assertEqualsWithDelta(10, $openResponse['distribution']['raise_x3'], 0.001);
        $this->assertEqualsWithDelta(30, $openResponse['distribution']['call'], 0.001);
        $this->assertEqualsWithDelta(60, $openResponse['distribution']['fold'], 0.001);

        $decisionContext = [
            'street' => 'preflop',
            'big_blind' => 100,
            'pot' => 150,
            'current_bet' => 100,
            'to_call' => 0,
            'required_equity' => 0,
            'board' => [],
            'history' => [],
            'legal' => ['raise' => ['min' => 200, 'max' => 5000], 'check' => true, 'call' => 0, 'allin' => 5000],
            'me' => [
                'id' => 1,
                'bot_id' => $bot->id,
                'position' => 'BTN',
                'stack' => 5000,
                'bet' => 100,
                'hole_cards' => ['A♠', 'A♥'],
                'flags' => [],
            ],
            'players' => [[
                'id' => 1,
                'position' => 'BTN',
                'stack' => 5000,
                'bet' => 100,
                'folded' => false,
                'all_in' => false,
                'stats' => [],
                'preflop_stats' => [],
            ]],
        ];
        $brain = [
            'default' => 'fold',
            'skill' => ['equity_samples' => 1, 'noise' => []],
            'imprecision' => ['rate' => 0],
            'rules' => [[
                'id' => 'legacy_preflop_rule',
                'when' => ['f' => 'street', 'op' => '=', 'v' => 'preflop'],
                'paint' => [['action' => 'allin']],
            ]],
        ];
        $decision = app(BrainRunner::class)->matrixFor($decisionContext, $brain, 17);
        $this->assertSame('pool_tables', $decision['preflop_strategy']['source']);
        $this->assertEqualsWithDelta(40, $decision['distribution']['open_2_5bb'], 0.001);
        $this->assertArrayNotHasKey('allin', $decision['distribution']);

        $decisionContext['current_bet'] = 300;
        $decisionContext['to_call'] = 200;
        $decisionContext['me']['bet'] = 100;
        $decisionContext['legal']['check'] = false;
        $decisionContext['history'] = [
            ['street' => 'preflop', 'player_id' => 1, 'action' => 'call', 'amount' => 100],
            ['street' => 'preflop', 'player_id' => 2, 'action' => 'raise', 'amount' => 300],
        ];
        $decisionContext['players'][] = [
            'id' => 2,
            'position' => 'UTG',
            'stack' => 5000,
            'bet' => 300,
            'folded' => false,
            'all_in' => false,
            'stats' => [],
            'preflop_stats' => [],
        ];
        $decision = app(BrainRunner::class)->matrixFor($decisionContext, $brain, 17);
        $this->assertTrue($decision['features']['i_limped_preflop']);
        $this->assertSame('pool_limp_response', $decision['preflop_strategy']['scenario']);
        $this->assertEqualsWithDelta(12, $decision['distribution']['raise_x3'], 0.001);
        $this->assertEqualsWithDelta(20, $decision['distribution']['allin'], 0.001);
    }

    public function test_pool_utg_folds_when_facing_an_open_instead_of_using_a_defense_range(): void
    {
        $this->seed(BotSeeder::class);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();
        $bot->update(['metadata' => ['source' => 'pt4_pool']]);
        BotPreflopHandRange::create([
            'bot_id' => $bot->id,
            'position' => 'UTG',
            'hand' => 'AA',
            'attack_open' => 80,
            'attack_limp' => 0,
            'open_reraise' => 50,
            'open_call' => 30,
            'open_fold' => 20,
            'limp_raise' => 0,
            'limp_push' => 0,
            'defense_three_bet' => 40,
            'defense_call' => 40,
            'defense_fold' => 20,
            'is_manual' => true,
        ]);

        $decision = app(PreflopStrategyMatrix::class)->forDecision(
            ['me' => ['bot_id' => $bot->id]],
            [
                'street' => 'preflop',
                'position' => 'UTG',
                'hand_code' => 'AA',
                'preflop_raises' => 1,
                'i_limped_preflop' => false,
                'i_raised_preflop' => false,
            ]
        );

        $this->assertSame('pool_no_utg_defense', $decision['scenario']);
        $this->assertSame(['fold' => 100.0], $decision['distribution']);
    }

    private function createHand(string $code): HandGroup
    {
        return HandGroup::create([
            'group_name' => $code,
            'hand_type' => str_ends_with($code, 's') ? 'Suited' : (str_ends_with($code, 'o') ? 'Offsuit' : 'Pair'),
            'gto_ev' => 50,
            'hands_in_group' => [],
            'combinatorios' => 6,
        ]);
    }
}
