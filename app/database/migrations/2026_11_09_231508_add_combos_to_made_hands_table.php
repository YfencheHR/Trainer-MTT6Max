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
        Schema::table('made_hands', function (Blueprint $table) {
            if (!Schema::hasColumn('made_hands', 'combos')) {
                $table->integer('combos')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('made_hands', function (Blueprint $table) {
            $table->dropColumn('combos');
        });
    }
};
