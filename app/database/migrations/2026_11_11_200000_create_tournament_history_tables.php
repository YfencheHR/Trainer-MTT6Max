<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial estructurado de torneos (pensado para poder reproducir manos y torneos):
     *  - hand_players:      quién estaba en cada mano (asiento, posición, stacks, cartas, resultado)
     *  - hand_actions:      cada acción de la mano EN ORDEN (ciegas, acciones, reparto de calles)
     *  - tournament_events: eliminaciones, mesas rotas, cambios de asiento, niveles…
     *  - tournament_hands:  board completo y botes de la mano
     */
    public function up(): void
    {
        Schema::table('tournament_hands', function (Blueprint $table) {
            $table->json('board_full')->nullable()->after('community_cards'); // las 5 cartas, se vean o no
            $table->json('pots')->nullable()->after('board_full');            // bote principal y laterales con ganadores
        });

        Schema::create('hand_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_hand_id')->constrained('tournament_hands')->cascadeOnDelete();
            $table->foreignId('tournament_player_id')->constrained('tournament_players')->cascadeOnDelete();
            $table->unsignedTinyInteger('seat');                 // asiento de la mesa (1-6)
            $table->string('position', 8)->nullable();           // BTN, SB, BB, UTG, HJ, CO
            $table->unsignedInteger('stack_start');
            $table->unsignedInteger('stack_end')->nullable();
            $table->json('hole_cards');
            $table->string('folded_street', 8)->nullable();      // calle en la que hizo fold (null = no foldeó)
            $table->boolean('went_to_showdown')->default(false);
            $table->string('hand_name')->nullable();             // "Pareja de ases"… solo si llegó al showdown
            $table->unsignedInteger('won')->default(0);          // fichas ganadas en la mano
            $table->timestamps();

            $table->unique(['tournament_hand_id', 'tournament_player_id']);
            $table->index('tournament_player_id');
        });

        Schema::create('hand_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_hand_id')->constrained('tournament_hands')->cascadeOnDelete();
            $table->unsignedInteger('sequence');                 // orden dentro de la mano
            $table->string('street', 8);                         // preflop, flop, turn, river
            $table->foreignId('tournament_player_id')->nullable()->constrained('tournament_players')->nullOnDelete();
            $table->string('type', 12);                          // post_sb, post_bb, fold, check, call, raise, allin, deal
            $table->unsignedInteger('amount')->default(0);       // fichas puestas en esta acción
            $table->unsignedInteger('to_amount')->nullable();    // apuesta total del jugador en la calle tras la acción
            $table->unsignedInteger('pot_after')->default(0);
            $table->unsignedInteger('stack_after')->nullable();
            $table->json('cards')->nullable();                   // cartas repartidas (type = deal)
            $table->timestamp('created_at')->nullable();

            $table->unique(['tournament_hand_id', 'sequence']);
            $table->index('tournament_player_id');
        });

        Schema::create('tournament_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->unsignedInteger('round')->nullable();
            $table->foreignId('table_id')->nullable()->constrained('tables')->nullOnDelete();
            $table->foreignId('tournament_player_id')->nullable()->constrained('tournament_players')->nullOnDelete();
            $table->string('type', 24);                          // tournament_started, level_up, elimination, table_break, player_moved, tournament_finished
            $table->string('message');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tournament_id', 'round']);
            $table->index(['tournament_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_events');
        Schema::dropIfExists('hand_actions');
        Schema::dropIfExists('hand_players');
        Schema::table('tournament_hands', function (Blueprint $table) {
            $table->dropColumn(['board_full', 'pots']);
        });
    }
};
