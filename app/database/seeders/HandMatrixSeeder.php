<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class HandMatrixSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            HandGroupSeeder::class,
            OtherScenariosSeeder::class,
            NashHandGroupValueSeeder::class,
        ]);
    }
}
