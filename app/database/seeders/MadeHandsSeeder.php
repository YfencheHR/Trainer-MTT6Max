<?php

namespace Database\Seeders;

use App\Models\MadeHand;
use Illuminate\Database\Seeder;

class MadeHandsSeeder extends Seeder
{
    public function run(): void
    {
        $madeHands = [
            // Made Hands - Ordered from best to worst
            [
                'name' => 'Escalera de Color Real',
                'value' => 100,
                'order' => 1,
                'type' => 'straight',
                'is_special' => false,
                'applies_to_river' => true,
                'combos' => 4,
                'probability' => 0.000154,
                'visuals' => [['cards' => ['A♠', 'K♠', 'Q♠', 'J♠', '10♠']]],
            ],
            [
                'name' => 'Escalera de Color',
                'value' => 95,
                'order' => 2,
                'type' => 'straight',
                'is_special' => false,
                'applies_to_river' => true,
                'combos' => 36,
                'probability' => 0.00139,
                'visuals' => [['cards' => ['10♠', '9♠', '8♠', '7♠', '6♠']]],
            ],
            [
                'name' => 'Póker',
                'value' => 90,
                'order' => 3,
                'type' => 'quads',
                'is_special' => false,
                'applies_to_river' => true,
                'combos' => 624,
                'probability' => 0.0240,
                'visuals' => [['cards' => ['A♠', 'A♥', 'A♣', 'A♦']]],
            ],
            [
                'name' => 'Full House',
                'value' => 85,
                'order' => 4,
                'type' => 'full_house',
                'is_special' => false,
                'applies_to_river' => true,
                'combos' => 3744,
                'probability' => 0.1441,
                'visuals' => [['cards' => ['A♠', 'A♥', 'A♣', 'K♦', 'K♥']]],
            ],
            [
                'name' => 'Color',
                'value' => 80,
                'order' => 5,
                'type' => 'flush',
                'is_special' => false,
                'applies_to_river' => true,
                'combos' => 5108,
                'probability' => 0.1965,
                'visuals' => [['cards' => ['A♠', '9♠', '8♠', '6♠', '3♠']]],
            ],
            [
                'name' => 'Escalera',
                'value' => 75,
                'order' => 6,
                'type' => 'straight',
                'is_special' => false,
                'applies_to_river' => true,
                'combos' => 10200,
                'probability' => 0.3925,
                'visuals' => [['cards' => ['10♠', '9♠', '8♠', '7♠', '6♠']]],
            ],
            [
                'name' => 'Tercera/Trips',
                'value' => 70,
                'order' => 7,
                'type' => 'set',
                'is_special' => false,
                'applies_to_river' => true,
                'combos' => 54912,
                'probability' => 2.1128,
                'visuals' => [['cards' => ['A♠', 'A♥', 'A♣']]],
            ],
            [
                'name' => 'Dos Parejas',
                'value' => 60,
                'order' => 8,
                'type' => 'two_pair',
                'is_special' => false,
                'applies_to_river' => true,
                'combos' => 123552,
                'probability' => 4.7539,
                'visuals' => [['cards' => ['A♠', 'A♥', 'K♣', 'K♦']]],
            ],
            [
                'name' => 'Pareja Alta',
                'value' => 50,
                'order' => 9,
                'type' => 'pair',
                'is_special' => false,
                'applies_to_river' => true,
                'combos' => 366080,
                'probability' => 14.0856,
                'visuals' => [
                    ['cards' => ['A♠', 'A♥'], 'separator' => true],
                    ['cards' => ['K♠', 'K♥'], 'separator' => true],
                    ['cards' => ['Q♠', 'Q♥'], 'separator' => true],
                    ['cards' => ['J♠', 'J♥']],
                ],
            ],
            [
                'name' => 'Pareja Media',
                'value' => 40,
                'order' => 10,
                'type' => 'pair',
                'is_special' => false,
                'applies_to_river' => true,
                'combos' => 366080,
                'probability' => 14.0856,
                'visuals' => [
                    ['cards' => ['10♠', '10♥'], 'separator' => true],
                    ['cards' => ['9♠', '9♥'], 'separator' => true],
                    ['cards' => ['8♠', '8♥'], 'separator' => true],
                    ['cards' => ['7♠', '7♥']],
                ],
            ],
            [
                'name' => 'Pareja Baja',
                'value' => 30,
                'order' => 11,
                'type' => 'pair',
                'is_special' => false,
                'applies_to_river' => true,
                'combos' => 366080,
                'probability' => 14.0856,
                'visuals' => [
                    ['cards' => ['6♠', '6♥'], 'separator' => true],
                    ['cards' => ['5♠', '4♥'], 'separator' => true],
                    ['cards' => ['3♠', '3♥'], 'separator' => true],
                    ['cards' => ['2♠', '2♥']],
                ],
            ],

            [
                'name' => 'Carta Alta',
                'value' => 0,
                'order' => 12,
                'type' => 'high_card',
                'is_special' => false,
                'applies_to_river' => true,
                'combos' => 1302540,
                'probability' => 50.1177,
                'visuals' => [['cards' => ['A♠']]],
            ],

            // Draw Projects - Special hands
            [
                'name' => 'Proyecto de Color',
                'value' => 35,
                'order' => 13,
                'type' => 'draw_project',
                'is_special' => true,
                'applies_to_river' => false,
                'combos' => 110940,
                'visuals' => [['cards' => ['X♠', 'X♠', 'X♠', 'X♠', '?♠']]],
            ],
            [
                'name' => 'Proyecto de Escalera Abierta',
                'value' => 32,
                'order' => 12,
                'type' => 'draw_project',
                'is_special' => true,
                'applies_to_river' => false,
                'combos' => 86244,
                'visuals' => [['cards' => ['?♥', 'B♠', 'C♠', 'D♠', 'E♠', '?♥']]],
            ],
            [
                'name' => 'Proyecto de Escalera Barrigona',
                'value' => 20,
                'order' => 14,
                'type' => 'draw_project',
                'is_special' => true,
                'applies_to_river' => false,
                'combos' => 257232,
                'visuals' => [['cards' => ['B♠', 'C♠', '?♣', 'D♦', 'E♥']]],
            ],
        ];

        foreach ($madeHands as $hand) {
            MadeHand::create($hand);
        }
    }
}
