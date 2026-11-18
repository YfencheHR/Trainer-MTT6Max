<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Respuesta a una apuesta según su tamaño, de toda la población importada de PokerTracker: por calle y tramo de tamaño (% del bote
 * antes de la apuesta, los mismos tramos de la matriz de apuestas), cuántas veces se enfrentó un jugador a una primera apuesta
 * y cuántas abandonó o subió. Con ello los bots abandonan más ante apuestas grandes y menos ante las pequeñas (FoldBySize).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fold_to_bet_size_population', function (Blueprint $table) {
            $table->id();
            $table->string('street', 5);
            $table->string('bucket', 6);
            $table->unsignedInteger('faced')->default(0);
            $table->unsignedInteger('folded')->default(0);
            $table->unsignedInteger('raised')->default(0);
            $table->timestamps();

            $table->unique(['street', 'bucket']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fold_to_bet_size_population');
    }
};
