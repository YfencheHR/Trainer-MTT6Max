<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué hace un perfil cuando limpeó y se encuentra una subida (PokerTracker: flg_p_limp y flg_p_face_raise), por posición: oportunidades y cómo
 * respondió (fold / call / subida o all-in). Sin el call, el bot solo podía subir o foldear tras limpear; la gente iguala la mitad de las veces.
 * Son contadores, así que se suman entre importaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_limp_response_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('bots')->onDelete('cascade');
            $table->string('position', 3);
            $table->unsignedInteger('opportunities')->default(0);
            $table->unsignedInteger('folds')->default(0);
            $table->unsignedInteger('calls')->default(0);
            $table->unsignedInteger('raises')->default(0);
            $table->timestamps();

            $table->unique(['bot_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_limp_response_stats');
    }
};
