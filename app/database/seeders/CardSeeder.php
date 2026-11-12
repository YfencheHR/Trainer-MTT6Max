<?php

namespace Database\Seeders;

use App\Models\Card;
use Illuminate\Database\Seeder;

class CardSeeder extends Seeder
{
    public function run(): void
    {
        $ranks = ['A', 'K', 'Q', 'J', '10', '9', '8', '7', '6', '5', '4', '3', '2'];
        $suits = [
            ['name' => 'Picas', 'symbol' => '♠'],
            ['name' => 'Corazones', 'symbol' => '♥'],
            ['name' => 'Tréboles', 'symbol' => '♣'],
            ['name' => 'Espadas', 'symbol' => '♦']
        ];

        foreach ($ranks as $rank) {
            foreach ($suits as $suit) {
                Card::create([
                    'rank' => $rank,
                    'suit' => $suit['name'],
                    'code' => $rank . strtoupper(substr($suit['name'], 0, 1)),
                    'image_path' => '/images/cards/' . strtolower($rank) . '-' . strtolower(substr($suit['name'], 0, 1)) . '.svg'
                ]);
            }
        }
    }
}
