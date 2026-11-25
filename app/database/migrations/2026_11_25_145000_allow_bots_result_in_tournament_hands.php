<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Una mano sin jugador humano ya no cuenta como «lose»: su resultado es «bots» (quién ganó el bote está en las acciones y en hand_players).
 * El resultado es un enum de la base de datos (una restricción CHECK): se amplía con el valor nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tournament_hands DROP CONSTRAINT IF EXISTS tournament_hands_result_check');
        DB::statement("ALTER TABLE tournament_hands ADD CONSTRAINT tournament_hands_result_check CHECK (result::text IN ('fold', 'win', 'lose', 'split', 'in_progress', 'bots'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE tournament_hands SET result = 'lose' WHERE result = 'bots'");
        DB::statement('ALTER TABLE tournament_hands DROP CONSTRAINT IF EXISTS tournament_hands_result_check');
        DB::statement("ALTER TABLE tournament_hands ADD CONSTRAINT tournament_hands_result_check CHECK (result::text IN ('fold', 'win', 'lose', 'split', 'in_progress'))");
    }
};
