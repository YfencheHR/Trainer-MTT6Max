<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bot_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('bots')->onDelete('cascade');
            $table->string('version')->default('v1');

            // Contexto
            $table->enum('posicion', ['UTG', 'MP', 'CO', 'BTN', 'SB', 'BB'])->nullable();
            $table->enum('accion', [
                'opening',
                'vs_open',
                'vs_3bet',
                'vs_4bet',
                'defense_bb',
                'cold_call',
                'squeeze'
            ])->nullable();
            $table->enum('rango_ciegas', [
                '0-6',
                '6-25',
                '25-40',
                '40-80',
                '80-100',
                '100-200',
                '200+'
            ])->nullable();

            // Rangos por tipo de acción
            $table->json('rango_raise')->nullable();   // {AK: 1.0, KK: 0.9, ...}
            $table->json('rango_call')->nullable();    // {A9s: 0.4, ...}
            $table->json('rango_limp')->nullable();    // {87s: 0.2, ...}
            $table->json('rango_fold')->nullable();    // Manos que foldeamos siempre

            // Metadata
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Index
            $table->unique(['bot_id', 'version', 'posicion', 'accion', 'rango_ciegas']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_ranges');
    }
};
