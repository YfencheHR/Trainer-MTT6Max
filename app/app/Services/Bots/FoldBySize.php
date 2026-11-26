<?php

namespace App\Services\Bots;

use Illuminate\Support\Facades\DB;

/**
 * Cómo cambia la respuesta a una apuesta según su tamaño, con los datos de toda la población de PokerTracker
 * (tabla fold_to_bet_size_population, que guarda el importador).
 *
 * Devuelve multiplicadores RELATIVOS a la media de la calle (1 = lo habitual): ante una apuesta pequeña se abandona menos de lo normal
 * y ante una grande más; las subidas, al revés. El NIVEL de cada bot (cuánto abandona de media) sigue saliendo de sus propias estadísticas;
 * esto solo reparte ese nivel según el tamaño. Entre tramos se interpola linealmente (con el centro de cada tramo) para que no haya saltos.
 */
class FoldBySize
{
    /** Con menos enfrentamientos en un tramo no se fía de él (se interpola con los vecinos). */
    public const MIN_FACED = 200;

    /** Los multiplicadores se acotan para que un tramo raro no deje de abandonar o abandone siempre. */
    public const MIN_MULT = 0.4;

    public const MAX_MULT = 1.8;

    /** @var array<string, array<string, array{fold: float, raise: float}>>|null calle => tramo => multiplicadores */
    private ?array $table = null;

    /**
     * @param  float  $betPct  apuesta rival como % del bote anterior a ella
     * @return array{fold: float, raise: float} 1.0 y 1.0 si no hay datos
     */
    public function multipliers(string $street, float $betPct): array
    {
        $rows = $this->table()[$street] ?? [];
        if (count($rows) < 2) {
            return ['fold' => 1.0, 'raise' => 1.0];
        }

        // Puntos (tamaño central del tramo → multiplicador)
        $points = [];
        foreach (BetSizeMatrix::BUCKET_FRACTION as $bucket => $fraction) {
            if (isset($rows[$bucket])) {
                $points[] = [$fraction * 100, $rows[$bucket]];
            }
        }
        if (count($points) < 2) {
            return ['fold' => 1.0, 'raise' => 1.0];
        }
        $first = $points[0];
        $last = $points[count($points) - 1];
        if ($betPct <= $first[0]) {
            return $first[1];
        }
        if ($betPct >= $last[0]) {
            return $last[1];
        }
        for ($i = 1; $i < count($points); $i++) {
            if ($betPct <= $points[$i][0]) {
                [$x0, $a] = $points[$i - 1];
                [$x1, $b] = $points[$i];
                $t = ($betPct - $x0) / ($x1 - $x0);

                return ['fold' => $a['fold'] + $t * ($b['fold'] - $a['fold']), 'raise' => $a['raise'] + $t * ($b['raise'] - $a['raise'])];
            }
        }

        return ['fold' => 1.0, 'raise' => 1.0];
    }

    /** @return array<string, array<string, array{fold: float, raise: float}>> */
    public function table(): array
    {
        if ($this->table !== null) {
            return $this->table;
        }
        $byStreet = [];
        foreach (DB::table('fold_to_bet_size_population')->get() as $row) {
            $byStreet[$row->street][$row->bucket] = ['faced' => (int) $row->faced, 'folded' => (int) $row->folded, 'raised' => (int) $row->raised];
        }

        $out = [];
        foreach ($byStreet as $street => $buckets) {
            $faced = array_sum(array_column($buckets, 'faced'));
            if ($faced <= 0) {
                continue;
            }
            $foldAvg = array_sum(array_column($buckets, 'folded')) / $faced;
            $raiseAvg = array_sum(array_column($buckets, 'raised')) / $faced;
            foreach ($buckets as $bucket => $b) {
                if ($b['faced'] < self::MIN_FACED || $foldAvg <= 0) {
                    continue;
                }
                $out[$street][$bucket] = [
                    'fold' => max(self::MIN_MULT, min(self::MAX_MULT, ($b['folded'] / $b['faced']) / $foldAvg)),
                    'raise' => $raiseAvg > 0 ? max(self::MIN_MULT, min(self::MAX_MULT, ($b['raised'] / $b['faced']) / $raiseAvg)) : 1.0,
                ];
            }
        }

        return $this->table = $out;
    }
}
