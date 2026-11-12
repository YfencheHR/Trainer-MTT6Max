<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('made_hands', function (Blueprint $table) {
            if (!Schema::hasColumn('made_hands', 'probability')) {
                $table->decimal('probability', 8, 6)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('made_hands', function (Blueprint $table) {
            $table->dropColumn('probability');
        });
    }
};
