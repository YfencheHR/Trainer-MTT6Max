<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_hands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->onDelete('cascade');
            $table->integer('hand_number');
            $table->integer('level');
            $table->json('blind_level_config');

            // Posiciones
            $table->integer('button_seat')->nullable();
            $table->integer('small_blind_seat')->nullable();
            $table->integer('big_blind_seat')->nullable();

            // Cartas y juego
            $table->integer('hero_seat');
            $table->json('hero_cards');
            $table->integer('hero_hand_type_id')->nullable();
            $table->json('community_cards')->nullable();

            // Información de stacks
            $table->json('stacks_before');
            $table->json('stacks_after');

            // Dinero en juego
            $table->decimal('pot_preflop', 10, 2)->nullable();
            $table->decimal('pot_flop', 10, 2)->nullable();
            $table->decimal('pot_turn', 10, 2)->nullable();
            $table->decimal('pot_river', 10, 2)->nullable();

            // Resultado
            $table->enum('result', ['fold', 'win', 'lose', 'split', 'in_progress'])->default('in_progress');
            $table->integer('winning_seat')->nullable();

            // Jugadores en la mano
            $table->json('players_in_hand');

            // Acciones
            $table->json('actions');

            // Estado
            $table->enum('status', ['completed', 'in_progress', 'voided'])->default('in_progress');
            $table->timestamps();

            // Índices
            $table->unique(['tournament_id', 'hand_number']);
            $table->index('tournament_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_hands');
    }
};
