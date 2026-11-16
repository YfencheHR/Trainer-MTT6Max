<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar columnas legacy como foreign key references para compatibilidad
        // Mientras se reconstruyen los assets compilados
        Schema::table('hand_action_ranges', function (Blueprint $table) {
            // Crear columnas computed que referencian las nuevas columnas
            if (!Schema::hasColumn('hand_action_ranges', 'hand_id')) {
                DB::statement('ALTER TABLE hand_action_ranges ADD COLUMN hand_id INTEGER GENERATED ALWAYS AS (tournament_hand_id) STORED');
            }
            if (!Schema::hasColumn('hand_action_ranges', 'player_id')) {
                DB::statement('ALTER TABLE hand_action_ranges ADD COLUMN player_id INTEGER GENERATED ALWAYS AS (tournament_player_id) STORED');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hand_action_ranges', function (Blueprint $table) {
            if (Schema::hasColumn('hand_action_ranges', 'hand_id')) {
                DB::statement('ALTER TABLE hand_action_ranges DROP COLUMN hand_id');
            }
            if (Schema::hasColumn('hand_action_ranges', 'player_id')) {
                DB::statement('ALTER TABLE hand_action_ranges DROP COLUMN player_id');
            }
        });
    }
};
