<?php

namespace App\Services\Bots;

use App\Models\BotPreflopHandStat;

/**
 * Inclinación por TIPO DE MANO aprendida de las manos que un rival enseñó en showdown.
 *
 * Todos los bots ordenan las manos con el mismo ranking genérico (Chen). Pero un jugador concreto puede preferir
 * pares, suited o ases débiles más (o menos) de lo que dice ese ranking. Las manos vistas en showdown cuentan
 * qué TIPO de manos juega, aunque no sirvan como frecuencias (solo se ven las que llegan a showdown: sesgo hacia
 * manos fuertes y hacia las que siguen en el bote).
 *
 * Para no aprender simplemente "las manos fuertes llegan más a showdown", la comparación se hace DENTRO de bandas
 * de fuerza: en cada banda se compara la composición de lo observado (qué clases de mano aparecen) con la que
 * predice el rango generado. Solo se aprende cuánto se desvía una clase de lo esperado a igual fuerza.
 * Los desvíos se suavizan hacia 0 según la muestra y se limitan, así que con pocos datos el ranking no cambia.
 *
 * Parámetros de ajuste (constantes de abajo): son de modelado, no datos; la auditoría (bots:audit-ranges) mide si
 * mejoran la cobertura del rango frente a lo observado.
 */
class HandTypeTilt
{
    public const CLASSES = [
        'pair', 'suited_ace', 'suited_broadway', 'suited_connected', 'suited_other',
        'offsuit_broadway', 'offsuit_ace', 'offsuit_other',
    ];

    /** Observaciones "virtuales" a favor del ranking genérico: cuanto mayor, más muestra hace falta para moverlo. */
    private const PSEUDO_COUNT = 15;

    /** Puntos de percentil que se mueve una clase por cada unidad de ln(lift) (con confianza total). */
    private const POINTS_PER_LOG = 10.0;

    /** Desplazamiento máximo (puntos de percentil) en cualquier dirección. */
    private const MAX_SHIFT = 10.0;

    /** Manos mostradas mínimas (suma de todas las posiciones y nodos) para aprender algo. */
    private const MIN_OBSERVATIONS = 60;

    /** Bandas de fuerza (por percentil genérico) en las que se controla la comparación. */
    private const BANDS = 10;

    private const RANKS = 'AKQJT98765432';
    private const DB_POSITION = ['MP' => 'HJ'];

    /**
     * Cada nodo "juega la mano": [columna de manos mostradas, sección de la tabla, campo de frecuencia generada].
     * Se usan solo nodos donde la mano se juega voluntariamente, que son los que enseñan el estilo del jugador.
     */
    private const NODES = [
        ['open_raises', 'attack', 'open'],
        ['limps', 'attack', 'limp'],
        ['three_bets', 'defense', 'three_bet'],
        ['vs_open_calls', 'defense', 'call'],
    ];

    public static function classify(string $hand): string
    {
        if (strlen($hand) === 2) {
            return 'pair';
        }
        $suited = str_ends_with($hand, 's');
        $hi = strpos(self::RANKS, $hand[0]);   // 0 = A … 12 = 2
        $lo = strpos(self::RANKS, $hand[1]);
        $bothBroadway = $lo <= 4;              // T o más
        if ($bothBroadway) {
            return $suited ? 'suited_broadway' : 'offsuit_broadway';
        }
        if ($hand[0] === 'A') {
            return $suited ? 'suited_ace' : 'offsuit_ace';
        }
        if ($suited && ($lo - $hi) <= 3) {
            return 'suited_connected';          // conectadas y con huecos de hasta 2
        }

        return $suited ? 'suited_other' : 'offsuit_other';
    }

