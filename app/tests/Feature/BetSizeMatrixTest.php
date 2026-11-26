<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotBetSizeCount;
use App\Services\Bots\BetSizeMatrix;
use App\Services\Bots\BetTier;
use App\Services\Bots\BotFreezer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Matriz de apuestas: clasificación de la mano y del tamaño, mezcla con la población y copia en los torneos. */
class BetSizeMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_classifies_the_hand_by_what_it_adds_to_the_board(): void
    {
        $board = ['A♠', 'K♦', '7♣', '2♥', '9♦'];

        $this->assertSame('air', BetTier::tier(['Q♣', 'J♣'], $board), 'Carta alta: no mejora el tablero');
        $this->assertSame('pair', BetTier::tier(['A♥', '3♣'], $board), 'Pareja de ases');
        $this->assertSame('strong', BetTier::tier(['7♠', '7♥'], $board), 'Trío');
        $this->assertSame('air', BetTier::tier(['A♣', 'K♣'], ['7♠', '7♦', '2♣', '9♥', 'J♦']), 'Con el tablero emparejado, A-K de arriba solo "gana por kicker": no mejora el tablero');
    }

    public function test_it_puts_each_size_in_its_bucket(): void
    {
        $this->assertSame('25', BetTier::bucket(20));
        $this->assertSame('25', BetTier::bucket(25));
        $this->assertSame('40', BetTier::bucket(33.3));
        $this->assertSame('60', BetTier::bucket(50));
        $this->assertSame('85', BetTier::bucket(75));
        $this->assertSame('max', BetTier::bucket(100));
        $this->assertSame('max', BetTier::bucket(300));
    }

    private function populate(): void
    {
        $now = now();
        // Población: en el river 20 % aire (100), 30 % pareja (150) y 50 % fuerte (250); la pareja apuesta casi siempre pequeño
        $rows = [
            ['river', 'air', '60', 100], ['river', 'pair', '25', 120], ['river', 'pair', 'max', 30], ['river', 'strong', 'max', 250],
        ];
        foreach ($rows as [$street, $tier, $bucket, $n]) {
            DB::table('bet_size_population')->insert(compact('street', 'tier', 'bucket', 'n') + ['created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function test_a_bot_without_bets_looks_like_the_population_and_one_with_many_moves_away(): void
    {
        $this->populate();
        $none = Bot::create(['nombre' => 'Sin apuestas', 'tipo' => 'pool', 'metadata' => ['source' => 'pt4_import']]);
        $bluffer = Bot::create(['nombre' => 'Farolero', 'tipo' => 'pool', 'metadata' => ['source' => 'pt4_import']]);
        BotBetSizeCount::create(['bot_id' => $bluffer->id, 'street' => 'river', 'tier' => 'air', 'bucket' => 'max', 'n' => 900]);
        BotBetSizeCount::create(['bot_id' => $bluffer->id, 'street' => 'river', 'tier' => 'strong', 'bucket' => 'max', 'n' => 100]);

        $matrix = new BetSizeMatrix;
        $same = $matrix->forBot($none)['river'];
        $far = $matrix->forBot($bluffer)['river'];

        $this->assertEqualsWithDelta(20, $same['bluff_share'], 0.1, 'Sin datos propios: la composición de la población');
        $this->assertEqualsWithDelta(100, array_sum($same['share']), 0.1);
        $this->assertEqualsWithDelta(80, $same['sizes']['pair']['25'], 0.1, 'La pareja apuesta pequeño como la población');
        $this->assertGreaterThan(70, $far['bluff_share'], 'Con 900 apuestas con aire pesa más el perfil que la población');
        $this->assertGreaterThan(95, $far['sizes']['air']['max'], 'Su aire apuesta grande');
        foreach ($far['sizes'] as $bySize) {
            $this->assertEqualsWithDelta(100, array_sum($bySize), 0.1);
        }
    }

    public function test_it_returns_nothing_without_any_data(): void
    {
        $bot = Bot::create(['nombre' => 'Vacío', 'tipo' => 'pool', 'metadata' => ['source' => 'pt4_import']]);

        $this->assertNull((new BetSizeMatrix)->forBot($bot));
    }

    public function test_it_samples_a_size_from_the_matrix_of_the_hand_type(): void
    {
        $matrix = new BetSizeMatrix;
        $data = ['river' => ['sizes' => ['air' => ['25' => 0, '40' => 0, '60' => 0, '85' => 0, 'max' => 100], 'pair' => ['25' => 80, '40' => 0, '60' => 20, '85' => 0, 'max' => 0]]]];

        $this->assertSame(1.1, $matrix->sampleFraction($data, 'river', 'air', 0.5), 'El aire de este perfil siempre apuesta grande');
        $this->assertSame(0.2, $matrix->sampleFraction($data, 'river', 'pair', 0.5));
        $this->assertSame(0.5, $matrix->sampleFraction($data, 'river', 'pair', 0.9));
        $this->assertNull($matrix->sampleFraction($data, 'flop', 'pair', 0.5), 'Sin datos de esa calle');
    }

    public function test_own_data_only_skips_bots_without_counts_or_prior(): void
    {
        $this->populate();
        $none = Bot::create(['nombre' => 'Sin datos propios', 'tipo' => 'pool', 'metadata' => ['source' => 'pt4_import']]);
        $with = Bot::create(['nombre' => 'Con datos propios', 'tipo' => 'pool', 'metadata' => ['source' => 'pt4_import']]);
        BotBetSizeCount::create(['bot_id' => $with->id, 'street' => 'river', 'tier' => 'air', 'bucket' => '60', 'n' => 5]);

        $matrix = new BetSizeMatrix;
        $this->assertNull($matrix->forId($none->id, true));
        $this->assertNotNull($matrix->forId($with->id, true));
        $this->assertNotNull($matrix->forId($none->id), 'Sin la restricción sí cae a la población');
    }

    public function test_frozen_copies_keep_the_bet_matrix(): void
    {
        $bot = Bot::create(['nombre' => 'Con matriz', 'tipo' => 'pool', 'metadata' => ['source' => 'pt4_import']]);
        BotBetSizeCount::create(['bot_id' => $bot->id, 'street' => 'flop', 'tier' => 'pair', 'bucket' => '40', 'n' => 42]);

        $copy = app(BotFreezer::class)->frozenFor($bot);

        $this->assertSame(42, (int) BotBetSizeCount::where('bot_id', $copy->id)->value('n'));
    }
}
