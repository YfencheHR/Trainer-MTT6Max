<?php

namespace App\Services\Bots;

use Illuminate\Support\Facades\DB;

/**
 * Cuánto farol (apuestas con aire) tiene un perfil según su juego, para los perfiles con pocas apuestas vistas.
 *
 * Con las apuestas con cartas conocidas de TODOS los jugadores se ajusta, por calle, una recta: % de apuestas con aire en función
 * del PFR del jugador (agrupando a los jugadores por PFR, porque cada uno aporta pocas apuestas). Los datos reales de PokerTracker
 * lo respaldan: el farol sube de forma regular con la agresión (flop ~20 % con PFR 4 → ~35 % con PFR 32; turn 18 → 28 %;
 * river 12 → 21 %). El PFR sirve de predictor porque existe en todos los perfiles, incluso en los de poca muestra.
 */
class BluffPriorModel
{
    /** Con menos apuestas no se ajusta (la recta saldría de ruido). */
    public const MIN_POINTS = 300;

    /** El farol de un rango de apuesta no baja de este % ni sube de este otro, por muy extremo que sea el PFR. */
    public const MIN_SHARE = 3.0;
    public const MAX_SHARE = 60.0;

    /**
     * @param array<int, array{0: float, 1: int}> $points [pfr, 1 si la apuesta fue con aire]
     * @return array{intercept: float, slope: float, r2: float, n: int}|null
     */
    public static function fit(array $points, int $groups = 5): ?array
    {
        $n = count($points);
        if ($n < self::MIN_POINTS) {
            return null;
        }
        usort($points, fn($a, $b) => $a[0] <=> $b[0]);

        $size = intdiv($n, $groups);
        $bins = [];
        for ($i = 0; $i < $groups; $i++) {
            $slice = array_slice($points, $i * $size, $i === $groups - 1 ? null : $size);
            $count = count($slice);
            $bins[] = [
                'x' => array_sum(array_column($slice, 0)) / $count,
                'y' => 100 * array_sum(array_column($slice, 1)) / $count,
                'w' => $count,
            ];
        }

        $w = array_sum(array_column($bins, 'w'));
        $mx = array_sum(array_map(fn($b) => $b['w'] * $b['x'], $bins)) / $w;
        $my = array_sum(array_map(fn($b) => $b['w'] * $b['y'], $bins)) / $w;
        $vx = array_sum(array_map(fn($b) => $b['w'] * ($b['x'] - $mx) ** 2, $bins)) / $w;
        $vy = array_sum(array_map(fn($b) => $b['w'] * ($b['y'] - $my) ** 2, $bins)) / $w;
        $cv = array_sum(array_map(fn($b) => $b['w'] * ($b['x'] - $mx) * ($b['y'] - $my), $bins)) / $w;
        if ($vx <= 0) {
            return null;
        }
        $slope = $cv / $vx;

        return [
            'intercept' => round($my - $slope * $mx, 4),
            'slope' => round($slope, 4),
            'r2' => $vy > 0 ? round(($cv * $cv) / ($vx * $vy), 3) : 0.0,
            'n' => $n,
        ];
    }

    /** % de apuestas con aire que se espera con ese PFR. */
    public static function predict(array $model, float $pfr): float
    {
        return max(self::MIN_SHARE, min(self::MAX_SHARE, $model['intercept'] + $model['slope'] * $pfr));
    }

    /** @return array<string, array{intercept: float, slope: float, r2: float, n: int}> calle => modelo */
    public static function models(): array
    {
        $out = [];
        foreach (DB::table('bet_size_prior_model')->get() as $row) {
            $out[$row->street] = ['intercept' => (float) $row->intercept, 'slope' => (float) $row->slope, 'r2' => (float) $row->r2, 'n' => (int) $row->n];
        }

        return $out;
    }

    public static function saveModels(array $models): void
    {
        foreach ($models as $street => $m) {
            DB::table('bet_size_prior_model')->updateOrInsert(
                ['street' => $street],
                ['intercept' => $m['intercept'], 'slope' => $m['slope'], 'r2' => $m['r2'], 'n' => $m['n'], 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
