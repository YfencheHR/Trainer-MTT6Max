<?php

namespace Database\Seeders;

use App\Models\HandGroup;
use Illuminate\Database\Seeder;

class HandGroupSeeder extends Seeder
{
    public function run(): void
    {
        $ranks = ['A', 'K', 'Q', 'J', 'T', '9', '8', '7', '6', '5', '4', '3', '2'];
        $handGroups = [];
        $generatedHands = [];

        foreach ($ranks as $i => $rank1) {
            foreach ($ranks as $j => $rank2) {
                // Generar todas las 13x13 = 169 combinaciones
                if ($rank1 === $rank2) {
                    $groupName = $rank1 . $rank2;
                    $handType = 'Pair';
                    $combinatorios = 6;
                    $gtoEv = 50 + (12 - $i) * 15;
                } elseif ($i < $j) {
                    // Triángulo superior = Suited
                    $groupName = $rank1 . $rank2 . 's';
                    $handType = 'Suited';
                    $combinatorios = 4;
                    $gtoEv = 40 + (12 - $i) * 10 + (12 - $j) * 5;
                } else {
                    // Triángulo inferior = Offsuit
                    $groupName = $rank2 . $rank1 . 'o';
                    $handType = 'Offsuit';
                    $combinatorios = 12;
                    $gtoEv = 30 + (12 - $j) * 8 + (12 - $i) * 4;
                }

                // Evitar duplicados
                if (!in_array($groupName, $generatedHands)) {
                    $generatedHands[] = $groupName;
                    $handGroups[] = [
                        'group_name' => $groupName,
                        'hand_type' => $handType,
                        'gto_ev' => round($gtoEv, 2),
                        'combinatorios' => $combinatorios,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        foreach ($handGroups as $group) {
            HandGroup::firstOrCreate(
                ['group_name' => $group['group_name']],
                $group,
            );
        }
    }
}
