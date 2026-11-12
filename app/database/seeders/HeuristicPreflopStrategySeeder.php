<?php

namespace Database\Seeders;

use App\Services\Equity\HeuristicPreflopStrategyGenerator;
use Illuminate\Database\Seeder;

class HeuristicPreflopStrategySeeder extends Seeder
{
    public function run(): void
    {
        app(HeuristicPreflopStrategyGenerator::class)->generate();
    }
}
