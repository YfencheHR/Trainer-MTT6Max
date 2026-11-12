<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bot_hud_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('bots')->onDelete('cascade');

            // Granularidad
            $table->integer('nivel')->default(1); // 1-4
            $table->enum('posicion', ['UTG', 'MP', 'CO', 'BTN', 'SB', 'BB'])->nullable();
            $table->enum('accion', [
                'opening',
                'vs_open',
                'vs_3bet',
                'vs_4bet',
                'defense_bb',
                'cold_call',
                'squeeze'
            ])->nullable();
            $table->enum('rango_ciegas', [
                '0-6',
                '6-25',
                '25-40',
                '40-80',
                '80-100',
                '100-200',
                '200+'
            ])->nullable();

            // PREFLOP STATS - Rigurosas
            $table->integer('vpip')->nullable();
            $table->integer('pfr')->nullable();
            $table->float('af_preflop')->nullable();
            $table->integer('three_bet')->nullable();
            $table->integer('fold_to_3bet')->nullable();
            $table->integer('four_bet')->nullable();
            $table->integer('fold_to_4bet')->nullable();

            // PREFLOP STATS - Adaptativas
            $table->integer('rfi')->nullable();
            $table->integer('squeeze')->nullable();
            $table->integer('cold_call')->nullable();
            $table->integer('sb_defense')->nullable();
            $table->integer('bb_defense')->nullable();

            // FLOP STATS - Rigurosas
            $table->float('af_flop')->nullable();
            $table->integer('cbet')->nullable();
            $table->integer('cr_flop')->nullable();
            $table->integer('f2cbet')->nullable();

            // FLOP STATS - Adaptativas
            $table->integer('cbet_ip')->nullable();
            $table->integer('cbet_oop')->nullable();
            $table->integer('donk')->nullable();
            $table->integer('float')->nullable();
            $table->integer('f2raise_flop')->nullable();

            // TURN STATS - Rigurosas
            $table->float('af_turn')->nullable();
            $table->integer('beturn')->nullable();
            $table->integer('cr_turn')->nullable();
            $table->integer('f2beturn')->nullable();

            // TURN STATS - Adaptativas
            $table->integer('barrel')->nullable();
            $table->integer('f2raise_turn')->nullable();
            $table->integer('checkcall_turn')->nullable();

            // RIVER STATS - Rigurosas
            $table->float('af_river')->nullable();
            $table->integer('betriver')->nullable();
            $table->integer('f2betriver')->nullable();
            $table->float('vb_ratio')->nullable();

            // RIVER STATS - Adaptativas
            $table->integer('bluff')->nullable();
            $table->integer('shove')->nullable();
            $table->integer('wsd')->nullable();

            // Metadata
            $table->integer('sample_size')->default(0);
            $table->float('confidence')->default(0.0);

            $table->timestamps();

            // Index para búsquedas rápidas
            $table->unique(['bot_id', 'nivel', 'posicion', 'accion', 'rango_ciegas']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_hud_stats');
    }
};
