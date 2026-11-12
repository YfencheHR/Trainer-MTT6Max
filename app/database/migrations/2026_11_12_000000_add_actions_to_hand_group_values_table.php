<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hand_group_values', function (Blueprint $table) {
            $table->json('actions')->nullable()->after('ev');
        });
    }

    public function down(): void
    {
        Schema::table('hand_group_values', function (Blueprint $table) {
            $table->dropColumn('actions');
        });
    }
};
