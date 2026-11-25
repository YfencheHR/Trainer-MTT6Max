<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repeticiones de una mano jugada: se reparten las mismas cartas y los bots deciden de nuevo (ver HandReplayService). Cada repetición guarda su
 * resumen (si se desvió de lo jugado, dónde y cómo terminó) y TODAS las acciones de las dos versiones (la jugada y la repetida, con la distribución
 * y la razón de cada decisión de bot) para poder analizar después, también con SQL, cuánto y dónde difieren.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hand_replays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->onDelete('cascade');
            $table->foreignId('tournament_hand_id')->constrained('tournament_hands')->onDelete('cascade');
            $table->uuid('batch_id')->index();          // las repeticiones pedidas de una vez comparten lote
            $table->unsignedSmallInteger('run');        // número dentro del lote
            $table->string('engine', 20)->default('rules');
            $table->boolean('diverged')->default(false);
            $table->unsignedSmallInteger('same_prefix')->default(0);   // acciones iguales hasta el primer cambio
            $table->unsignedSmallInteger('original_actions')->default(0);
            $table->unsignedSmallInteger('replay_actions')->default(0);
            $table->json('first_divergence')->nullable();
            $table->json('original_result')->nullable();
            $table->json('replay_result')->nullable();
            $table->boolean('same_winner')->default(false);
            $table->timestamps();

            $table->index(['tournament_hand_id', 'created_at']);
        });

        Schema::create('hand_replay_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hand_replay_id')->constrained('hand_replays')->onDelete('cascade');
            $table->string('side', 10);                 // original | replay
            $table->unsignedSmallInteger('seq');
            $table->string('street', 10);
            $table->unsignedBigInteger('tournament_player_id')->nullable();
            $table->string('player_name');
            $table->unsignedBigInteger('bot_id')->nullable();
            $table->string('action', 12);
            $table->integer('amount')->default(0);
            $table->integer('pot_before')->nullable();
            $table->integer('to_call')->nullable();
            $table->integer('stack_before')->nullable();
            $table->float('equity')->nullable();
            $table->json('distribution')->nullable();
            $table->text('reason')->nullable();

            $table->index(['hand_replay_id', 'side', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hand_replay_actions');
        Schema::dropIfExists('hand_replays');
    }
};
