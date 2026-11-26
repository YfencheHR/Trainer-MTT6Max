<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ScoresAgainstRealActions;
use App\Services\Ai\AiClient;
use App\Services\Ai\DecisionContract;
use App\Services\Ai\HandHistoryParser;
use Illuminate\Console\Command;

/**
 * Compara las decisiones preflop de la IA con las reales de un jugador en manos de PokerTracker (herramienta de desarrollo).
 *
 * Con las primeras manos del jugador se calcula su perfil (vpip, pfr, 3-bet), el de cada rival y lo que hace en cada situación
 * (su «tabla»: con todas las manos y con cada mano); la IA se evalúa con OTRAS manos distintas (sin fugas). Cada decisión se pregunta en varios modos:
 *   plain → el reparto del jugador en esa situación con todas sus manos (sin la tabla de la mano exacta), sin adaptación al rival
 *   exact → además lo que su tabla hace con esa mano exacta (cuando tiene muestra suficiente)
 *   full  → además la adaptación al rival (lo suelto o selectivo que es)
 * Se compara con dos líneas base sin IA: «solo el reparto de la situación» y «la tabla de la mano» (lo que haría un bot de tablas).
 */
class AiEvaluatePreflopCommand extends Command
{
    use ScoresAgainstRealActions;

    protected $signature = 'ai:evaluate-preflop {file : TSV id_hand<TAB>history (volcado de tourney_hand_histories)}
        {--player= : jugador a evaluar (por defecto, el héroe de las manos)}
        {--profile-hands=12000 : manos para calcular el perfil y las tablas}
        {--limit=100 : decisiones a evaluar}
        {--modes=plain,exact,full : modos a comparar}
        {--min-spot=30 : manos mínimas de una situación para fiarse de su reparto}
        {--min-hand=6 : veces mínimas que tuvo esa mano en la situación para fiarse de su tabla}
        {--min-rival=30 : manos mínimas de un rival para darle perfil}
        {--dump= : números de decisión cuyo estado se guarda en storage/app/ai/eval-preflop-states.json; no llama al modelo}
        {--out=ai/eval-preflop.json : resultado detallado (dentro de storage/app)}';

    protected $description = 'Evalúa la IA del preflop contra manos reales de PokerTracker';

    /** Cuántas veces pesa el reparto de la situación al suavizar la tabla de una mano con pocas muestras. */
    private const SMOOTHING = 4;

