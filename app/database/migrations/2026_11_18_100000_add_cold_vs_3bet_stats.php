<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Respuesta de un jugador que se encuentra un 3-bet SIN haber actuado antes en la mano (no abrió, no limpeó, no pagó la
 * apertura). Comprobado contra DB0.backup: flg_p_3bet_def_opp sin flg_p_open / flg_p_limp / flg_p_ccall / limpers /
 * flg_p_squeeze_def_opp, con la acción en enum_p_3bet_action (F / C / R).
 *
 *  - bot_hud_stats: % de fold / call / subida (cold3_*), de la fila general.
 *  - bot_preflop_hand_stats: contadores por mano y posición de esa situación.
 *  - bot_preflop_hand_ranges: respuesta generada por mano ante un 3-bet (cold3_*) y ante un 4-bet o más (cold4_*).
 */
return new class extends Migration
{
    private const HUD = ['cold3_fold', 'cold3_call', 'cold3_raise'];
    private const HANDS = ['cold3_opportunities', 'cold3_calls', 'cold3_raises'];
    private const RANGES = ['cold3_raise', 'cold3_call', 'cold4_raise', 'cold4_call'];

    public function up(): void
    {
        Schema::table('bot_hud_stats', function (Blueprint $table) {
            foreach (self::HUD as $column) {
                if (! Schema::hasColumn('bot_hud_stats', $column)) {
                    $table->decimal($column, 5, 2)->nullable();
                }
            }
        });
        Schema::table('bot_preflop_hand_stats', function (Blueprint $table) {
            foreach (self::HANDS as $column) {
                if (! Schema::hasColumn('bot_preflop_hand_stats', $column)) {
                    $table->unsignedInteger($column)->default(0);
                }
            }
        });
        Schema::table('bot_preflop_hand_ranges', function (Blueprint $table) {
            foreach (self::RANGES as $column) {
                if (! Schema::hasColumn('bot_preflop_hand_ranges', $column)) {
                    $table->decimal($column, 5, 2)->nullable(); // null = aún sin generar (se regenera al leerla)
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('bot_hud_stats', fn(Blueprint $t) => $t->dropColumn(self::HUD));
        Schema::table('bot_preflop_hand_stats', fn(Blueprint $t) => $t->dropColumn(self::HANDS));
        Schema::table('bot_preflop_hand_ranges', fn(Blueprint $t) => $t->dropColumn(self::RANGES));
    }
};
