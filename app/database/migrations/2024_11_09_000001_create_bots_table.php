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
        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->enum('tipo', [
                'setminer',
                'roca',
                'nit',
                'omc',
                'tag',
                'lag',
                'calling_station',
                'maniaco',
                'ballena',
                'usuario_real',
                'pool'
            ]);
            $table->enum('perfil', [
                'tight-pasivo',
                'tight-agresivo',
                'loose-agresivo',
                'loose-pasivo',
                'errático'
            ])->nullable();
            $table->integer('vpip')->nullable();
            $table->integer('pfr')->nullable();
            $table->integer('tilt_risk')->default(0);
            $table->boolean('profesional')->default(false);
            $table->string('version_rango')->nullable();
            $table->json('estado_juego')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};
