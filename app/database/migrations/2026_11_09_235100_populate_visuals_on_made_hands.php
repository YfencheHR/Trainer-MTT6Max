<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $visuals = [
            'Escalera de Color Real' => [['cards' => ['A♠', 'K♠', 'Q♠', 'J♠', '10♠']]],
            'Escalera de Color' => [['cards' => ['10♠', '9♠', '8♠', '7♠', '6♠']]],
            'Póker' => [['cards' => ['A♠', 'A♥', 'A♣', 'A♦']]],
            'Full House' => [['cards' => ['A♠', 'A♥', 'A♣', 'K♦', 'K♥']]],
            'Color' => [['cards' => ['A♠', '9♠', '8♠', '6♠', '3♠']]],
            'Escalera' => [['cards' => ['10♠', '9♠', '8♠', '7♠', '6♠']]],
            'Tercera/Trips' => [['cards' => ['A♠', 'A♥', 'A♣']]],
            'Dos Parejas' => [['cards' => ['A♠', 'A♥', 'K♣', 'K♦']]],
            'Pareja Alta' => [
                ['cards' => ['A♠', 'A♥'], 'separator' => true],
                ['cards' => ['K♠', 'K♥'], 'separator' => true],
                ['cards' => ['Q♠', 'Q♥'], 'separator' => true],
                ['cards' => ['J♠', 'J♥']]
            ],
            'Pareja Media' => [
                ['cards' => ['10♠', '10♥'], 'separator' => true],
                ['cards' => ['9♠', '9♥'], 'separator' => true],
                ['cards' => ['7♠', '7♥']]
            ],
            'Pareja Baja' => [
                ['cards' => ['6♠', '6♥'], 'separator' => true],
                ['cards' => ['5♠', '4♥'], 'separator' => true],
                ['cards' => ['3♠', '3♥'], 'separator' => true],
                ['cards' => ['2♠', '2♥']]
            ],
            'Proyecto de Color' => [['cards' => ['A♠', '9♠', '8♠', '6♠', '?♠']]],
            'Proyecto de Escalera Abierta' => [['cards' => ['?♥', '10♠', '9♠', '8♠', '7♠', '?♥']]],
            'Proyecto de Escalera Barrigona' => [['cards' => ['10♠', '9♠', '?♣', '8♦', '6♥']]],
            'Proyecto Color + Escalera' => [['cards' => []]],
        ];

        foreach ($visuals as $name => $visualData) {
            DB::table('made_hands')
                ->where('name', $name)
                ->update(['visuals' => json_encode($visualData)]);
        }
    }

    public function down(): void
    {
        DB::table('made_hands')->update(['visuals' => null]);
    }
};
