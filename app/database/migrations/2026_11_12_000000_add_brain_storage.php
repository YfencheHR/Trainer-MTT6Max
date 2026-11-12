<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Almacenamiento de los "cerebros" (reglas de decisión) de los bots:
     *  - bots.brain               capa PROPIA del bot: reglas extra, ajustes de habilidad y qué partes se autoconfiguran
     *  - bot_brain_snapshots      copia congelada del cerebro completo de un bot (con el hash como identidad):
     *                             un torneo juega siempre con el cerebro que tenía al crearse, aunque luego se edite el bot
     *  - tournament_players       snapshot heredado + capa de cambios propios del jugador (para evolucionarlo)
     */
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->json('brain')->nullable();
            $table->unsignedInteger('brain_version')->default(1);
        });

        Schema::create('bot_brain_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->nullable()->constrained('bots')->nullOnDelete();
            $table->string('hash', 16)->unique();
            $table->json('brain');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('tournament_players', function (Blueprint $table) {
            $table->foreignId('brain_snapshot_id')->nullable()->constrained('bot_brain_snapshots')->nullOnDelete();
            $table->json('brain_overrides')->nullable(); // evolución propia del jugador (mismo formato que bots.brain)
        });
    }

    public function down(): void
    {
        Schema::table('tournament_players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brain_snapshot_id');
            $table->dropColumn('brain_overrides');
        });
        Schema::dropIfExists('bot_brain_snapshots');
        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn(['brain', 'brain_version']);
        });
    }
};
