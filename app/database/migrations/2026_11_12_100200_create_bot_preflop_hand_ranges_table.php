<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_preflop_hand_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->string('position', 3);
            $table->string('hand', 3);
            $table->decimal('attack_open', 5, 2)->default(0);
            $table->decimal('attack_limp', 5, 2)->default(0);
            $table->decimal('open_reraise', 5, 2)->default(0);
            $table->decimal('open_call', 5, 2)->default(0);
            $table->decimal('open_fold', 5, 2)->default(0);
            $table->decimal('limp_raise', 5, 2)->default(0);
            $table->decimal('limp_push', 5, 2)->default(0);
            $table->decimal('defense_three_bet', 5, 2)->default(0);
            $table->decimal('defense_call', 5, 2)->default(0);
            $table->decimal('defense_fold', 5, 2)->default(0);
            $table->boolean('is_manual')->default(false);
            $table->timestamps();

            $table->unique(['bot_id', 'position', 'hand']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_preflop_hand_ranges');
    }
};
