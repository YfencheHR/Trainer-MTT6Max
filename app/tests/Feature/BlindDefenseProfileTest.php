<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotHudStat;
use App\Services\Bots\Brain\BotProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** La defensa de las ciegas usa la defensa GENERAL del jugador cuando la ciega no tiene datos propios. */
class BlindDefenseProfileTest extends TestCase
{
    use RefreshDatabase;

    private function bot(array $general, array $bbThreeBet = []): Bot
    {
        $bot = Bot::create(['nombre' => 'Perfil ciegas', 'tipo' => 'pool', 'vpip' => 61, 'pfr' => 34, 'metadata' => ['source' => 'pt4_import']]);
        BotHudStat::create(['bot_id' => $bot->id, 'nivel' => 1] + $general);
        if ($bbThreeBet) {
            BotHudStat::create(['bot_id' => $bot->id, 'nivel' => 1, 'posicion' => 'BB', 'accion' => 'vs_open'] + $bbThreeBet);
        }

        return $bot;
    }

    public function test_big_blind_without_own_data_uses_the_general_defense(): void
    {
        $bot = $this->bot(['vpip' => 61, 'pfr' => 34, 'three_bet' => 15, 'bb_defense' => 70, 'sb_defense' => 40], ['three_bet' => 23]);

        $profile = BotProfile::forBot($bot);

        // Defensa total 70 % = 3-bet 23 % + call 47 % (antes se inventaba con vpip × 0,9 − 3-bet = 32 %)
        $this->assertEqualsWithDelta(47, $profile['positions']['BB']['vs_open']['call'], 0.01);
        $this->assertEqualsWithDelta(40 - 15 * 1.3, $profile['positions']['SB']['vs_open']['call'], 0.01, 'SB: defensa 40 % − su 3-bet (15 % × 1,3)');
    }

    public function test_the_defense_stored_for_the_blind_position_wins_over_the_general_one(): void
    {
        $bot = $this->bot(['vpip' => 61, 'pfr' => 34, 'three_bet' => 15, 'bb_defense' => 70], ['three_bet' => 10]);
        // Lo que guarda el importador para la ciega grande: defensa ante una apertura (paga o resube), por oportunidad
        BotHudStat::create(['bot_id' => $bot->id, 'nivel' => 1, 'posicion' => 'BB', 'accion' => 'defense_bb', 'bb_defense' => 52]);

        $this->assertEqualsWithDelta(42, BotProfile::forBot($bot)['positions']['BB']['vs_open']['call'], 0.01, '52 % de defensa − 3-bet 10 %');
    }

    public function test_without_any_defense_stat_it_falls_back_to_the_old_estimate(): void
    {
        $bot = $this->bot(['vpip' => 61, 'pfr' => 34, 'three_bet' => 15], ['three_bet' => 23]);

        $this->assertEqualsWithDelta(61 * 0.9 - 23, BotProfile::forBot($bot)['positions']['BB']['vs_open']['call'], 0.01);
    }
}
