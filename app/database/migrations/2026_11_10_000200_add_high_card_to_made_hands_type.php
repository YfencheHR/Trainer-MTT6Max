<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE made_hands DROP CONSTRAINT made_hands_type_check");
        DB::statement("ALTER TABLE made_hands ADD CONSTRAINT made_hands_type_check CHECK (type IN ('pair', 'two_pair', 'set', 'straight', 'flush', 'full_house', 'quads', 'draw_project', 'high_card'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE made_hands DROP CONSTRAINT made_hands_type_check");
        DB::statement("ALTER TABLE made_hands ADD CONSTRAINT made_hands_type_check CHECK (type IN ('pair', 'two_pair', 'set', 'straight', 'flush', 'full_house', 'quads', 'draw_project'))");
    }
};