    /**
     * Aprende el desplazamiento (en puntos de percentil, negativo = la clase se juega más de lo que dice el ranking)
     * de cada clase de mano.
     *
     * @param iterable<BotPreflopHandStat> $stats manos mostradas por posición
     * @param array $tables tablas generadas SIN inclinación (RivalPreflopTables::generate)
     * @return array<string, float> clase => desplazamiento; vacío si no hay muestra suficiente
     */
    public function learn(iterable $stats, array $tables): array
    {
        $byPosition = [];
        foreach ($stats as $row) {
            $byPosition[self::DB_POSITION[$row->posicion] ?? $row->posicion][$row->mano] = $row;
        }

        $observed = array_fill_keys(self::CLASSES, 0.0);
        $expected = array_fill_keys(self::CLASSES, 0.0);
        $total = 0.0;

        foreach ($tables as $position => $table) {
            $rows = $byPosition[$position] ?? [];
            if (! $rows) {
                continue;
            }
            $attack = collect($table['attack'])->keyBy('hand');
            $defense = collect($table['defense'])->keyBy('hand');

            foreach (self::NODES as [$column, $section, $field]) {
                $source = $section === 'attack' ? $attack : $defense;
                $bands = [];
                foreach ($rows as $hand => $row) {
                    $count = (float) $row->{$column};
                    $entry = $source[$hand] ?? null;
                    if ($count <= 0 || ! $entry) {
                        continue;
                    }
                    $band = min(self::BANDS - 1, (int) floor($entry['percentile'] / (100 / self::BANDS)));
                    $bands[$band]['obs'][self::classify($hand)] = ($bands[$band]['obs'][self::classify($hand)] ?? 0) + $count;
                }

                foreach ($bands as $band => $data) {
                    $bandObserved = array_sum($data['obs']);
                    // Masa generada (frecuencia × combinaciones) por clase dentro de la banda
                    $mass = array_fill_keys(self::CLASSES, 0.0);
                    $fallback = array_fill_keys(self::CLASSES, 0.0);
                    foreach ($source as $hand => $entry) {
                        if ((int) min(self::BANDS - 1, floor($entry['percentile'] / (100 / self::BANDS))) !== $band) {
                            continue;
                        }
                        $combos = $this->combos($hand);
                        $class = self::classify($hand);
                        $mass[$class] += (float) $entry[$field] * $combos;
                        $fallback[$class] += $combos;
                    }
                    // Si el rango generado no tiene nada en esta banda (hay manos vistas fuera de él), no hay una
                    // composición esperada del rango: se usa la composición natural de las manos de la banda.
                    $basis = array_sum($mass) > 0 ? $mass : $fallback;
                    $basisTotal = array_sum($basis);
                    if ($basisTotal <= 0) {
                        continue;
                    }
                    foreach (self::CLASSES as $class) {
                        $observed[$class] += $data['obs'][$class] ?? 0.0;
                        $expected[$class] += $bandObserved * $basis[$class] / $basisTotal;
                    }
                    $total += $bandObserved;
                }
            }
        }

        if ($total < self::MIN_OBSERVATIONS) {
            return [];
        }

        $shifts = [];
        foreach (self::CLASSES as $class) {
            $n = ($observed[$class] + $expected[$class]) / 2;
            $confidence = $n / ($n + self::PSEUDO_COUNT);
            $lift = ($observed[$class] + 0.5) / ($expected[$class] + 0.5);
            $shift = -self::POINTS_PER_LOG * $confidence * log($lift);
            $shifts[$class] = round(max(-self::MAX_SHIFT, min(self::MAX_SHIFT, $shift)), 2);
        }

        return array_filter($shifts, fn($s) => abs($s) >= 0.05) ? $shifts : [];
    }

    /**
     * Aplica los desplazamientos al percentil genérico y vuelve a ordenar, recalculando el percentil como
     * porcentaje acumulado de combinaciones (mismo criterio que PreflopRanking: punto medio de cada mano).
     *
     * @param array<string, float> $percentiles percentil genérico por mano
     * @param array<string, int|float> $weights combinaciones por mano
     * @param array<string, float> $shifts clase => desplazamiento
     * @return array<string, float>
     */
    public function apply(array $percentiles, array $weights, array $shifts): array
    {
        $score = [];
        foreach ($percentiles as $hand => $percentile) {
            $score[$hand] = $percentile + ($shifts[self::classify($hand)] ?? 0.0);
        }
        $hands = array_keys($percentiles);
        usort($hands, fn($a, $b) => [$score[$a], $percentiles[$a]] <=> [$score[$b], $percentiles[$b]]);

        $total = array_sum($weights);
        $out = [];
        $before = 0.0;
        foreach ($hands as $hand) {
            $weight = (float) $weights[$hand];
            $out[$hand] = 100.0 * ($before + $weight / 2) / $total;
            $before += $weight;
        }

        return $out;
    }

    private function combos(string $hand): int
    {
        return strlen($hand) === 2 ? 6 : (str_ends_with($hand, 's') ? 4 : 12);
    }
}
