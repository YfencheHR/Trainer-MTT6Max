<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resultados del análisis de EV, guardados junto al historial para poder consultarlos y analizarlos.
     *
     * hand_actions  → EV por decisión (equity, equity necesaria, EV de lo hecho y de la alternativa, veredicto)
     * hand_players  → EV ajustado de la mano (all-in) y "suerte" = resultado real − EV
     * tournament_hands.ev_analyzed_at → mano ya analizada
     */
    public function up(): void
    {
        Schema::table('hand_actions', function (Blueprint $table) {
            $table->decimal('equity', 5, 4)->nullable()->after('decision');          // 0-1, contra las cartas reales de los rivales
            $table->decimal('required_equity', 5, 4)->nullable()->after('equity');   // lo que hacía falta para que igualar fuese 0 EV
            $table->integer('ev_taken')->nullable()->after('required_equity');       // EV de la acción realizada (fichas)
            $table->integer('ev_alternative')->nullable()->after('ev_taken');        // EV de la mejor alternativa
            $table->integer('ev_diff')->nullable()->after('ev_alternative');         // ev_taken − ev_alternative (+ ganó EV, − perdió EV)
            $table->string('ev_best', 8)->nullable()->after('ev_diff');              // fold | call
            $table->string('ev_verdict', 8)->nullable()->after('ev_best');           // gain | loss | neutral | na
            $table->string('ev_model', 24)->nullable()->after('ev_verdict');         // vs_actual_hands
        });

        Schema::table('hand_players', function (Blueprint $table) {
            $table->integer('ev_net')->nullable()->after('won');                     // resultado esperado (all-in ajustado)
            $table->integer('ev_luck')->nullable()->after('ev_net');                 // resultado real − ev_net
            $table->decimal('allin_equity', 5, 4)->nullable()->after('ev_luck');    // equity cuando quedó todo el dinero
        });

        Schema::table('tournament_hands', function (Blueprint $table) {
            $table->timestamp('ev_analyzed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hand_actions', function (Blueprint $table) {
            $table->dropColumn(['equity', 'required_equity', 'ev_taken', 'ev_alternative', 'ev_diff', 'ev_best', 'ev_verdict', 'ev_model']);
        });
        Schema::table('hand_players', function (Blueprint $table) {
            $table->dropColumn(['ev_net', 'ev_luck', 'allin_equity']);
        });
        Schema::table('tournament_hands', function (Blueprint $table) {
            $table->dropColumn('ev_analyzed_at');
        });
    }
};
