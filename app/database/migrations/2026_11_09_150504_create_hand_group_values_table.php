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
        Schema::create('hand_group_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hand_group_id')->constrained('hand_groups')->onDelete('cascade');
            $table->enum('scenario', ['Nash Push', 'Nash Call', 'Opener', 'Defense', 'Caller', 'Pusher', 'Ataque', 'Defensa', 'ISO/LIMP'])->default('Nash Push');
            $table->json('positions')->comment('["UTG", "BTN", "BB", ...]');
            $table->unsignedTinyInteger('stack_depth_min')->default(0)->comment('Minimum stack depth in bb');
            $table->unsignedTinyInteger('stack_depth_max')->default(255)->comment('Maximum stack depth in bb');
            $table->decimal('ev', 5, 2)->comment('Expected Value');
            $table->timestamps();

            $table->index(['scenario', 'stack_depth_min', 'stack_depth_max']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hand_group_values');
    }
};
