<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('made_hands')->where('name', 'Escalera de Color Real')->update(['combos' => 4]);
        DB::table('made_hands')->where('name', 'Escalera de Color')->update(['combos' => 16]);
        DB::table('made_hands')->where('name', 'Póker')->update(['combos' => 1]);
        DB::table('made_hands')->where('name', 'Full House')->update(['combos' => 6]);
        DB::table('made_hands')->where('name', 'Color')->update(['combos' => 10]);
        DB::table('made_hands')->where('name', 'Escalera')->update(['combos' => 64]);
        DB::table('made_hands')->where('name', 'Tercera/Trips')->update(['combos' => 3]);
        DB::table('made_hands')->where('name', 'Dos Parejas')->update(['combos' => 9]);
        DB::table('made_hands')->where('name', 'Pareja Alta')->update(['combos' => 6]);
        DB::table('made_hands')->where('name', 'Pareja Media')->update(['combos' => 6]);
        DB::table('made_hands')->where('name', 'Pareja Baja')->update(['combos' => 6]);
        DB::table('made_hands')->where('name', 'Proyecto de Color')->update(['combos' => 9]);
        DB::table('made_hands')->where('name', 'Proyecto de Escalera Abierta')->update(['combos' => 16]);
        DB::table('made_hands')->where('name', 'Proyecto de Escalera Barrigona')->update(['combos' => 4]);
        DB::table('made_hands')->where('name', 'Proyecto Color + Escalera')->update(['combos' => 15]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('made_hands')->update(['combos' => 0]);
    }
};
