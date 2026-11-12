<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->onDelete('cascade');
            $table->foreignId('bot_id')->nullable()->constrained('bots')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            // Información del jugador
            $table->string('name');
            $table->decimal('starting_stack', 10, 2);
            $table->decimal('current_stack', 10, 2)->default(0);

            // Estado
            $table->boolean('is_bot')->default(true);
            $table->boolean('is_eliminated')->default(false);
            $table->integer('placement')->nullable(); // Posición final si fue eliminado
            $table->integer('position')->default(0); // Posición en la mesa

            $table->timestamps();

            // Índices
            $table->unique(['tournament_id', 'bot_id', 'user_id']);
            $table->index('tournament_id');
            $table->index('is_eliminated');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_players');
    }
};
