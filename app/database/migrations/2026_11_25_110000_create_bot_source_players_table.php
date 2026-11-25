<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué jugadores reales de PokerTracker forman cada perfil Pool. Sirve para asignar a cada jugador de un torneo real el bot de su perfil
 * (réplica de torneos y comparación de decisiones con lo que hizo la persona).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_source_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('bots')->onDelete('cascade');
            $table->unsignedBigInteger('pt4_player_id');
            $table->string('player_name');
            $table->unsignedInteger('hands')->default(0);
            $table->timestamps();

            $table->unique(['bot_id', 'pt4_player_id']);
            $table->index('player_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_source_players');
    }
};
