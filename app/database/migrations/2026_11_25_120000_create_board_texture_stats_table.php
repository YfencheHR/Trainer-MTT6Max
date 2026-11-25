<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué pasa en la población según la textura del board (ver BoardTexture), con CONTADORES para saber la muestra de cada clase.
 *   open:   le tocaba y nadie había apostado → open_opps / opens (apostó)
 *   facing: se enfrentó a una primera apuesta → facing / folds / raises
 *   por papel: c-bet del agresor, donk de quien lidera sin iniciativa y respuesta del defensor ante la c-bet
 * De aquí salen multiplicadores relativos a la media de la calle que se aplican a las estadísticas de cada perfil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_texture_stats', function (Blueprint $table) {
            $table->id();
            $table->string('street', 5);
            $table->string('texture', 12);
            $table->unsignedInteger('open_opps')->default(0);
            $table->unsignedInteger('opens')->default(0);
            $table->unsignedInteger('facing')->default(0);
            $table->unsignedInteger('folds')->default(0);
            $table->unsignedInteger('raises')->default(0);
            // Por papel: el agresor preflop (c-bet), quien lidera sin iniciativa (donk) y el defensor ante esa c-bet
            $table->unsignedInteger('cbet_opps')->default(0);
            $table->unsignedInteger('cbets')->default(0);
            $table->unsignedInteger('donk_opps')->default(0);
            $table->unsignedInteger('donks')->default(0);
            $table->unsignedInteger('cdef_opps')->default(0);
            $table->unsignedInteger('cdef_folds')->default(0);
            $table->unsignedInteger('cdef_raises')->default(0);
            $table->timestamps();

            $table->unique(['street', 'texture']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_texture_stats');
    }
};
