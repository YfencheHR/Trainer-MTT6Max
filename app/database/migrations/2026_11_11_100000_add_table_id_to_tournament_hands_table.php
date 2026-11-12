<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada mano pertenece a una mesa. `hand_number` pasa a ser la RONDA del torneo
     * (todas las mesas juegan una mano por ronda), así que la unicidad es por mesa.
     */
    public function up(): void
    {
        Schema::table('tournament_hands', function (Blueprint $table) {
            $table->foreignId('table_id')->nullable()->after('tournament_id')->constrained('tables')->nullOnDelete();
            $table->dropUnique(['tournament_id', 'hand_number']);
            $table->unique(['tournament_id', 'table_id', 'hand_number']);
            $table->index('table_id');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_hands', function (Blueprint $table) {
            $table->dropUnique(['tournament_id', 'table_id', 'hand_number']);
            $table->dropConstrainedForeignId('table_id');
            $table->unique(['tournament_id', 'hand_number']);
        });
    }
};
