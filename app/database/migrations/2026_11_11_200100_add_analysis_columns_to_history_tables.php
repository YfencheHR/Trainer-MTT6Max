<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columnas para ANALIZAR el juego de los bots a partir del historial:
     * qué situación tenía cada jugador al actuar y qué información usó el bot.
     */
    public function up(): void
    {
        Schema::table('hand_actions', function (Blueprint $table) {
            $table->unsignedInteger('pot_before')->default(0)->after('amount');
            $table->unsignedInteger('stack_before')->nullable()->after('pot_before');
            $table->unsignedInteger('to_call')->default(0)->after('stack_before');       // fichas que debía igualar al actuar
            $table->unsignedTinyInteger('players_active')->nullable()->after('to_call'); // jugadores aún en la mano
            $table->json('decision')->nullable()->after('cards');                        // bots: contexto que vio + motivo de la decisión
        });

        Schema::table('hand_players', function (Blueprint $table) {
            $table->json('stats_before')->nullable()->after('hole_cards'); // stats observadas del jugador al empezar la mano
        });
    }

    public function down(): void
    {
        Schema::table('hand_actions', function (Blueprint $table) {
            $table->dropColumn(['pot_before', 'stack_before', 'to_call', 'players_active', 'decision']);
        });
        Schema::table('hand_players', function (Blueprint $table) {
            $table->dropColumn('stats_before');
        });
    }
};
