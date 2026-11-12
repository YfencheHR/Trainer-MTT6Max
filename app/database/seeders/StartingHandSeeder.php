<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\StartingHand;
use Illuminate\Database\Seeder;

class StartingHandSeeder extends Seeder
{
    public function run(): void
    {
        $cards = Card::all();
        $hands = [];
        $handCodes = [];

        foreach ($cards as $card1) {
            foreach ($cards as $card2) {
                if ($card1->id >= $card2->id) continue;

                $rank1 = $card1->rank;
                $rank2 = $card2->rank;
                $suit1 = $card1->suit;
                $suit2 = $card2->suit;

                // Ordenar rangos para código consistente
                $ranks = [$rank1, $rank2];
                sort($ranks);

                if ($rank1 === $rank2) {
                    $handCode = $rank1 . $rank2;
                    $handType = 'Pair';
                } elseif ($suit1 === $suit2) {
                    $handCode = $ranks[1] . $ranks[0] . 's';
                    $handType = 'Suited';
                } else {
                    $handCode = $ranks[1] . $ranks[0] . 'o';
                    $handType = 'Offsuit';
                }

                if (!in_array($handCode, $handCodes)) {
                    $handCodes[] = $handCode;
                    $hands[] = [
                        'card1_id' => $card1->id,
                        'card2_id' => $card2->id,
                        'hand_code' => $handCode,
                        'hand_type' => $handType,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        }

        StartingHand::insert($hands);
    }
}
