<?php

namespace Tests\Unit;

use App\Services\Bots\HandTypeTilt;
use PHPUnit\Framework\TestCase;

class HandTypeTiltTest extends TestCase
{
    /** Las 169 manos con su percentil genérico y combinaciones (sin tocar la BD). */
    private function universe(): array
    {
        $ranks = str_split('AKQJT98765432');
        $hands = [];
        foreach ($ranks as $r => $high) {
            foreach ($ranks as $c => $low) {
                $hands[] = $r === $c ? $high . $low : ($r < $c ? $high . $low . 's' : $low . $high . 'o');
            }
        }
        $weights = [];
        foreach ($hands as $h) {
            $weights[$h] = strlen($h) === 2 ? 6 : (str_ends_with($h, 's') ? 4 : 12);
        }
        // Orden de fuerza simple y estable (alta + suited + par), suficiente para un ranking de prueba
        $score = [];
        $order = array_flip($ranks);
        foreach ($hands as $h) {
            $score[$h] = ($order[$h[0]] + $order[$h[1]]) - (strlen($h) === 2 ? 6 : 0) - (str_ends_with($h, 's') ? 1.5 : 0);
        }
        asort($score);
        $percentiles = [];
        $before = 0;
        foreach (array_keys($score) as $h) {
            $percentiles[$h] = 100 * ($before + $weights[$h] / 2) / 1326;
            $before += $weights[$h];
        }

        return [$percentiles, $weights];
    }

    private function tables(array $percentiles, float $frequency): array
    {
        $rows = fn(string $field) => array_map(fn($h, $p) => ['hand' => $h, 'percentile' => $p, $field => $frequency], array_keys($percentiles), $percentiles);
        $attack = array_map(fn($h, $p) => ['hand' => $h, 'percentile' => $p, 'open' => $frequency, 'limp' => 0.0], array_keys($percentiles), $percentiles);
        $defense = array_map(fn($h, $p) => ['hand' => $h, 'percentile' => $p, 'three_bet' => 0.0, 'call' => 0.0], array_keys($percentiles), $percentiles);

        return ['UTG' => ['attack' => $attack, 'defense' => $defense]];
    }

    private function stat(string $hand, int $openRaises): object
    {
        return (object) ['posicion' => 'UTG', 'mano' => $hand, 'open_raises' => $openRaises, 'limps' => 0, 'three_bets' => 0, 'vs_open_calls' => 0];
    }

    public function test_classifies_every_hand_into_exactly_one_class(): void
    {
        $expected = [
            'AA' => 'pair', '22' => 'pair', 'AKs' => 'suited_broadway', 'KQo' => 'offsuit_broadway', 'JTs' => 'suited_broadway',
            'A5s' => 'suited_ace', 'A5o' => 'offsuit_ace', 'T9s' => 'suited_connected', '86s' => 'suited_connected',
            'K7s' => 'suited_other', 'K7o' => 'offsuit_other', 'T9o' => 'offsuit_other', '72o' => 'offsuit_other',
        ];
        foreach ($expected as $hand => $class) {
            $this->assertSame($class, HandTypeTilt::classify($hand), $hand);
        }
        [$percentiles] = $this->universe();
        foreach (array_keys($percentiles) as $hand) {
            $this->assertContains(HandTypeTilt::classify($hand), HandTypeTilt::CLASSES);
        }
    }

    public function test_apply_without_shifts_keeps_the_order_and_a_valid_percentile_scale(): void
    {
        [$percentiles, $weights] = $this->universe();
        $out = (new HandTypeTilt())->apply($percentiles, $weights, []);

        $this->assertEqualsWithDelta($percentiles['AA'], $out['AA'], 1e-9);
        $this->assertEqualsWithDelta($percentiles['72o'], $out['72o'], 1e-9);
        $this->assertGreaterThan(0, min($out));
        $this->assertLessThan(100, max($out));
    }

    public function test_apply_moves_a_class_up_the_ranking(): void
    {
        [$percentiles, $weights] = $this->universe();
        $tilt = new HandTypeTilt();
        $base = $tilt->apply($percentiles, $weights, []);
        $moved = $tilt->apply($percentiles, $weights, ['suited_other' => -10.0]);

        $this->assertLessThan($base['K7s'], $moved['K7s']);          // la clase se juega más: sube en el ranking
        $this->assertGreaterThan($base['K7o'], $moved['K7o'] + 0.0001); // las demás no mejoran
        $this->assertEqualsWithDelta($moved['AA'], min($moved), 5);   // AA sigue entre las primeras
        // El percentil sigue siendo un % acumulado de combinaciones: la media ponderada ronda el 50
        $mean = array_sum(array_map(fn($h) => $moved[$h] * $weights[$h], array_keys($moved))) / array_sum($weights);
        $this->assertEqualsWithDelta(50.0, $mean, 1.0);
    }

    public function test_learn_returns_nothing_with_too_little_sample(): void
    {
        [$percentiles] = $this->universe();
        $stats = [$this->stat('K7s', 5), $this->stat('J5s', 5)];

        $this->assertSame([], (new HandTypeTilt())->learn($stats, $this->tables($percentiles, 50.0)));
    }

    public function test_learn_detects_a_class_shown_more_often_than_the_range_predicts(): void
    {
        [$percentiles] = $this->universe();
        $tables = $this->tables($percentiles, 50.0); // el rango generado es uniforme: espera la composición natural de cada banda

        // Todo lo que enseña son suited "otras" (K7s, J5s…) en todas las bandas
        $stats = [];
        foreach (array_keys($percentiles) as $hand) {
            if (HandTypeTilt::classify($hand) === 'suited_other') {
                $stats[] = $this->stat($hand, 6);
            }
        }
        $shifts = (new HandTypeTilt())->learn($stats, $tables);

        $this->assertNotEmpty($shifts);
        $this->assertLessThan(0, $shifts['suited_other']);   // se juegan más de lo esperado → suben
        $this->assertGreaterThan(0, $shifts['offsuit_other']); // las que nunca enseña → bajan
        foreach ($shifts as $shift) {
            $this->assertLessThanOrEqual(10.0, abs($shift));    // acotado
        }
    }

    public function test_learn_is_neutral_when_observed_matches_the_expected_composition(): void
    {
        [$percentiles, $weights] = $this->universe();
        $tables = $this->tables($percentiles, 50.0);
        // Enseña todas las manos en proporción a sus combinaciones: no hay nada que aprender
        $stats = [];
        foreach ($weights as $hand => $combos) {
            $stats[] = $this->stat($hand, $combos);
        }
        $shifts = (new HandTypeTilt())->learn($stats, $tables);

        // Sin nada que aprender devuelve [] (sin inclinación); si devuelve algo, es despreciable
        $this->assertTrue($shifts === [] || max(array_map('abs', $shifts)) < 0.5, json_encode($shifts));
    }
}
