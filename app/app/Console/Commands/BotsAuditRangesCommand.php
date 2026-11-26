<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Services\Bots\RivalPreflopTables;
use Illuminate\Console\Command;

/**
 * Audita los rangos preflop generados para los bots Pool:
 *  1. Invariantes: las manos de self::PREMIUM_NEVER_FOLD (AA) no foldean nunca; JJ/TT/AQs foldeando es aviso.
 *  2. Monotonía: dentro de cada nodo, una mano más débil no debe ser más agresiva que una más fuerte.
 *  3. Observado vs generado: compara cada nodo con lo registrado en manos reales (bot_preflop_hand_stats),
 *     ponderando por las oportunidades observadas de cada mano.
 *
 * OJO: regenerar las tablas las guarda (forBot persiste las filas no manuales), así que el comando también las actualiza.
 * Y la generación ya mezcla lo observado (PRIOR_OPPORTUNITIES), por lo que la comparación no es independiente del todo:
 * sirve para detectar desvíos grandes y datos con muestra pequeña, no para validar al milímetro.
 */
class BotsAuditRangesCommand extends Command
{
    protected $signature = 'bots:audit-ranges {bot? : id del bot (por defecto, todos los Pool)} {--min-samples=30 : oportunidades observadas mínimas para comparar un nodo} {--gap=15 : diferencia (puntos) observado/generado a partir de la cual se marca} {--tolerance=10 : tolerancia (puntos) en la comprobación de monotonía}';

    protected $description = 'Regenera y audita los rangos preflop de los bots Pool (invariantes, monotonía y comparación con manos registradas)';

    private const DB_POSITION = ['HJ' => 'MP'];

    /** Manos que no deberían foldear nunca (ni ante 3-bet ni ante apertura): ERROR si ocurre. No se fuerza en la generación, solo se comprueba. */
    private const PREMIUM_NEVER_FOLD = ['AA', 'KK', 'QQ', 'AKs', 'AKo'];

    private const PREMIUM_WARN = ['JJ', 'TT', 'AQs'];

    /** Nodos con manos vistas en showdown que se usan para la cobertura (el fold no tiene cartas visibles). */
    private const COVERAGE_NODES = ['open', 'limp', '3bet', 'call_open', 'reraise', 'call_3bet'];

    /** nodo => [etiqueta, columna de oportunidades, columna de éxitos, de dónde sale la frecuencia generada] */
    private const NODES = [
        'open' => ['RFI abrir', 'open_opportunities', 'open_raises', ['attack', 'open']],
        'limp' => ['limp', 'open_opportunities', 'limps', ['attack', 'limp']],
        '3bet' => ['3-bet vs apertura', 'vs_open_opportunities', 'three_bets', ['defense', 'three_bet']],
        'call_open' => ['call vs apertura', 'vs_open_opportunities', 'vs_open_calls', ['defense', 'call']],
        'reraise' => ['4-bet vs 3-bet', 'vs_3bet_opportunities', 'open_reraises', ['attack', 'open_response', 'reraise']],
        'call_3bet' => ['call vs 3-bet', 'vs_3bet_opportunities', 'open_calls', ['attack', 'open_response', 'call']],
        'fold_3bet' => ['fold vs 3-bet', 'vs_3bet_opportunities', 'open_folds', ['attack', 'open_response', 'fold']],
    ];

