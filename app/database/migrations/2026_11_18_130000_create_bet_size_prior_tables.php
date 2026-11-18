<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prior de farol por estadísticas (BluffPriorModel):
 *  - bet_size_prior_model: la recta por calle (% de apuestas con aire = intercepto + pendiente × PFR), ajustada en la importación.
 *  - bot_bet_size_prior: el % de aire que le corresponde a cada perfil según su PFR (se fija al importar y viaja con la copia
 *    congelada de los torneos, así un torneo no cambia si se reimporta después).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bet_size_prior_model', function (Blueprint $table) {
            $table->id();
            $table->string('street', 5)->unique();
            $table->decimal('intercept', 8, 4);
            $table->decimal('slope', 8, 4);
            $table->decimal('r2', 5, 3)->default(0);
            $table->unsignedInteger('n')->default(0);
            $table->timestamps();
        });

        Schema::create('bot_bet_size_prior', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->string('street', 5);
            $table->decimal('air_share', 5, 2);
            $table->timestamps();

            $table->unique(['bot_id', 'street']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_bet_size_prior');
        Schema::dropIfExists('bet_size_prior_model');
    }
};
