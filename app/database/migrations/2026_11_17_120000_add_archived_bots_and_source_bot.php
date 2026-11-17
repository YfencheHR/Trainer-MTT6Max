<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfiles archivados y copias congeladas, para que recalcular una importación no rompa los torneos.
 *
 *  - bots.archived_at: un bot archivado no aparece en listas ni se puede elegir para torneos nuevos, pero conserva sus
 *    estadísticas y rangos. Lo usan (a) los perfiles Pool que ya tenían torneos al recalcular y (b) las copias congeladas.
 *  - tournament_players.source_bot_id: el perfil "vivo" del que salió el jugador. Desde ahora bot_id apunta a una COPIA
 *    CONGELADA de ese perfil al crear el torneo, así que importar, editar o borrar el perfil vivo no afecta al torneo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
        });

        Schema::table('tournament_players', function (Blueprint $table) {
            $table->foreignId('source_bot_id')->nullable()->constrained('bots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_bot_id');
        });

        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
