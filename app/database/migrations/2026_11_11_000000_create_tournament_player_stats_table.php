<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contadores observados de cada jugador DENTRO de un torneo.
     * Se guardan contadores (no porcentajes) para poder calcular cualquier ratio
     * y saber el tamaño de la muestra. Se actualizan al terminar cada mano.
     */
    public function up(): void
    {
        Schema::create('tournament_player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_player_id')->unique()->constrained('tournament_players')->onDelete('cascade');

            $table->unsignedInteger('hands')->default(0);

            // Preflop
            $table->unsignedInteger('vpip')->default(0);
            $table->unsignedInteger('pfr')->default(0);
            $table->unsignedInteger('three_bet_opp')->default(0);
            $table->unsignedInteger('three_bet')->default(0);
            $table->unsignedInteger('fold_to_three_bet_opp')->default(0);
            $table->unsignedInteger('fold_to_three_bet')->default(0);

            // Postflop
            $table->unsignedInteger('saw_flop')->default(0);
            $table->unsignedInteger('cbet_opp')->default(0);
            $table->unsignedInteger('cbet')->default(0);
            $table->unsignedInteger('aggressive_actions')->default(0); // bets + raises
            $table->unsignedInteger('passive_actions')->default(0);    // calls
            $table->unsignedInteger('showdowns')->default(0);
            $table->unsignedInteger('showdown_wins')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_player_stats');
    }
};
