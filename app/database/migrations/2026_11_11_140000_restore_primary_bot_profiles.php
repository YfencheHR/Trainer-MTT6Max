<?php

use App\Models\Bot;
use App\Models\BotHudStat;
use App\Services\Import\Pt4Importer;
use Database\Seeders\BotHudStatsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Los 9 perfiles primarios (setminer, roca, nit, OMC, TAG, LAG, calling station, maníaco, ballena) son los arquetipos de
 * referencia y NO los modifica la importación de PokerTracker: sus datos van a sus copias "· Pool".
 * Las primeras importaciones sí los reescribían (y con pocas manos algunos se desviaron mucho: un "Setminer" con VPIP 2,9),
 * así que aquí vuelven a sus cifras de referencia. Los datos reales se recuperan importando de nuevo (alimentan las copias).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Pt4Importer::PRIMARY_VPIP_PFR as $tipo => [$vpip, $pfr]) {
            foreach (Bot::where('tipo', $tipo)->get() as $bot) {
                BotHudStat::where('bot_id', $bot->id)->delete();
                $bot->update(['vpip' => $vpip, 'pfr' => $pfr]);
            }
        }
        (new BotHudStatsSeeder())->run();
    }

    public function down(): void
    {
        // Los datos importados se recuperan volviendo a importar
    }
};
