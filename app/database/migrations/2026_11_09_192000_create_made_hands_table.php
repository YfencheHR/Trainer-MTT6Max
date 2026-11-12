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
        Schema::create('made_hands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Top Pair, Two Pair, Set, etc.
            $table->text('description')->nullable();
            $table->decimal('value', 5, 2); // Valor absoluto para cálculos (Royal Flush = máximo)
            $table->integer('order')->default(0); // Para ordenar en el listado
            $table->boolean('is_special')->default(false); // Proyectos especiales
            $table->enum('type', ['pair', 'two_pair', 'set', 'straight', 'flush', 'full_house', 'quads', 'draw_project'])->default('pair');
            $table->boolean('applies_to_river')->default(true); // false para proyectos que no aplican en river
            $table->timestamps();

            $table->index(['order', 'is_special']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('made_hands');
    }
};
