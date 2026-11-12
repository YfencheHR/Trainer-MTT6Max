<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Respuesta del defensor ante una apuesta (calles postflop, en posición / fuera de posición):
 * fold ya existía (f2cbet); se añaden call y raise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_hud_stats', function (Blueprint $t) {
            $t->unsignedTinyInteger('call_vs_bet')->nullable()->after('f2cbet');
            $t->unsignedTinyInteger('raise_vs_bet')->nullable()->after('call_vs_bet');
        });
    }

    public function down(): void
    {
        Schema::table('bot_hud_stats', function (Blueprint $t) {
            $t->dropColumn(['call_vs_bet', 'raise_vs_bet']);
        });
    }
};
