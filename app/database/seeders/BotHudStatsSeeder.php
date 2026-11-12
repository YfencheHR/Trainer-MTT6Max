<?php

namespace Database\Seeders;

use App\Models\Bot;
use App\Models\BotHudStat;
use App\Services\Bots\Brain\BotProfile;
use Illuminate\Database\Seeder;

/**
 * Estadísticas PROVISIONALES de cada bot (fila general, sin posición ni acción).
 *
 * Salen de las cifras documentadas de cada perfil (docs/03-Bots.md). Son un sustituto hasta que exista
 * la importación desde la base de datos de PokerTracker, que rellenará bot_hud_stats (y bot_ranges) con
 * datos reales por posición, acción y profundidad de stack. Cuando haya datos más específicos, el
 * preflop de los bots los usará automáticamente (ver BotProfile).
 */
class BotHudStatsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Bot::all() as $bot) {
            $doc = BotProfile::DOCUMENTED[$bot->tipo] ?? null;
            if (!$doc) {
                continue;
            }

            BotHudStat::firstOrCreate(
                ['bot_id' => $bot->id, 'nivel' => 1, 'posicion' => null, 'accion' => null, 'rango_ciegas' => null],
                ['vpip' => $bot->vpip, 'pfr' => $bot->pfr] + $doc
            );
        }
    }
}
