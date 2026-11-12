<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = ['limp', 'limp_raise', 'limp_push', 'call_vs_3bet', 'reraise_vs_3bet'];

    public function up(): void
    {
        Schema::table('bot_hud_stats', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->decimal($column, 5, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bot_hud_stats', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->dropColumn($column);
            }
        });
    }
};
