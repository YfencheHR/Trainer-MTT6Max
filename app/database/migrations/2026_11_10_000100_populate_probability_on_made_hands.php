<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $probabilities = [
            'Escalera de Color Real' => 0.000154,
            'Escalera de Color' => 0.00139,
            'Póker' => 0.0240,
            'Full House' => 0.1441,
            'Color' => 0.1965,
            'Escalera' => 0.3925,
            'Tercera/Trips' => 2.1128,
            'Dos Parejas' => 4.7539,
            'Pareja Alta' => 42.2569,
            'Pareja Media' => 42.2569,
            'Pareja Baja' => 42.2569,
        ];

        foreach ($probabilities as $name => $probability) {
            DB::table('made_hands')
                ->where('name', $name)
                ->update(['probability' => $probability]);
        }
    }

    public function down(): void
    {
        DB::table('made_hands')->update(['probability' => null]);
    }
};
