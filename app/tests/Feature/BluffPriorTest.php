<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Services\Bots\BetSizeMatrix;
use App\Services\Bots\BluffPriorModel;
use App\Services\Bots\BotFreezer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Prior de farol por estadísticas: lo que le toca a un perfil según su PFR cuando casi no se le han visto apuestas. */
class BluffPriorTest extends TestCase
{
    use RefreshDatabase;

    /** Jugadores sintéticos: cuanto más agresivos (PFR alto), más apuestas con aire. */
    private function points(int $perGroup = 120): array
    {
        $points = [];
        foreach ([[5, 0.20], [12, 0.25], [18, 0.30], [25, 0.35], [32, 0.40]] as [$pfr, $airRate]) {
            for ($i = 0; $i < $perGroup; $i++) {
                $points[] = [$pfr + ($i % 3) * 0.1, ($i / $perGroup) < $airRate ? 1 : 0];
            }
        }

        return $points;
    }

    public function test_it_fits_bluff_share_as_a_line_of_pfr(): void
    {
        $model = BluffPriorModel::fit($this->points());

        $this->assertNotNull($model);
        $this->assertGreaterThan(0.5, $model['slope'] * 10, 'Más PFR, más farol: ~0,74 puntos de farol por punto de PFR');
        $this->assertEqualsWithDelta(0.74, $model['slope'], 0.1);
        $this->assertGreaterThan(0.95, $model['r2']);
        $this->assertGreaterThan(BluffPriorModel::predict($model, 8), BluffPriorModel::predict($model, 30));
    }

    public function test_it_does_not_fit_with_too_few_bets_and_clamps_extremes(): void
    {
        $this->assertNull(BluffPriorModel::fit(array_slice($this->points(), 0, 100)));

        $model = ['intercept' => 10, 'slope' => 2, 'r2' => 1, 'n' => 1000];
        $this->assertSame(BluffPriorModel::MAX_SHARE, BluffPriorModel::predict($model, 200));
        $this->assertSame(BluffPriorModel::MIN_SHARE, BluffPriorModel::predict(['intercept' => 0, 'slope' => 0, 'r2' => 0, 'n' => 1], 10));
    }

    private function population(): void
    {
        $now = now();
        foreach ([['river', 'air', '60', 100], ['river', 'pair', '25', 150], ['river', 'strong', 'max', 250]] as [$street, $tier, $bucket, $n]) {
            DB::table('bet_size_population')->insert(compact('street', 'tier', 'bucket', 'n') + ['created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function test_a_profile_without_bets_takes_its_bluff_share_from_the_prior_not_from_the_population(): void
    {
        $this->population(); // la población tiene un 20 % de aire
        $maniac = Bot::create(['nombre' => 'Maníaco', 'tipo' => 'pool', 'metadata' => ['source' => 'pt4_import']]);
        $plain = Bot::create(['nombre' => 'Normal', 'tipo' => 'pool', 'metadata' => ['source' => 'pt4_import']]);
        DB::table('bot_bet_size_prior')->insert(['bot_id' => $maniac->id, 'street' => 'river', 'air_share' => 45, 'created_at' => now(), 'updated_at' => now()]);

        $matrix = new BetSizeMatrix;
        $m = $matrix->forBot($maniac)['river'];

        $this->assertEqualsWithDelta(45, $m['bluff_share'], 0.1);
        $this->assertEqualsWithDelta(100, array_sum($m['share']), 0.1);
        $this->assertEqualsWithDelta(55 * 250 / 400, $m['share']['strong'], 0.2, 'El resto se reparte como la población: pareja 150 y fuerte 250');
        $this->assertEqualsWithDelta(20, $matrix->forBot($plain)['river']['bluff_share'], 0.1, 'Sin prior: la población');
    }

    public function test_the_prior_travels_with_the_frozen_copy(): void
    {
        $bot = Bot::create(['nombre' => 'Con prior', 'tipo' => 'pool', 'metadata' => ['source' => 'pt4_import']]);
        DB::table('bot_bet_size_prior')->insert(['bot_id' => $bot->id, 'street' => 'flop', 'air_share' => 33.5, 'created_at' => now(), 'updated_at' => now()]);

        $copy = app(BotFreezer::class)->frozenFor($bot);

        $this->assertEquals(33.5, (float) DB::table('bot_bet_size_prior')->where('bot_id', $copy->id)->value('air_share'));
    }
}
