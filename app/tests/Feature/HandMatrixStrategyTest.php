<?php

namespace Tests\Feature;

use App\Models\HandGroup;
use App\Models\HandGroupValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandMatrixStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_matrix_returns_action_frequencies_and_ev_for_the_selected_context(): void
    {
        $hand = HandGroup::create([
            'group_name' => 'AKs',
            'hand_type' => 'Suited',
            'gto_ev' => 50,
            'hands_in_group' => [],
            'combinatorios' => 4,
        ]);

        $strategy = HandGroupValue::create([
            'hand_group_id' => $hand->id,
            'scenario' => 'Opener',
            'positions' => ['BTN'],
            'stack_depth_min' => 20,
            'stack_depth_max' => 50,
            'ev' => 1.25,
            'actions' => [
                'fold' => ['frequency_pct' => 25, 'ev_bb' => 0],
                'raise_2_5bb' => ['frequency_pct' => 75, 'ev_bb' => 1.6, 'size_bb' => 2.5],
            ],
        ]);

        $response = $this->getJson('/hand-matrix/matrix-values?scenario=Opener&position=BTN&stack_depth=20');

        $response->assertOk();
        $row = collect($response->json())->firstWhere('id', $hand->id);
        $this->assertNotNull($row);
        $this->assertSame($strategy->id, $row['strategy_value_id']);
        $this->assertSame($strategy->actions, $row['actions']);
    }

    public function test_filter_structure_exposes_generated_preflop_scenarios(): void
    {
        $hand = HandGroup::create([
            'group_name' => 'AKs',
            'hand_type' => 'Suited',
            'gto_ev' => 50,
            'hands_in_group' => [],
            'combinatorios' => 4,
        ]);

        foreach (['Opener', 'Defense'] as $scenario) {
            HandGroupValue::create([
                'hand_group_id' => $hand->id,
                'scenario' => $scenario,
                'positions' => ['BTN'],
                'stack_depth_min' => 6,
                'stack_depth_max' => 25,
                'ev' => 0.5,
                'actions' => ['call' => ['frequency_pct' => 100, 'ev_bb' => 0.5]],
            ]);
        }

        $response = $this->getJson('/hand-matrix/api/filter-structure');

        $response->assertOk()
            ->assertJsonStructure([
                'Opener' => ['BTN'],
                'Defense' => ['BTN'],
            ]);
    }

    public function test_matrix_page_renders_the_strategy_editor(): void
    {
        $this->get('/hand-matrix')
            ->assertOk()
            ->assertSee('strategyModal');
    }

    public function test_strategy_update_creates_a_context_row_and_persists_actions(): void
    {
        $hand = HandGroup::create([
            'group_name' => 'QJs',
            'hand_type' => 'Suited',
            'gto_ev' => 40,
            'hands_in_group' => [],
            'combinatorios' => 4,
        ]);

        $actions = [
            'fold' => ['frequency_pct' => 40, 'ev_bb' => 0],
            'raise_2_5bb' => ['frequency_pct' => 60, 'ev_bb' => 0.8, 'size_bb' => 2.5],
        ];

        $this->withoutMiddleware();
        $response = $this->patchJson('/hand-matrix/strategies', [
            'scenario' => 'Opener',
            'position' => 'CO',
            'stack_depth_min' => 20,
            'stack_depth_max' => 50,
            'updates' => [[
                'hand_group_id' => $hand->id,
                'actions' => $actions,
            ]],
        ]);

        $response->assertOk()->assertJsonPath('updated', 1);
        $this->assertDatabaseCount('hand_group_values', 1);

        $value = HandGroupValue::firstOrFail();
        $this->assertSame(['CO'], $value->positions);
        $this->assertSame($actions, $value->actions);
    }

    public function test_strategy_update_splits_shared_position_rows_before_saving_position_specific_actions(): void
    {
        $hand = HandGroup::create([
            'group_name' => 'AJo',
            'hand_type' => 'Offsuit',
            'gto_ev' => 35,
            'hands_in_group' => [],
            'combinatorios' => 12,
        ]);
        $sharedValue = HandGroupValue::create([
            'hand_group_id' => $hand->id,
            'scenario' => 'Opener',
            'positions' => ['CO', 'BTN'],
            'stack_depth_min' => 20,
            'stack_depth_max' => 50,
            'ev' => 0,
        ]);
        $broadValue = HandGroupValue::create([
            'hand_group_id' => $hand->id,
            'scenario' => 'Opener',
            'positions' => ['CO', 'BTN'],
            'stack_depth_min' => 0,
            'stack_depth_max' => 255,
            'ev' => 0,
        ]);
        $actions = [
            'fold' => ['frequency_pct' => 30, 'ev_bb' => 0],
            'raise_2_5bb' => ['frequency_pct' => 70, 'ev_bb' => 0.6, 'size_bb' => 2.5],
        ];

        $this->withoutMiddleware();
        $this->patchJson('/hand-matrix/strategies', [
            'scenario' => 'Opener',
            'position' => 'CO',
            'stack_depth_min' => 20,
            'stack_depth_max' => 50,
            'updates' => [[
                'hand_group_id' => $hand->id,
                'actions' => $actions,
            ]],
        ])->assertOk();

        $sharedValue->refresh();
        $this->assertSame(['BTN'], $sharedValue->positions);
        $broadValue->refresh();
        $this->assertSame(['CO', 'BTN'], $broadValue->positions);

        $positionValue = HandGroupValue::where('hand_group_id', $hand->id)
            ->where('stack_depth_min', 20)
            ->where('stack_depth_max', 50)
            ->whereJsonContains('positions', 'CO')
            ->firstOrFail();
        $this->assertSame(['CO'], $positionValue->positions);
        $this->assertSame($actions, $positionValue->actions);

        $this->getJson('/hand-matrix/matrix-values?scenario=Opener&position=CO&stack_depth=20&stack_depth_max=50')
            ->assertOk()
            ->assertJsonFragment(['id' => $hand->id, 'actions' => $actions]);
        $this->getJson('/hand-matrix/matrix-values?scenario=Opener&position=BTN&stack_depth=20&stack_depth_max=50')
            ->assertOk()
            ->assertJsonFragment(['id' => $hand->id, 'actions' => null]);
    }

    public function test_strategy_update_rejects_frequencies_that_do_not_total_one_hundred(): void
    {
        $hand = HandGroup::create([
            'group_name' => 'T9s',
            'hand_type' => 'Suited',
            'gto_ev' => 25,
            'hands_in_group' => [],
            'combinatorios' => 4,
        ]);

        $this->withoutMiddleware();
        $response = $this->patchJson('/hand-matrix/strategies', [
            'scenario' => 'Opener',
            'position' => 'CO',
            'stack_depth_min' => 20,
            'stack_depth_max' => 50,
            'updates' => [[
                'hand_group_id' => $hand->id,
                'actions' => [
                    'fold' => ['frequency_pct' => 25, 'ev_bb' => 0],
                    'raise_2_5bb' => ['frequency_pct' => 50, 'ev_bb' => 0.5],
                ],
            ]],
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('hand_group_values', 0);
    }
}
