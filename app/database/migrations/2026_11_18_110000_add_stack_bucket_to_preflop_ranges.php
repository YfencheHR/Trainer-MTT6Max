<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas preflop por tramo de stack (en ciegas grandes): 'all' es la tabla general del perfil (la que se edita a mano y la
 * de respaldo) y '0-10', '10-20', '20-40', '40+' son las generadas con las estadísticas de cada tramo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bot_preflop_hand_ranges', 'stack_bucket')) {
            Schema::table('bot_preflop_hand_ranges', function (Blueprint $table) {
                $table->string('stack_bucket', 8)->default('all');
            });
            Schema::table('bot_preflop_hand_ranges', function (Blueprint $table) {
                $table->dropUnique(['bot_id', 'position', 'hand']);
                $table->unique(['bot_id', 'stack_bucket', 'position', 'hand']);
            });
        }
    }

    public function down(): void
    {
        DB::table('bot_preflop_hand_ranges')->where('stack_bucket', '<>', 'all')->delete();
        Schema::table('bot_preflop_hand_ranges', function (Blueprint $table) {
            $table->dropUnique(['bot_id', 'stack_bucket', 'position', 'hand']);
            $table->unique(['bot_id', 'position', 'hand']);
            $table->dropColumn('stack_bucket');
        });
    }
};
