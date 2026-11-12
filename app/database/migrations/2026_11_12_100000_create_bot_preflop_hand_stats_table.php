<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_preflop_hand_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->enum('posicion', ['UTG', 'MP', 'CO', 'BTN', 'SB', 'BB']);
            $table->string('mano', 3);
            $table->bigInteger('open_opportunities')->default(0);
            $table->bigInteger('open_raises')->default(0);
            $table->bigInteger('limps')->default(0);
            $table->bigInteger('vs_open_opportunities')->default(0);
            $table->bigInteger('three_bets')->default(0);
            $table->bigInteger('vs_3bet_opportunities')->default(0);
            $table->bigInteger('open_reraises')->default(0);
            $table->bigInteger('open_calls')->default(0);
            $table->bigInteger('open_folds')->default(0);
            $table->bigInteger('limp_raise_opportunities')->default(0);
            $table->bigInteger('limp_raises')->default(0);
            $table->bigInteger('limp_pushes')->default(0);
            $table->timestamps();

            $table->unique(['bot_id', 'posicion', 'mano']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_preflop_hand_stats');
    }
};
