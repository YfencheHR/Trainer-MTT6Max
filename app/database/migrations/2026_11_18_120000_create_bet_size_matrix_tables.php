<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matriz de apuestas: cuántas veces apuesta cada perfil, por calle, con cada tipo de mano (aire / pareja / fuerte) y con cada
 * tamaño (% del bote antes de apostar). Son CONTEOS de las apuestas con cartas conocidas (las que llegan a showdown); se
 * suman entre importaciones y las probabilidades se calculan al leer, mezclando con la población (BetSizeMatrix).
 *
 *  - bot_bet_size_counts: por perfil.
 *  - bet_size_population: de todos los jugadores importados (el prior al que se acerca un perfil con pocas apuestas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_bet_size_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->string('street', 5);
            $table->string('tier', 6);
            $table->string('bucket', 6);
            $table->unsignedInteger('n')->default(0);
            $table->timestamps();

            $table->unique(['bot_id', 'street', 'tier', 'bucket']);
        });

        Schema::create('bet_size_population', function (Blueprint $table) {
            $table->id();
            $table->string('street', 5);
            $table->string('tier', 6);
            $table->string('bucket', 6);
            $table->unsignedInteger('n')->default(0);
            $table->timestamps();

            $table->unique(['street', 'tier', 'bucket']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_bet_size_counts');
        Schema::dropIfExists('bet_size_population');
    }
};
