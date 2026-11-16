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
        Schema::create('hand_action_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_hand_id')->constrained('tournament_hands')->cascadeOnDelete();
            $table->foreignId('tournament_player_id')->constrained('tournament_players')->cascadeOnDelete();
            $table->string('street', 10);
            $table->string('sequence', 50);
            $table->json('ranges');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('tournament_hand_id');
            $table->index('tournament_player_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hand_action_ranges');
    }
};
