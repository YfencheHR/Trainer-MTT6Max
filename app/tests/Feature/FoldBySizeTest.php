<?php

namespace Tests\Feature;

use App\Services\Bots\Brain\StatsPrior;
use App\Services\Bots\FoldBySize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Respuesta a una apuesta según su tamaño (datos de la población): multiplicadores relativos a la media de la calle. */
class FoldBySizeTest extends TestCase
{
    use RefreshDatabase;

    private function populate(): void
    {
        $now = now();
        // Flop: abandona 38 % ante apuestas pequeñas, 50 % medianas y 66 % grandes; sube más cuanto menor es la apuesta
        foreach ([['25', 1000, 380, 120], ['60', 1000, 500, 60], ['max', 1000, 660, 60]] as [$bucket, $faced, $folded, $raised]) {
            DB::table('fold_to_bet_size_population')->insert(compact('bucket', 'faced', 'folded', 'raised') + ['street' => 'flop', 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function test_bigger_bets_fold_more_and_raise_less(): void
    {
        $this->populate();
        $by = new FoldBySize;

        $small = $by->multipliers('flop', 20);
        $big = $by->multipliers('flop', 110);

        $this->assertLessThan(1.0, $small['fold']);
        $this->assertGreaterThan(1.0, $big['fold']);
        $this->assertGreaterThan($small['fold'], $by->multipliers('flop', 50)['fold']);
        $this->assertGreaterThan($big['raise'], $small['raise']);
    }

    public function test_it_interpolates_between_buckets_and_is_neutral_without_data(): void
    {
        $this->populate();
        $by = new FoldBySize;

        $mid = $by->multipliers('flop', 80)['fold'];
        $this->assertGreaterThan($by->multipliers('flop', 50)['fold'], $mid);
        $this->assertLessThan($by->multipliers('flop', 110)['fold'], $mid);
        $this->assertSame(['fold' => 1.0, 'raise' => 1.0], $by->multipliers('river', 50), 'Sin datos de la calle no cambia nada');
    }

    public function test_the_prior_folds_more_to_a_big_bet_with_the_same_hand(): void
    {
        $prior = new StatsPrior;
        $base = ['own_f2bet' => 50, 'own_raise_vs_bet' => 8, 'range_rank' => 70, 'street' => 'flop'];

        $small = $prior->facingBet($base + ['size_fold_mult' => 0.7]);
        $big = $prior->facingBet($base + ['size_fold_mult' => 1.3]);

        $this->assertGreaterThan($small['fold'], $big['fold']);
        $this->assertEqualsWithDelta(100, $big['fold'] + $big['call'] + $big['raise'], 0.01);
    }
}
