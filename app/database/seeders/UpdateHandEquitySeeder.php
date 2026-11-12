<?php

namespace Database\Seeders;

use App\Models\HandGroup;
use Illuminate\Database\Seeder;

class UpdateHandEquitySeeder extends Seeder
{
    public function run(): void
    {
        $equityData = [
            'AA' => 85.2,
            'KK' => 82.4,
            'QQ' => 79.9,
            'JJ' => 77.5,
            'AKs' => 67.0,
            'TT' => 75.0,
            'AQs' => 66.2,
            'AKo' => 65.3,
            '99' => 72.1,
            '88' => 69.1,
            'AJs' => 65.4,
            'KQs' => 63.4,
            'AQo' => 63.0,
            'KQo' => 61.4,
            'KJs' => 64.0,
            'QJs' => 62.5,
            'JTs' => 61.2,
            'ATo' => 62.0,
            '77' => 66.2,
            '66' => 64.5,
            '55' => 62.8,
            '44' => 61.2,
            '33' => 59.5,
            '22' => 50.3,
            'KJo' => 59.0,
            'QTo' => 59.0,
            '87s' => 48.1,
            '76s' => 45.5,
            '72o' => 34.6,
            '32o' => 32.3,
        ];

        foreach ($equityData as $handName => $equity) {
            HandGroup::where('group_name', $handName)->update(['gto_ev' => $equity]);
        }
    }
}
