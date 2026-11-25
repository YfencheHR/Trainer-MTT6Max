<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que un perfil hace postflop según el tipo de bote (limp / srp), la posición y el tramo de stack, con CONTADORES (no porcentajes) para
 * poder sumarlos entre importaciones y saber la muestra de cada celda.
 *
 *   open   (le toca y nadie ha apostado): open_opps = oportunidades · opens = veces que apostó. Es la frecuencia de apostar sin iniciativa
 *          en botes limpeados, que no se parece al «donk» (liderar contra quien subió preflop).
 *   facing (se enfrenta a una primera apuesta): facing = veces · folds / calls / raises = lo que hizo.
 *
 * position: any | ip | oop · stack_bucket: all | 0-10 | 10-20 | 20-40 | 40+ (stack al empezar la mano).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_postflop_pot_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('bots')->onDelete('cascade');
            $table->string('street', 5);
            $table->string('pot', 4);
            $table->string('position', 3)->default('any');
            $table->string('stack_bucket', 8)->default('all');
            $table->unsignedInteger('open_opps')->default(0);
            $table->unsignedInteger('opens')->default(0);
            $table->unsignedInteger('facing')->default(0);
            $table->unsignedInteger('folds')->default(0);
            $table->unsignedInteger('calls')->default(0);
            $table->unsignedInteger('raises')->default(0);
            $table->timestamps();

            $table->unique(['bot_id', 'street', 'pot', 'position', 'stack_bucket'], 'bot_postflop_pot_stats_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_postflop_pot_stats');
    }
};
