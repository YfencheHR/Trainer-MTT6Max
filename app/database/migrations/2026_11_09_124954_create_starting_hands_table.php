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
        Schema::create('starting_hands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card1_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignId('card2_id')->constrained('cards')->cascadeOnDelete();
            $table->string('hand_code')->unique(); // AKs, AKo, AA, KK, etc.
            $table->string('hand_type'); // Pair, Suited, Offsuit
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('starting_hands');
    }
};
