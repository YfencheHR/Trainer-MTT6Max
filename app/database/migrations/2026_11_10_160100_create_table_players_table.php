<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('tables')->onDelete('cascade');
            $table->foreignId('tournament_player_id')->constrained('tournament_players')->onDelete('cascade');
            $table->integer('seat_number');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['table_id', 'tournament_player_id']);
            $table->unique(['table_id', 'seat_number']);
            $table->index('table_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_players');
    }
};