    public function handle(RivalPreflopTables $tables): int
    {
        $bots = $this->argument('bot')
            ? Bot::whereKey($this->argument('bot'))->get()
            : Bot::all()->filter(fn (Bot $b) => $b->isPool())->values();

        if ($bots->isEmpty()) {
            $this->error('No hay bots que auditar.');

            return self::FAILURE;
        }

        $single = $bots->count() === 1;
        $summary = [];
        $failed = false;

        foreach ($bots as $bot) {
            $baseline = $this->coverage($bot, $tables->generate($bot, false)); // ranking genérico, sin inclinación
            $data = $tables->forBot($bot);                                       // con inclinación (lo que se guarda)
            $report = $this->audit($bot, $data);
            $report['coverage'] = $this->coverage($bot, $data);
            $report['coverage_base'] = $baseline;
            $report['tilt'] = $tables->tiltFor($bot);
            $failed = $failed || $report['never_fold'] > 0;
            $cov = $report['coverage'];
            $summary[] = [
                $bot->id, mb_substr($bot->nombre, 0, 28), $report['never_fold'], $report['premium_warn'],
                $report['monotonic'], $report['gaps'],
                (int) $cov['seen'],
                $cov['seen'] > 0 ? $this->f(100 * $baseline['covered'] / max(1, $baseline['seen'])).' → '.$this->f(100 * $cov['covered'] / $cov['seen']).'%' : '-',
                $cov['seen'] > 0 ? $this->f($baseline['fit']).' → '.$this->f($cov['fit']).'%' : '-',
                $report['tilt'] ? 'sí' : 'no (poca muestra)',
            ];

            if ($single) {
                $this->detail($bot, $report);
            }
        }

        $this->newLine();
        $this->table(['bot', 'nombre', 'premium fold (ERROR)', 'JJ/TT/AQs fold (aviso)', 'monotonía', 'desvíos obs/gen', 'manos vistas', 'cobertura base → inclin.', 'ajuste base → inclin.', 'inclinación'], $summary);
        $this->line('Premium fold = JJ/TT/AQs con fold > 5 % dentro de su rango. Monotonía = manos débiles más agresivas que fuertes.');
        $this->line('Cobertura = % de manos vistas en showdown (jugando cada acción) que el rango incluye. Ajuste = frecuencia generada media en esas manos. "base" = ranking genérico; "inclin." = ajustado al estilo del rival.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{never_fold:int, premium_warn:int, monotonic:int, mono:array, gaps:int, compared:int, lines:array, comparison:array} */
    private function audit(Bot $bot, array $tables): array
    {
        $neverFold = $premium = 0;
        $mono = ['open' => 0, '3bet' => 0, 'reraise' => 0, 'fold' => 0];
        $lines = [];
        $tolerance = (float) $this->option('tolerance');

        foreach ($tables as $position => $table) {
            $attack = collect($table['attack'])->keyBy('hand');
            $defense = collect($table['defense'])->keyBy('hand');

            // 1. Invariantes
            foreach (self::PREMIUM_NEVER_FOLD as $hand) {
                $a = $attack[$hand] ?? null;
                $d = $defense[$hand] ?? null;
                if ($a && $a['open'] > 0 && $a['open_response']['fold'] > 0.5) {
                    $neverFold++;
                    $lines[] = "ERROR  $position $hand foldea {$this->f($a['open_response']['fold'])}% a un 3-bet";
                }
                if ($position !== 'UTG' && $d && $d['fold'] > 0.5) {
                    $neverFold++;
                    $lines[] = "ERROR  $position $hand foldea {$this->f($d['fold'])}% ante una apertura";
                }
            }
            foreach (self::PREMIUM_WARN as $hand) {
                $a = $attack[$hand] ?? null;
                $d = $defense[$hand] ?? null;
                if ($a && $a['open'] > 5 && $a['open_response']['fold'] > 5) {
                    $premium++;
                    $lines[] = "aviso  $position $hand foldea {$this->f($a['open_response']['fold'])}% a un 3-bet";
                }
                if ($position !== 'UTG' && $d && $d['fold'] > 5) {
                    $premium++;
                    $lines[] = "aviso  $position $hand foldea {$this->f($d['fold'])}% ante una apertura";
                }
            }

            // 2. Monotonía (de más fuerte a más débil)
            $ordered = collect($table['attack'])->sortBy('percentile')->values();
            $inRange = $ordered->filter(fn ($a) => $a['open'] > 5);
            $mono['open'] += $this->inversions($ordered->map(fn ($a) => $a['open'])->all(), true, $tolerance);
            $mono['reraise'] += $this->inversions($inRange->filter(fn ($a) => RivalPreflopTables::bluffScore($a['hand']) <= 0)->map(fn ($a) => $a['open_response']['reraise'])->all(), true, $tolerance);
            $mono['fold'] += $this->inversions($inRange->map(fn ($a) => $a['open_response']['fold'])->all(), false, $tolerance);
            if ($position !== 'UTG') {
                $mono['3bet'] += $this->inversions(collect($table['defense'])->sortBy('percentile')->filter(fn ($d) => RivalPreflopTables::bluffScore($d['hand']) <= 0)->map(fn ($d) => $d['three_bet'])->values()->all(), true, $tolerance);
            }
        }

        $comparison = $this->compare($bot, $tables);
        $gap = (float) $this->option('gap');

        return [
            'never_fold' => $neverFold,
            'premium_warn' => $premium,
            'monotonic' => array_sum($mono),
            'mono' => $mono,
            'gaps' => count(array_filter($comparison, fn ($c) => abs($c['obs'] - $c['gen']) > $gap)),
            'compared' => count($comparison),
            'lines' => $lines,
            'comparison' => $comparison,
        ];
    }

    /**
     * Cuenta manos que rompen la monotonía. $decreasing = true: la frecuencia debe bajar al empeorar la mano
     * (agresividad); false: debe subir (fold). Se tolera $tolerance puntos.
     */
    private function inversions(array $sequence, bool $decreasing, float $tolerance): int
    {
        $count = 0;
        $extreme = $decreasing ? INF : -INF;
        foreach ($sequence as $value) {
            if ($decreasing ? $value > $extreme + $tolerance : $value < $extreme - $tolerance) {
                $count++;
            }
            $extreme = $decreasing ? min($extreme, $value) : max($extreme, $value);
        }

        return $count;
    }

    /** @return list<array{pos:string,node:string,n:int,obs:float,gen:float}> */
    private function compare(Bot $bot, array $tables): array
    {
        $min = (int) $this->option('min-samples');
        $stats = $bot->preflopHandStats()->get()->groupBy('posicion');
        $out = [];

        foreach ($tables as $position => $table) {
            $rows = $stats->get(self::DB_POSITION[$position] ?? $position);
            if (! $rows) {
                continue;
            }
            $maps = ['attack' => collect($table['attack'])->keyBy('hand'), 'defense' => collect($table['defense'])->keyBy('hand')];

            foreach (self::NODES as $key => [$label, $oppCol, $succCol, $path]) {
                $opps = $succ = $genWeighted = 0.0;
                foreach ($rows as $row) {
                    $n = (float) $row->{$oppCol};
                    $entry = $maps[$path[0]][$row->mano] ?? null;
                    if ($n <= 0 || ! $entry) {
                        continue;
                    }
                    $value = $entry;
                    foreach (array_slice($path, 1) as $segment) {
                        $value = $value[$segment];
                    }
                    $opps += $n;
                    $succ += (float) $row->{$succCol};
                    $genWeighted += $n * (float) $value;
                }
                if ($opps >= $min) {
                    $out[] = ['pos' => $position, 'node' => $key, 'n' => (int) $opps, 'obs' => 100 * $succ / $opps, 'gen' => $genWeighted / $opps];
                }
            }
        }

        return $out;
    }

    /**
     * Cobertura del rango generado frente a las manos que el rival enseñó en showdown. Para cada nodo (posición × acción):
     *  - cobertura: % de las manos vistas jugando esa acción que el rango generado incluye (frecuencia >= 1 %);
     *  - ajuste: frecuencia generada media en esas manos vistas (más alto = el rango pone masa donde el rival juega);
     *  - borde: percentil (de la propia tabla) bajo el que está el 90 % de lo observado frente al del 90 % de la masa generada.
     * Las manos vistas en showdown están sesgadas hacia manos fuertes: la cobertura es una comprobación de soporte, no de frecuencias.
     *
     * @return array{seen:float, covered:float, fit:float, rows:list<array>}
     */
    private function coverage(Bot $bot, array $tables): array
    {
        $stats = $bot->preflopHandStats()->get()->groupBy('posicion');
        $seen = $covered = $fitSum = 0.0;
        $rows = [];

        foreach ($tables as $position => $table) {
            $statRows = $stats->get(self::DB_POSITION[$position] ?? $position);
            if (! $statRows) {
                continue;
            }
            $maps = ['attack' => collect($table['attack'])->keyBy('hand'), 'defense' => collect($table['defense'])->keyBy('hand')];

            foreach (self::COVERAGE_NODES as $key) {
                [$label, , $succCol, $path] = self::NODES[$key];
                $freq = function (array $entry) use ($path) {
                    $value = $entry;
                    foreach (array_slice($path, 1) as $segment) {
                        $value = $value[$segment];
                    }

                    return (float) $value;
                };

                $items = [];
                foreach ($statRows as $row) {
                    $count = (float) $row->{$succCol};
                    $entry = $maps[$path[0]][$row->mano] ?? null;
                    if ($count > 0 && $entry) {
                        $items[] = [$entry['percentile'], $count, $freq($entry)];
                    }
                }
                $total = array_sum(array_column($items, 1));
                if ($total <= 0) {
                    continue;
                }
                $nodeCovered = array_sum(array_map(fn ($i) => $i[2] >= 1.0 ? $i[1] : 0.0, $items));
                $nodeFit = array_sum(array_map(fn ($i) => $i[1] * $i[2], $items));
                $seen += $total;
                $covered += $nodeCovered;
                $fitSum += $nodeFit;

                // Bordes (percentil del 90 % acumulado)
                usort($items, fn ($a, $b) => $a[0] <=> $b[0]);
                $obsEdge = $this->edge(array_map(fn ($i) => [$i[0], $i[1]], $items));
                $generated = [];
                foreach ($maps[$path[0]] as $hand => $entry) {
                    $generated[] = [$entry['percentile'], $freq($entry) * (strlen($hand) === 2 ? 6 : (str_ends_with($hand, 's') ? 4 : 12))];
                }
                usort($generated, fn ($a, $b) => $a[0] <=> $b[0]);

                $rows[] = [
                    'pos' => $position, 'node' => $label, 'seen' => (int) $total,
                    'cov' => 100 * $nodeCovered / $total, 'fit' => $nodeFit / $total,
                    'obs_edge' => $obsEdge, 'gen_edge' => $this->edge($generated),
                ];
            }
        }

        return ['seen' => $seen, 'covered' => $covered, 'fit' => $seen > 0 ? $fitSum / $seen : 0.0, 'rows' => $rows];
    }

    /** Percentil por debajo del cual está el 90 % del peso. @param list<array{0:float,1:float}> $sorted ordenado por percentil */
    private function edge(array $sorted): ?float
    {
        $total = array_sum(array_column($sorted, 1));
        if ($total <= 0) {
            return null;
        }
        $acc = 0.0;
        foreach ($sorted as [$percentile, $weight]) {
            $acc += $weight;
            if ($acc >= 0.9 * $total) {
                return (float) $percentile;
            }
        }

        return null;
    }

    private function detail(Bot $bot, array $report): void
    {
        $this->info("Bot {$bot->id} · {$bot->nombre}");
        $this->line('  monotonía por nodo: '.json_encode($report['mono']));
        foreach (array_slice($report['lines'], 0, 40) as $line) {
            $this->line("  $line");
        }
        if (count($report['lines']) > 40) {
            $this->line('  … y '.(count($report['lines']) - 40).' más');
        }

        $gap = (float) $this->option('gap');
        $rows = array_map(fn ($c) => [
            $c['pos'], self::NODES[$c['node']][0], $c['n'], $this->f($c['obs']).'%', $this->f($c['gen']).'%',
            ($c['gen'] - $c['obs'] >= 0 ? '+' : '').$this->f($c['gen'] - $c['obs']).(abs($c['gen'] - $c['obs']) > $gap ? '  <<' : ''),
        ], $report['comparison']);
        $this->table(['pos', 'nodo', 'oportunidades', 'observado', 'generado', 'gen-obs'], $rows);

        $this->line('  Inclinación por tipo de mano (puntos de percentil; negativo = se juega más de lo que dice el ranking): '
            .($report['tilt'] ? json_encode($report['tilt']) : 'ninguna (muestra insuficiente)'));
        $coverageRows = array_map(fn ($c) => [
            $c['pos'], $c['node'], $c['seen'], $this->f($c['cov']).'%', $this->f($c['fit']).'%',
            $c['obs_edge'] === null ? '-' : $this->f($c['obs_edge']), $c['gen_edge'] === null ? '-' : $this->f($c['gen_edge']),
        ], $report['coverage']['rows']);
        $this->table(['pos', 'nodo', 'manos vistas', 'cobertura', 'ajuste', 'borde obs (p90)', 'borde gen (p90)'], $coverageRows);
    }

    private function f(float $v): string
    {
        return number_format($v, 1);
    }
}
