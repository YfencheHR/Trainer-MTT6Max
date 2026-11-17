<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Services\Bots\Brain\PreflopRanking;
use App\Services\Bots\RivalPreflopTables;
use Database\Seeders\BotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** La forma de los rangos generados: premium que no se foldean, 3-bet polarizado y calls por jugabilidad. */
class RangeShapeTest extends TestCase
{
    use RefreshDatabase;

    private function tables(string $tipo): array
    {
        $this->seed(BotSeeder::class);

        return (new RivalPreflopTables(new PreflopRanking()))->generate(Bot::where('tipo', $tipo)->firstOrFail());
    }

    public function test_premium_hands_never_fold_for_any_profile(): void
    {
        foreach (['nit', 'tag', 'lag', 'roca', 'calling_station'] as $tipo) {
            foreach ($this->tables($tipo) as $position => $table) {
                $attack = collect($table['attack'])->keyBy('hand');
                $defense = collect($table['defense'])->keyBy('hand');
                foreach (['AA', 'KK', 'QQ', 'AKs', 'AKo'] as $hand) {
                    if ($attack[$hand]['open'] > 0) {
                        $this->assertLessThan(0.5, $attack[$hand]['open_response']['fold'], "$tipo $position $hand vs 3-bet");
                    }
                    if ($position !== 'UTG') {
                        $this->assertLessThan(0.5, $defense[$hand]['fold'], "$tipo $position $hand vs apertura");
                    }
                }
            }
        }
    }

    public function test_three_bet_is_polarized_with_blocker_bluffs_and_calls_favor_playable_hands(): void
    {
        $defense = collect($this->tables('lag')['BB']['defense'])->keyBy('hand');

        // Farol con bloqueador y suited conectada: se resuben más que una offsuit de similar fuerza que no se resube.
        $this->assertGreaterThan($defense['KJo']['three_bet'], $defense['A5s']['three_bet']);
        // Sin iniciativa, una suited conectada se paga más que una offsuit débil
        $this->assertGreaterThan($defense['K9o']['call'], $defense['87s']['call'] + $defense['87s']['three_bet']);
        // Las manos de valor se resuben más que los faroles
        $this->assertGreaterThan($defense['A5s']['three_bet'], $defense['AA']['three_bet']);
    }
}
