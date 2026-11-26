<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qué hace un perfil cuando le toca hablar con limpers delante y nadie ha subido (PokerTracker: vs_limp_*), por posición: oportunidades, veces que
 * sube (aislar) y veces que iguala. Sin esto el bot usaba su frecuencia de abrir y no subía casi nunca ante limpers ni desde la ciega grande.
 *
 * Se rellena con los contadores por mano que ya guarda el importador (bot_preflop_hand_stats), así que no hace falta importar otra vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_iso_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('bots')->onDelete('cascade');
            $table->string('position', 3);
            $table->unsignedInteger('opportunities')->default(0);
            $table->unsignedInteger('raises')->default(0);
            $table->unsignedInteger('calls')->default(0);
            $table->timestamps();

            $table->unique(['bot_id', 'position']);
        });

        DB::statement('
            insert into bot_iso_stats (bot_id, position, opportunities, raises, calls, created_at, updated_at)
            select bot_id, posicion, sum(vs_limp_opportunities), sum(vs_limp_raises), sum(vs_limp_calls), now(), now()
            from bot_preflop_hand_stats group by bot_id, posicion having sum(vs_limp_opportunities) > 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_iso_stats');
    }
};
