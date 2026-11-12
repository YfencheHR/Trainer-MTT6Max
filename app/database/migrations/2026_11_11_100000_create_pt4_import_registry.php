<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de lo que ya se importó de PokerTracker, para no contar dos veces una mano o un torneo
 * cuando se sube la misma copia (o una que se solapa con otra).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pt4_imported_hands', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('id_site');
            $t->string('hand_no', 40);
            $t->string('tourney_no', 40)->nullable();
            $t->timestamp('imported_at')->useCurrent();
            $t->unique(['id_site', 'hand_no']);
        });

        Schema::create('pt4_imported_tourneys', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('id_site');
            $t->string('tourney_no', 40);
            $t->unsignedInteger('hands')->default(0);
            $t->timestamp('imported_at')->useCurrent();
            $t->unique(['id_site', 'tourney_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pt4_imported_tourneys');
        Schema::dropIfExists('pt4_imported_hands');
    }
};
