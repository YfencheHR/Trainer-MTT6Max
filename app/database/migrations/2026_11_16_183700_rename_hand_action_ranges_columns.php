<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Primero eliminar las columnas GENERATED si existen
        DB::statement('ALTER TABLE hand_action_ranges DROP COLUMN IF EXISTS hand_id');
        DB::statement('ALTER TABLE hand_action_ranges DROP COLUMN IF EXISTS player_id');

        // Luego renombrar las columnas reales a los nombres viejos para compatibilidad
        DB::statement('ALTER TABLE hand_action_ranges RENAME COLUMN tournament_hand_id TO hand_id');
        DB::statement('ALTER TABLE hand_action_ranges RENAME COLUMN tournament_player_id TO player_id');

        // Renombrar los índices y constraints también
        DB::statement('ALTER INDEX IF EXISTS hand_action_ranges_tournament_hand_id_index RENAME TO hand_action_ranges_hand_id_index');
        DB::statement('ALTER INDEX IF EXISTS hand_action_ranges_tournament_player_id_index RENAME TO hand_action_ranges_player_id_index');
        DB::statement('ALTER TABLE hand_action_ranges RENAME CONSTRAINT hand_action_ranges_tournament_hand_id_foreign TO hand_action_ranges_hand_id_foreign');
        DB::statement('ALTER TABLE hand_action_ranges RENAME CONSTRAINT hand_action_ranges_tournament_player_id_foreign TO hand_action_ranges_player_id_foreign');
    }

    public function down(): void
    {
        // Renombrar las columnas de vuelta
        DB::statement('ALTER TABLE hand_action_ranges RENAME COLUMN hand_id TO tournament_hand_id');
        DB::statement('ALTER TABLE hand_action_ranges RENAME COLUMN player_id TO tournament_player_id');

        // Renombrar los índices y constraints de vuelta
        DB::statement('ALTER INDEX hand_action_ranges_hand_id_index RENAME TO hand_action_ranges_tournament_hand_id_index');
        DB::statement('ALTER INDEX hand_action_ranges_player_id_index RENAME TO hand_action_ranges_tournament_player_id_index');
        DB::statement('ALTER TABLE hand_action_ranges RENAME CONSTRAINT hand_action_ranges_hand_id_foreign TO hand_action_ranges_tournament_hand_id_foreign');
        DB::statement('ALTER TABLE hand_action_ranges RENAME CONSTRAINT hand_action_ranges_player_id_foreign TO hand_action_ranges_tournament_player_id_foreign');
    }
};
