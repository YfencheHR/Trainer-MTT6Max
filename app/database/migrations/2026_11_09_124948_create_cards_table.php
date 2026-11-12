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
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->string('rank'); // A, K, Q, J, 10, 9, 8, 7, 6, 5, 4, 3, 2
            $table->string('suit'); // Picas, Corazones, Tréboles, Espadas
            $table->string('code')->unique(); // AH, AD, AC, AS, KH, etc.
            $table->string('image_path')->nullable(); // Ruta de la imagen
            $table->timestamps();

            $table->unique(['rank', 'suit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
