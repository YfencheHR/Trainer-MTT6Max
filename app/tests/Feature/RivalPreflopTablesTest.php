<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotPreflopHandStat;
use App\Models\BotPreflopHandRange;
use App\Services\Bots\Brain\PreflopRanking;
use App\Services\Bots\RivalPreflopTables;
use Database\Seeders\BotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RivalPreflopTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflop_tables_project_each_position_into_169_hands(): void
    {
        $this->seed(BotSeeder::class);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();
        $tables = (new RivalPreflopTables(new PreflopRanking()))->forBot($bot);

        $this->assertSame(['UTG', 'HJ', 'CO', 'BTN', 'SB', 'BB'], array_keys($tables));
        $this->assertDatabaseCount('bot_preflop_hand_ranges', 6 * 169);
        $this->assertCount(169, $tables['UTG']['attack']);
        $this->assertCount(169, $tables['UTG']['defense']);

        $attack = collect($tables['UTG']['attack'])->keyBy('hand');
        $defense = collect($tables['UTG']['defense'])->keyBy('hand');
        $this->assertGreaterThan(80, $attack['AA']['open']);
        $this->assertLessThan(1, $attack['72o']['open']);
        $this->assertGreaterThan(0, $attack['JTs']['open']);
        $this->assertLessThan(100, $attack['JTs']['open']);

        foreach ($attack as $cell) {
            $this->assertLessThanOrEqual(100.01, $cell['open'] + $cell['limp']);
            $this->assertEqualsWithDelta(
                100,
                $cell['open_response']['reraise'] + $cell['open_response']['call'] + $cell['open_response']['fold'],
                0.01
            );
        }
        foreach ($defense as $cell) {
            $this->assertEqualsWithDelta(100, $cell['three_bet'] + $cell['call'] + $cell['fold'], 0.01);
            $this->assertSame(0.0, $cell['three_bet']);
            $this->assertSame(0.0, $cell['call']);
            $this->assertSame(100.0, $cell['fold']);
        }
        $middlePositionDefense = collect($tables['HJ']['defense'])->keyBy('hand');
        $this->assertGreaterThan($middlePositionDefense['72o']['three_bet'], $middlePositionDefense['AA']['three_bet']);
        $this->assertGreaterThan($middlePositionDefense['AA']['fold'], $middlePositionDefense['72o']['fold']);

        $weightedOpen = $attack->sum(fn($cell) => $cell['open'] * (strlen($cell['hand']) === 2 ? 6 : (str_ends_with($cell['hand'], 's') ? 4 : 12))) / 1326;
        $this->assertEqualsWithDelta($tables['UTG']['stats']['open'], $weightedOpen, 0.01);
    }

    public function test_hand_level_pt4_observations_change_the_smoothed_frequency(): void
    {
        $this->seed(BotSeeder::class);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();
        $service = new RivalPreflopTables(new PreflopRanking());
        $before = collect($service->forBot($bot)['UTG']['attack'])->keyBy('hand');

        BotPreflopHandStat::create([
            'bot_id' => $bot->id,
            'posicion' => 'UTG',
            'mano' => '72o',
            'open_opportunities' => 100,
            'open_raises' => 100,
        ]);
        $after = collect($service->forBot($bot)['UTG']['attack'])->keyBy('hand');

        $this->assertGreaterThan($before['72o']['open'], $after['72o']['open']);
        $this->assertLessThanOrEqual(100.01, $after['72o']['open'] + $after['72o']['limp']);
    }

    public function test_approximate_tables_are_shown_only_for_pool_rivals(): void
    {
        $this->seed(BotSeeder::class);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();

        $this->get(route('bots.show', $bot))
            ->assertOk()
            ->assertDontSee('Tablas preflop aproximadas');

        $bot->update(['metadata' => ['source' => 'pt4_pool']]);

        $this->get(route('bots.show', $bot))
            ->assertOk()
            ->assertSee('Tablas preflop aproximadas')
            ->assertSee('Defensa · ante una subida')
            ->assertSee('Editar tabla')
            ->assertSee('style="background: linear-gradient(90deg', false)
            ->assertSee('sin defensa')
            ->assertSee('si recibe 3-bet tras open: resubir')
            ->assertSee('Las ramas de iso aún no están incluidas.')
            ->assertDontSee(route('bots.preflop-tables.edit', [
                'bot' => $bot,
                'position' => 'UTG',
                'type' => 'defense',
            ]), false);
    }

    public function test_preflop_table_editor_is_scoped_and_utg_has_no_defense_editor(): void
    {
        $this->seed(BotSeeder::class);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();
        $bot->update(['metadata' => ['source' => 'pt4_pool']]);

        $this->get(route('bots.preflop-tables.edit', [
            'bot' => $bot,
            'position' => 'UTG',
            'type' => 'attack',
        ]))
            ->assertOk()
            ->assertSee('Editar tabla preflop')
            ->assertSee('data-table-type="attack"', false)
            ->assertDontSee('Defensa · ante una subida · frecuencias %');

        $this->get(route('bots.preflop-tables.edit', [
            'bot' => $bot,
            'position' => 'UTG',
            'type' => 'defense',
        ]))->assertNotFound();

        $this->get(route('bots.preflop-tables.edit', [
            'bot' => $bot,
            'position' => 'HJ',
            'type' => 'defense',
        ]))
            ->assertOk()
            ->assertSee('Defensa · ante una subida · frecuencias %')
            ->assertDontSee('Ataque · sin subida · frecuencias %');
    }

    public function test_pool_rival_hand_frequencies_can_be_saved_and_restored(): void
    {
        $this->seed(BotSeeder::class);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();
        $bot->update(['metadata' => ['source' => 'pt4_pool']]);
        $frequencies = [
            'attack_open' => 45,
            'attack_limp' => 5,
            'open_reraise' => 50,
            'open_call' => 30,
            'open_fold' => 20,
            'limp_raise' => 5,
            'limp_push' => 15,
            'defense_three_bet' => 20,
            'defense_call' => 30,
            'defense_fold' => 50,
        ];

        $this->patch(route('bots.preflop-tables.update', [
            'bot' => $bot,
            'position' => 'UTG',
            'hand' => 'AA',
        ]), $frequencies + ['table_type' => 'attack'])
            ->assertRedirect(route('bots.preflop-tables.edit', [
                'bot' => $bot,
                'position' => 'UTG',
                'type' => 'attack',
            ]));

        $this->assertDatabaseHas('bot_preflop_hand_ranges', [
            'bot_id' => $bot->id,
            'position' => 'UTG',
            'hand' => 'AA',
            'attack_open' => 45,
            'is_manual' => true,
        ]);

        $tables = app(RivalPreflopTables::class)->forBot($bot);
        $attack = collect($tables['UTG']['attack'])->firstWhere('hand', 'AA');
        $defense = collect($tables['UTG']['defense'])->firstWhere('hand', 'AA');
        $this->assertSame(45.0, $attack['open']);
        $this->assertSame(30.0, $attack['open_response']['call']);
        $this->assertSame(0.0, $defense['three_bet']);
        $this->assertSame(100.0, $defense['fold']);
        $this->assertTrue($attack['manual']);

        BotPreflopHandStat::create([
            'bot_id' => $bot->id,
            'posicion' => 'UTG',
            'mano' => 'AA',
            'open_opportunities' => 100,
            'open_raises' => 0,
            'limps' => 100,
        ]);
        $refreshed = app(RivalPreflopTables::class)->forBot($bot);
        $manualAfterRefresh = collect($refreshed['UTG']['attack'])->firstWhere('hand', 'AA');
        $this->assertSame(45.0, $manualAfterRefresh['open']);
        $this->assertTrue($manualAfterRefresh['manual']);

        $this->get(route('bots.show', $bot))
            ->assertOk()
            ->assertSee('editada manualmente')
            ->assertSee('data-attack-open="45.00"', false);

        $this->delete(route('bots.preflop-tables.reset', [
            'bot' => $bot,
            'position' => 'UTG',
            'hand' => 'AA',
        ]), ['table_type' => 'attack'])->assertRedirect(route('bots.preflop-tables.edit', [
            'bot' => $bot,
            'position' => 'UTG',
            'type' => 'attack',
        ]));

        $this->assertDatabaseMissing('bot_preflop_hand_ranges', [
            'bot_id' => $bot->id,
            'position' => 'UTG',
            'hand' => 'AA',
            'is_manual' => true,
        ]);

        $this->get(route('bots.show', $bot))->assertOk();
        $this->assertDatabaseHas('bot_preflop_hand_ranges', [
            'bot_id' => $bot->id,
            'position' => 'UTG',
            'hand' => 'AA',
            'is_manual' => false,
        ]);
    }

    public function test_manual_table_edit_rejects_inconsistent_action_frequencies(): void
    {
        $this->seed(BotSeeder::class);
        $bot = Bot::where('tipo', 'tag')->firstOrFail();
        $bot->update(['metadata' => ['source' => 'pt4_pool']]);

        $this->from(route('bots.show', $bot))
            ->patch(route('bots.preflop-tables.update', [
                'bot' => $bot,
                'position' => 'UTG',
                'hand' => 'AA',
            ]), [
                'attack_open' => 90,
                'attack_limp' => 20,
                'open_reraise' => 50,
                'open_call' => 30,
                'open_fold' => 20,
                'limp_raise' => 5,
                'limp_push' => 15,
                'defense_three_bet' => 20,
                'defense_call' => 30,
                'defense_fold' => 50,
            ])
            ->assertRedirect(route('bots.show', $bot))
            ->assertSessionHasErrors('attack');

        $this->assertDatabaseMissing('bot_preflop_hand_ranges', [
            'bot_id' => $bot->id,
            'position' => 'UTG',
            'hand' => 'AA',
        ]);
    }
}
