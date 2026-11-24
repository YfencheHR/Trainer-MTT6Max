<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STREETS = ['flop', 'turn', 'river'];

    /**
     * Lo que se observa de cada jugador por calle: cuántas veces apuesta cuando nadie ha apostado y cuántas veces foldea ante una apuesta,
     * con sus oportunidades. Es lo que la memoria de los bots con IA mezcla con el perfil del rival.
     */
    public function up(): void
    {
        Schema::table('tournament_player_stats', function (Blueprint $table) {
            foreach (self::STREETS as $street) {
                foreach (['bet_opp', 'bet', 'f2bet_opp', 'f2bet'] as $counter) {
                    $table->unsignedInteger("{$street}_{$counter}")->default(0);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_player_stats', function (Blueprint $table) {
            foreach (self::STREETS as $street) {
                foreach (['bet_opp', 'bet', 'f2bet_opp', 'f2bet'] as $counter) {
                    $table->dropColumn("{$street}_{$counter}");
                }
            }
        });
    }
};
