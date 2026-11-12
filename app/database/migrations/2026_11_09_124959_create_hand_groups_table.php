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
        Schema::create('hand_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_name'); // AA, KK, AKs, AKo, etc.
            $table->string('hand_type'); // Pair, Suited, Offsuit
            $table->decimal('gto_ev', 8, 4); // Valor EV GTO real
            $table->text('hands_in_group')->nullable(); // JSON con las manos que forman el grupo
            $table->integer('combinatorios')->default(0); // Número de combinatorios posibles
            $table->timestamps();

            $table->unique('group_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hand_groups');
    }
};