    public function handle(HandHistoryParser $parser, DecisionContract $contract, AiClient $client): int
    {
        $file = fopen($this->argument('file'), 'r');
        if (! $file) {
            $this->error('No se puede abrir el archivo');

            return 1;
        }
        $profileHands = (int) $this->option('profile-hands');
        $player = $this->option('player');
        $modes = array_values(array_intersect(['plain', 'exact', 'full'], explode(',', (string) $this->option('modes'))));

        // Pasada única: las primeras manos del jugador dan su perfil y sus tablas; las siguientes son de evaluación
        $everyone = [];
        $spots = [];
        $byHand = [];
        $pool = [];
        $seen = 0;
        while (($line = fgets($file)) !== false) {
            [, $history] = array_pad(explode("\t", rtrim($line, "\r\n"), 2), 2, null);
            $hand = $history ? $parser->parse($history) : null;
            if ($hand && count($hand['players']) > 6) {
                $hand = null; // la aplicación es 6-max: las manos de mesas más grandes no se usan
            }
            if ($hand && $seen < $profileHands) {
                foreach ($hand['players'] as $p) {
                    $everyone[$p['name']] ??= [];
                    $this->tallyStats($hand, $p['name'], $everyone[$p['name']]);
                }
            }
            if (! $hand || ! $hand['hero'] || ($player && $hand['hero'] !== $player)) {
                continue;
            }
            $player ??= $hand['hero'];
            $seen++;
            if ($seen <= $profileHands) {
                $this->tallyTable($hand, $player, $spots, $byHand);
            } else {
                $pool[] = $hand;
                if (count($pool) >= 4000) {
                    break;
                }
            }
        }
        fclose($file);

        $heroStats = $this->statsFrom($everyone[$player] ?? [], 1);
        $this->info("Jugador: {$player} · manos para el perfil: ".min($seen, $profileHands).' · manos de evaluación: '.count($pool).' · modos: '.implode(', ', $modes));
        $this->line('Perfil preflop: '.json_encode($heroStats));
        $this->line('Situaciones con reparto fiable: '.count(array_filter($spots, fn ($s) => array_sum($s) >= (int) $this->option('min-spot'))).' de '.count($spots));

        // Una decisión por mano (la primera del jugador), al azar pero siempre las mismas
        mt_srand(7);
        shuffle($pool);
        $rows = [];
        foreach ($pool as $hand) {
            if (count($rows) >= (int) $this->option('limit')) {
                break;
            }
            $row = $this->row($hand, $player, $contract, $everyone, $heroStats, $spots, $byHand);
            if ($row) {
                $rows[] = $row;
            }
        }

        if ($this->option('dump')) {
            $states = [];
            foreach (array_filter(explode(',', (string) $this->option('dump'))) as $number) {
                $row = $rows[(int) $number - 1] ?? null;
                if ($row) {
                    $states[$number] = ['state' => $this->stateFor($row, 'full'), 'real' => $row['real_exact'], 'spot' => $row['spot'], 'key' => $row['key'], 'allowed' => $row['allowed']];
                }
            }
            file_put_contents(storage_path('app/ai/eval-preflop-states.json'), json_encode($states, JSON_UNESCAPED_UNICODE));
            $this->line('Estados guardados: '.implode(', ', array_keys($states)));

            return 0;
        }

        $report = [];
        foreach ($rows as $n => $row) {
            $answers = [];
            foreach ($modes as $mode) {
                try {
                    $dist = $contract->restrictToLegal($client->decide($this->stateFor($row, $mode), $row['allowed'], 1), $row['legal']);
                } catch (\Throwable $e) {
                    $this->warn('Decisión '.($n + 1)." ({$mode}): ".$e->getMessage());

                    continue 2;
                }
                $coarse = array_fill_keys(self::CLASSES, 0.0);
                foreach ($dist as $action => $p) {
                    $coarse[$this->coarse($action)] += $p;
                }
                $answers[$mode] = $coarse;
            }
            $report[] = [
                'spot' => $row['spot'], 'real' => $row['real'], 'real_exact' => $row['real_exact'], 'modes' => $answers,
                'base' => $row['base'], 'table_base' => $row['table_base'], 'hand' => $row['class'], 'position' => $row['position'],
                'key' => $row['key'], 'has_table' => (bool) $row['table'], 'has_hand' => (bool) ($row['table']['hand'] ?? null),
            ];
            $this->line(sprintf('%3d/%d  %-14s %-4s %-4s real=%-5s %s', $n + 1, count($rows), $row['spot'], $row['position'], $row['class'], $row['real'],
                implode(' | ', array_map(fn ($m) => $m.': '.$this->fmt($answers[$m]), array_keys($answers)))));
        }

        $this->summary($report, $modes);
        $out = storage_path('app/'.$this->option('out'));
        @mkdir(dirname($out), 0777, true);
        file_put_contents($out, json_encode(['player' => $player, 'profile' => $heroStats, 'rows' => $report], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->line("Detalle: {$out}");

        return 0;
    }

    /** Estado que ve la IA en cada modo. */
    private function stateFor(array $row, string $mode): array
    {
        $state = $row['state'] + ['adaptation' => $mode === 'full' ? 1.0 : 0.0];
        if ($row['table']) {
            $table = $row['table'];
            if ($mode === 'plain') {
                unset($table['hand'], $table['hand_samples']);
            }
            $state['table'] = $table;
        }

        return $state;
    }

    /** La decisión preflop de una mano: su estado, la acción real, la situación y las dos líneas base. */
    private function row(array $hand, string $player, DecisionContract $contract, array $everyone, array $heroStats, array $spots, array $byHand): ?array
    {
        foreach ($hand['actions'] as $i => $a) {
            if ($a['player'] !== $player || $a['street'] !== 'preflop') {
                continue;
            }
            $situation = $this->situation($hand, $i);
            $me = collect($hand['players'])->firstWhere('name', $player);
            if (! $situation || ! $me || ! $me['hole_cards']) {
                return null;
            }
            $profiles = [$player => ['preflop_stats' => $heroStats]];
            foreach ($hand['players'] as $p) {
                $observed = $this->statsFrom($everyone[$p['name']] ?? [], (int) $this->option('min-rival'));
                if ($p['name'] !== $player && $observed) {
                    $profiles[$p['name']] = ['preflop_stats' => $observed];
                }
            }
            $built = $contract->fromHistory($hand, $i, $profiles);
            if (! $built) {
                return null;
            }
            $class = $this->handClass($me['hole_cards']);
            $table = $this->tableFor($spots, $byHand, $situation, $class);
            $facing = $a['to_call'] > 0;
            $legal = ['fold' => $facing, 'check' => ! $facing, 'call' => $facing ? $a['to_call'] : null, 'raise' => ['min' => 1, 'max' => 1], 'allin' => 1];

            return [
                'state' => $built['state'], 'real' => $this->coarse($built['action']), 'real_exact' => $built['action'], 'spot' => $situation['spot'],
                'key' => $table['key'] ?? $situation['key'], 'class' => $class, 'position' => $a['position'], 'legal' => $legal,
                'allowed' => $contract->allowedActions($legal, 'preflop', $situation['raises']),
                'table' => $table ? ['spot' => $table['spot']] + ($table['hand'] ? ['hand' => $table['hand'], 'hand_samples' => $table['hand_samples']] : []) : null,
                'base' => $this->classes($table['spot'] ?? $this->fallback($spots)),
                'table_base' => $this->classes($table['smoothed'] ?? $table['spot'] ?? $this->fallback($spots)),
            ];
        }

        return null;
    }

    /**
     * En qué situación está el jugador al hablar: la misma clasificación que hace el servicio de Python.
     *
     * @return array{spot: string, key: string, raises: int}|null null si no la cubre ninguna situación
     */
    private function situation(array $hand, int $i): ?array
    {
        $a = $hand['actions'][$i];
        $before = array_values(array_filter($hand['actions'], fn ($x) => $x['street'] === 'preflop' && $x['order'] < $a['order']));
        $raises = array_values(array_filter($before, fn ($x) => $x['action'] === 'raise'));
        $acted = (bool) array_filter($before, fn ($x) => $x['player'] === $a['player'] && $x['action'] !== 'fold');
        if ($acted) {
            if (! $raises || $a['to_call'] <= 0) {
                return null;
            }
            $spot = 'facing_reraise';
        } else {
            $spot = match (count($raises)) {
                0 => 'open',
                1 => 'facing_open',
                default => 'cold_reraise',
            };
        }
        $key = $spot.'|'.$a['position'].($spot === 'facing_open' ? '|'.$raises[0]['position'] : '');

        return ['spot' => $spot, 'key' => $key, 'raises' => count($raises)];
    }

    /** Cuenta lo que hizo el jugador en cada situación, con todas sus manos y mano por mano. */
    private function tallyTable(array $hand, string $player, array &$spots, array &$byHand): void
    {
        $me = collect($hand['players'])->firstWhere('name', $player);
        if (! $me || ! $me['hole_cards']) {
            return;
        }
        $class = $this->handClass($me['hole_cards']);
        foreach ($hand['actions'] as $i => $a) {
            if ($a['player'] !== $player || $a['street'] !== 'preflop') {
                continue;
            }
            $situation = $this->situation($hand, $i);
            if (! $situation) {
                continue;
            }
            $action = $a['action'] === 'raise' || $a['action'] === 'bet' ? 'raise' : $a['action'];
            $parts = explode('|', $situation['key']);
            // Del más concreto al más general: situación × posición × quien abrió, situación × posición y la situación sola
            foreach (array_unique([$situation['key'], $parts[0].'|'.$parts[1], $parts[0]]) as $key) {
                $spots[$key][$action] = ($spots[$key][$action] ?? 0) + 1;
                $byHand[$key][$class][$action] = ($byHand[$key][$class][$action] ?? 0) + 1;
            }
        }
    }

    /**
     * Lo que su perfil hace en esta situación (con todas sus manos y con esta mano), del nivel más concreto que tenga muestra suficiente.
     *
     * @return array{spot: array, hand: ?array, hand_samples: ?int, smoothed: array, key: string}|null
     */
    private function tableFor(array $spots, array $byHand, array $situation, string $class): ?array
    {
        $parts = explode('|', $situation['key']);
        foreach (array_unique([$situation['key'], $parts[0].'|'.$parts[1], $parts[0]]) as $key) {
            if (array_sum($spots[$key] ?? []) < (int) $this->option('min-spot')) {
                continue;
            }
            $spot = $this->percent($spots[$key]);
            $tally = $byHand[$key][$class] ?? [];
            $n = array_sum($tally);
            $hand = $n >= (int) $this->option('min-hand') ? $this->percent($tally) : null;
            $smoothed = $spot;
            if ($n > 0) {
                $smoothed = [];
                foreach (array_unique(array_merge(array_keys($spot), array_keys($tally))) as $action) {
                    $smoothed[$action] = round(($n * 100 * ($tally[$action] ?? 0) / $n + self::SMOOTHING * ($spot[$action] ?? 0)) / ($n + self::SMOOTHING), 1);
                }
            }

            return ['spot' => $spot, 'hand' => $hand, 'hand_samples' => $hand ? $n : null, 'smoothed' => $smoothed, 'key' => $key];
        }

        return null;
    }

    /** Reparto de todo lo que hizo en cualquier situación: la línea base de las que no tienen muestra. */
    private function fallback(array $spots): array
    {
        $total = [];
        foreach ($spots as $key => $tally) {
            if (! str_contains($key, '|')) {
                foreach ($tally as $action => $n) {
                    $total[$action] = ($total[$action] ?? 0) + $n;
                }
            }
        }

        return $total ? $this->percent($total) : ['fold' => 100.0];
    }

    /** @return array<string, float> */
    private function percent(array $counts): array
    {
        $sum = max(1, array_sum($counts));

        return array_map(fn ($n) => round(100 * $n / $sum, 1), $counts);
    }

    /** De un reparto en % (raise/call/check/fold) a las clases del informe (aggr/call/check/fold) en probabilidad. */
    private function classes(array $percent): array
    {
        $out = array_fill_keys(self::CLASSES, 0.0);
        foreach ($percent as $action => $value) {
            $out[$this->coarse($action === 'raise' ? 'raise' : $action)] += $value / 100;
        }
        $sum = array_sum($out);

        return $sum > 0 ? array_map(fn ($v) => $v / $sum, $out) : $out;
    }

    /** Cuenta, para un jugador en una mano, lo que hace falta para su vpip, pfr y 3-bet. */
    private function tallyStats(array $hand, string $name, array &$c): void
    {
        $mine = array_values(array_filter($hand['actions'], fn ($a) => $a['player'] === $name && $a['street'] === 'preflop'));
        $c['hands'] = ($c['hands'] ?? 0) + 1;
        if (array_filter($mine, fn ($a) => in_array($a['action'], ['call', 'raise', 'bet'], true))) {
            $c['vpip'] = ($c['vpip'] ?? 0) + 1;
        }
        if (array_filter($mine, fn ($a) => $a['action'] === 'raise')) {
            $c['pfr'] = ($c['pfr'] ?? 0) + 1;
        }
        $first = $mine[0] ?? null;
        if ($first) {
            $before = array_filter($hand['actions'], fn ($x) => $x['street'] === 'preflop' && $x['order'] < $first['order'] && $x['action'] === 'raise');
            if (count($before) === 1) {
                $c['three_bet_chances'] = ($c['three_bet_chances'] ?? 0) + 1;
                $c['three_bet'] = ($c['three_bet'] ?? 0) + ($first['action'] === 'raise' ? 1 : 0);
            }
        }
    }

    /** Estadísticas preflop de un jugador (vacío si tiene menos de $min manos). */
    private function statsFrom(array $c, int $min): array
    {
        $hands = $c['hands'] ?? 0;
        if ($hands < $min) {
            return [];
        }
        $stats = ['vpip' => round(100 * ($c['vpip'] ?? 0) / $hands, 1), 'pfr' => round(100 * ($c['pfr'] ?? 0) / $hands, 1), 'hands' => $hands];
        if (($c['three_bet_chances'] ?? 0) >= 10) {
            $stats['three_bet'] = round(100 * ($c['three_bet'] ?? 0) / $c['three_bet_chances'], 1);
        }

        return $stats;
    }

    private function summary(array $report, array $modes): void
    {
        $this->newLine();
        $with = count(array_filter($report, fn ($r) => $r['has_table']));
        $exact = count(array_filter($report, fn ($r) => $r['has_hand']));
        $this->line("Decisiones con reparto de la situación: {$with} de ".count($report).' · con tabla de la mano exacta: '.$exact);
        $who = ['base' => 'Solo la situación', 'table_base' => 'Tabla de la mano'] + array_combine($modes, array_map(fn ($m) => "IA {$m}", $modes));
        $groups = ['todas' => null] + array_combine($spots = array_values(array_unique(array_column($report, 'spot'))), $spots);
        foreach ($groups as $label => $spot) {
            $rows = array_values(array_filter($report, fn ($r) => $spot === null || $r['spot'] === $spot));
            if (! $rows) {
                continue;
            }
            $n = count($rows);
            $real = array_fill_keys(self::CLASSES, 0);
            foreach ($rows as $r) {
                $real[$r['real']]++;
            }
            $this->info("== {$label} ({$n} decisiones) ==  reales: ".$this->fmt(array_map(fn ($v) => $v / $n, $real)));
            foreach ($who as $key => $name) {
                $m = ['acc' => 0, 'p' => 0.0, 'll' => 0.0];
                $pred = array_fill_keys(self::CLASSES, 0.0);
                foreach ($rows as $r) {
                    $dist = $key === 'base' || $key === 'table_base' ? $r[$key] : $r['modes'][$key];
                    $p = max(0.01, $dist[$r['real']] ?? 0);
                    $m['p'] += $p;
                    $m['ll'] += -log($p);
                    $m['acc'] += (array_search(max($dist), $dist) === $r['real']) ? 1 : 0;
                    foreach (self::CLASSES as $k) {
                        $pred[$k] += $dist[$k];
                    }
                }
                $this->line(sprintf('  %-17s acierta la más probable %3d%% · prob. media a la real %2d%% · pérdida log %.3f · media: %s',
                    $name, round(100 * $m['acc'] / $n), round(100 * $m['p'] / $n), $m['ll'] / $n, $this->fmt(array_map(fn ($v) => $v / $n, $pred))));
            }
        }
    }
}
