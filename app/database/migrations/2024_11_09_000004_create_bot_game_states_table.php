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
        Schema::create('bot_game_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('bots')->onDelete('cascade');

            // Posición y información de mesa
            $table->enum('posicion', ['UTG', 'MP', 'CO', 'BTN', 'SB', 'BB'])->nullable();
            $table->integer('stack_bb')->nullable();
            $table->integer('blinds_bb')->nullable();

            // Mano actual
            $table->string('mano')->nullable(); // AKs, KK, 32o, etc
            $table->enum('street', ['preflop', 'flop', 'turn', 'river', 'showdown'])->nullable();

            // Estado emocional del bot
            $table->enum('estado_mental', [
                'NORMAL',      // Estado base
                'FOCUS',       // Concentración (después de bad beat pero resiliente)
                'TILT'         // Tilt (juega peor)
            ])->default('NORMAL');

            // Acciones
            $table->enum('ultima_accion', [
                'FOLD',
                'CHECK',
                'CALL',
                'BET',
                'RAISE',
                'ALL_IN',
                '3BET',
                '4BET',
                'LIMP'
            ])->nullable();

            $table->integer('apuesta_actual')->nullable();
            $table->integer('total_puesto')->nullable();

            // Tablero comunitario
            $table->json('board')->nullable(); // ['AK7', 'Q', 'J'] = [flop, turn, river]
            $table->json('villanos')->nullable(); // [{ id, posicion, stack, acciones }]

            // Metadata
            $table->timestamp('ultimo_movimiento')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_game_states');
    }
};
