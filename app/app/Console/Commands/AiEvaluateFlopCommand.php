<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ScoresAgainstRealActions;
use App\Services\Ai\AiClient;
use App\Services\Ai\DecisionContract;
use App\Services\Ai\HandHistoryParser;
use Illuminate\Console\Command;

/**
 * Compara las decisiones del flop de la IA con las reales de un jugador en manos de PokerTracker (herramienta de desarrollo).
 *
 * El perfil, el rango preflop del jugador y los perfiles de los rivales se calculan con unas manos y la IA se evalúa con OTRAS
 * distintas (sin fugas). Cada decisión se pregunta en varios modos para ver qué aporta cada pieza:
 *   plain → solo el perfil y la mano (sin rango propio, sin adaptación al rival)
 *   range → además el rango con el que llega al flop según su línea preflop (zonas del rango)
 *   full  → además la adaptación al rival (agresión, faroles, folds)
 * Se compara con la línea base «solo el perfil, sin mirar la mano».
 */
class AiEvaluateFlopCommand extends Command
{
    protected $signature = 'ai:evaluate-flop {file : TSV id_hand<TAB>history (volcado de tourney_hand_histories)}
        {--player= : jugador a evaluar (por defecto, el héroe de las manos)}
        {--profile-hands=20000 : manos para calcular el perfil}
        {--limit=120 : decisiones a evaluar}
        {--modes=plain,range,full : modos a comparar}
        {--min-line=40 : manos mínimas de una línea preflop para construir su rango}
        {--dump= : números de decisión (p. ej. 40,115) cuyo estado se guarda en storage/app/ai/eval-states.json; no llama al modelo}
        {--out=ai/eval-flop.json : resultado detallado (dentro de storage/app)}';

    protected $description = 'Evalúa la IA del flop contra manos reales de PokerTracker';

    /** Calle que se evalúa (el comando del turn hereda de este y cambia solo esto). */
    protected string $street = 'flop';

    use ScoresAgainstRealActions;

    public function handle(HandHistoryParser $parser, DecisionContract $contract, AiClient $client): int
    {
        $file = fopen($this->argument('file'), 'r');
        if (! $file) {
            $this->error('No se puede abrir el archivo');

            return 1;
        }
        $profileHands = (int) $this->option('profile-hands');
        $player = $this->option('player');
        $modes = array_values(array_intersect(['plain', 'range', 'full'], explode(',', (string) $this->option('modes'))));

        // Pasada única: las primeras manos del jugador dan el perfil y su rango; las siguientes son de evaluación
        $counts = [];
        $cells = [];
        $everyone = [];
        $deals = [];
        $lines = [];
        $pool = [];
        $seen = 0;
        while (($line = fgets($file)) !== false) {
            [, $history] = array_pad(explode("\t", rtrim($line, "\r\n"), 2), 2, null);
            $hand = $history ? $parser->parse($history) : null;
            // Perfil de cada rival: solo con manos ANTERIORES a las de evaluación (sin fugas)
            if ($hand && $seen < $profileHands) {
                foreach ($hand['players'] as $p) {
                    $everyone[$p['name']] ??= [];
                    $this->tally($hand, $p['name'], $everyone[$p['name']]);
                }
            }
            if (! $hand || ! $hand['hero'] || ($player && $hand['hero'] !== $player)) {
                continue;
            }
            $player ??= $hand['hero'];
            if ($seen < $profileHands) {
                $this->tallyRange($hand, $player, $deals, $lines);
            }
            $mine = array_values(array_filter($hand['actions'], fn ($a) => $a['player'] === $player && $a['street'] === $this->street));
            if (! $mine) {
                continue;
            }
            $seen++;
            if ($seen <= $profileHands) {
                $this->tally($hand, $player, $counts);
                $this->tallyCells($hand, $player, $cells, $contract);
            } else {
                $pool[] = $hand;
                if (count($pool) >= 4000) {
                    break;
                }
            }
        }
        fclose($file);

        $profile = $this->profileFrom($counts);
        // Su cbet por posición y, dentro de ella, heads-up o multiway: lo más concreto que tiene el perfil (ver Initiative::target)
        $profile['postflop'][$this->street] += $this->cellsProfile($cells);
        $this->info("Jugador: {$player} · manos para el perfil: ".min($seen, $profileHands).' · manos de evaluación: '.count($pool).' · modos: '.implode(', ', $modes));
        $this->line("Perfil {$this->street}: ".json_encode($profile['postflop'][$this->street]));
        $this->line('Líneas preflop con rango: '.count(array_filter($lines, fn ($l) => array_sum($l) >= (int) $this->option('min-line'))).' de '.count($lines));

        // Decisiones de evaluación (una sola por mano, para no repetir contexto), al azar pero siempre las mismas
        mt_srand(7);
        shuffle($pool);
        $rows = [];
        foreach ($pool as $hand) {
            if (count($rows) >= (int) $this->option('limit')) {
                break;
            }
            foreach ($hand['actions'] as $i => $a) {
                if ($a['player'] !== $player || $a['street'] !== $this->street) {
                    continue;
                }
                $profiles = [$player => ['preflop_stats' => $profile['preflop'], 'postflop_stats' => $profile['postflop']]];
                foreach ($hand['players'] as $p) {
                    // Rival con pocas manos observadas: sin perfil (la IA lo trata como desconocido)
                    $observed = $this->profileFrom($everyone[$p['name']] ?? [], 30);
                    if ($p['name'] !== $player && array_filter($observed['postflop'])) {
                        $profiles[$p['name']] = ['preflop_stats' => [], 'postflop_stats' => $observed['postflop']];
                    }
                }
                $built = $contract->fromHistory($hand, $i, $profiles);
                if (! $built) {
                    continue 2;
                }
                $preflop = $this->preflopLine($hand, $player);
                $range = $preflop ? $this->heroRange($preflop['position'], $preflop['key'], $deals, $lines) : null;
                $rows[] = ['state' => $built['state'], 'real' => $this->coarse($built['action']), 'real_exact' => $built['action'], 'a' => $a,
                    'range' => $range, 'line' => $preflop['key'] ?? null];
                break;
            }
        }

        if ($this->option('dump')) {
            // Solo guarda los estados que se piden (con el rango propio y la adaptación del modo «full») para poder ver el cuestionario exacto
            $states = [];
            foreach (array_filter(explode(',', (string) $this->option('dump'))) as $number) {
                $row = $rows[(int) $number - 1] ?? null;
                if ($row) {
                    $states[$number] = ['state' => $row['state'] + ['adaptation' => 1.0] + ($row['range'] ? ['range' => $row['range']] : []),
                        'real' => $row['real_exact'], 'line' => $row['line']];
                }
            }
            file_put_contents(storage_path('app/ai/eval-states.json'), json_encode($states, JSON_UNESCAPED_UNICODE));
            $this->line('Estados guardados: '.implode(', ', array_keys($states)));

            return 0;
        }

        $report = [];
        foreach ($rows as $n => $row) {
            $facing = $row['a']['to_call'] > 0;
            $legal = ['fold' => $facing, 'check' => ! $facing, 'call' => $facing ? $row['a']['to_call'] : null, 'raise' => ['min' => 1, 'max' => 1], 'allin' => 1];
            $allowed = $contract->allowedActions($legal, $this->street);
            $answers = [];
            foreach ($modes as $mode) {
                $state = $row['state'] + ['adaptation' => $mode === 'full' ? 1.0 : 0.0];
                if ($mode !== 'plain' && $row['range']) {
                    $state['range'] = $row['range'];
                }
                try {
                    $dist = $contract->restrictToLegal($client->decide($state, $allowed, 1), $legal);
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
                'spot' => $facing ? 'ante apuesta' : 'primero en hablar', 'real' => $row['real'], 'modes' => $answers,
                'base' => $this->baseline($profile['postflop'][$this->street], $facing, $row['state']),
                'hand' => implode(' ', $row['state']['hole_cards']).' | '.implode(' ', $row['state']['board']),
                'real_exact' => $row['real_exact'], 'line' => $row['line'], 'has_range' => (bool) $row['range'],
            ];
            $this->line(sprintf('%3d/%d  %-17s real=%-5s %s', $n + 1, count($rows), end($report)['spot'], $row['real'],
                implode(' | ', array_map(fn ($m) => $m.': '.$this->fmt($answers[$m]), array_keys($answers)))));
        }

        $this->summary($report, $modes);
        $out = storage_path('app/'.$this->option('out'));
        @mkdir(dirname($out), 0777, true);
        file_put_contents($out, json_encode(['player' => $player, 'profile' => $profile, 'rows' => $report], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->line("Detalle: {$out}");

        return 0;
    }

    /**
     * Lo que hizo el jugador preflop: su posición y su línea (cada acción con las subidas que había antes), como clave de texto.
     *
     * @return array{position: string, key: string}|null  null si foldeó preflop o no tiene cartas
     */
    private function preflopLine(array $hand, string $player): ?array
    {
        $me = collect($hand['players'])->firstWhere('name', $player);
        if (! $me || ! $me['hole_cards']) {
            return null;
        }
        $raises = 0;
        $steps = [];
        foreach ($hand['actions'] as $a) {
            if ($a['street'] !== 'preflop') {
                continue;
            }
            if ($a['player'] === $player) {
                if ($a['action'] === 'fold') {
                    return null;
                }
                $steps[] = $a['action'].'@'.$raises;
            }
            if ($a['action'] === 'raise') {
                $raises++;
            }
        }

        return $steps ? ['position' => $me['position'], 'key' => $me['position'].':'.implode(',', $steps)] : null;
    }

    /** Cuenta cuántas veces recibió cada mano en cada posición y cuántas la jugó con cada línea preflop. */
    private function tallyRange(array $hand, string $player, array &$deals, array &$lines): void
    {
        $me = collect($hand['players'])->firstWhere('name', $player);
        if (! $me || ! $me['hole_cards']) {
            return;
        }
        $class = $this->handClass($me['hole_cards']);
        $deals[$me['position']][$class] = ($deals[$me['position']][$class] ?? 0) + 1;
        $line = $this->preflopLine($hand, $player);
        if ($line) {
            $lines[$line['key']][$class] = ($lines[$line['key']][$class] ?? 0) + 1;
        }
    }

    /**
     * Su rango con esa línea: de cada mano, qué fracción de las veces que la recibió en esa posición la jugó así (0 a 1).
     * null si hay pocas manos con esa línea.
     *
     * @return array<string, float>|null
     */
    private function heroRange(string $position, string $key, array $deals, array $lines): ?array
    {
        $line = $lines[$key] ?? [];
        if (array_sum($line) < (int) $this->option('min-line')) {
            return null;
        }
        $range = [];
        foreach ($line as $class => $n) {
            $weight = min(1.0, $n / max($n, $deals[$position][$class] ?? 0));
            if ($weight >= 0.005) {
                $range[$class] = round($weight, 3);
            }
        }

        return $range;
    }

    /** Quién traía la iniciativa a una calle: el último que subió o apostó en las anteriores. */
    private function holderBefore(array $hand, string $street): ?string
    {
        $order = ['preflop' => 0, 'flop' => 1, 'turn' => 2, 'river' => 3];
        $last = null;
        foreach ($hand['actions'] as $a) {
            if (($order[$a['street']] ?? 9) < $order[$street] && in_array($a['action'], ['bet', 'raise'], true)) {
                $last = $a['player'];
            }
        }

        return $last;
    }

    /** Cuenta las oportunidades y acciones de cada estadística del flop, el turn y el river en una mano del jugador. */
    private function tally(array $hand, string $player, array &$c): void
    {
        foreach (['flop', 'turn', 'river'] as $street) {
            $c[$street] ??= [];
            $this->tallyStreet($hand, $player, $street, $c[$street]);
        }
    }

    private function tallyStreet(array $hand, string $player, string $street, array &$c): void
    {
        $initiative = $this->holderBefore($hand, $street) === $player;
        $flop = array_values(array_filter($hand['actions'], fn ($a) => $a['street'] === $street));
        $iBet = false;
        foreach ($flop as $k => $a) {
            if ($a['player'] !== $player) {
                continue;
            }
            if ($a['to_call'] > 0) {
                $key = $iBet ? 'f2raise' : 'f2bet';
                $c[$key]['n'] = ($c[$key]['n'] ?? 0) + 1;
                $c[$key]['hit'] = ($c[$key]['hit'] ?? 0) + ($a['action'] === 'fold' ? 1 : 0);
                if (! $iBet && $a['action'] !== 'fold') {
                    $c['raise_vs_bet']['n'] = ($c['raise_vs_bet']['n'] ?? 0) + 1;
                    $c['raise_vs_bet']['hit'] = ($c['raise_vs_bet']['hit'] ?? 0) + ($a['action'] === 'raise' ? 1 : 0);
                }
            } elseif ($k === 0 || ! array_filter(array_slice($flop, 0, $k), fn ($x) => in_array($x['action'], ['bet', 'raise'], true))) {
                // Flop: su cbet con la iniciativa y su donk sin ella. Turn y river: «apuesta cuando le pasan», tenga o no la iniciativa
                $key = $street !== 'flop' ? 'bet' : ($initiative ? 'cbet' : ($k === 0 ? 'donk' : null));
                if ($key) {
                    $c[$key]['n'] = ($c[$key]['n'] ?? 0) + 1;
                    $c[$key]['hit'] = ($c[$key]['hit'] ?? 0) + (in_array($a['action'], ['bet', 'raise'], true) ? 1 : 0);
                }
            }
            if (in_array($a['action'], ['bet', 'raise'], true)) {
                $iBet = true;
            }
        }
    }

    /** Perfil del flop, el turn y el river con las estadísticas que tengan al menos $minSample oportunidades. */
    private function profileFrom(array $c, int $minSample = 1): array
    {
        $postflop = [];
        foreach (['flop', 'turn', 'river'] as $street) {
            $stats = [];
            foreach ($c[$street] ?? [] as $key => $v) {
                if (($v['n'] ?? 0) >= $minSample) {
                    $stats[$key] = round(100 * $v['hit'] / max(1, $v['n']), 1);
                }
            }
            if ($stats) {
                $stats['sample_size'] = max(array_map(fn ($v) => $v['n'], $c[$street]));
            }
            $postflop[$street] = $stats;
        }

        return ['preflop' => [], 'postflop' => $postflop];
    }

    /** Cuenta su apuesta con la iniciativa (primera acción de la calle sin apuesta que igualar) por posición y heads-up / multiway. */
    private function tallyCells(array $hand, string $player, array &$cells, DecisionContract $contract): void
    {
        if ($this->holderBefore($hand, $this->street) !== $player) {
            return;
        }
        foreach ($hand['actions'] as $i => $a) {
            if ($a['street'] !== $this->street || $a['player'] !== $player) {
                continue;
            }
            $built = $a['to_call'] > 0 ? null : $contract->fromHistory($hand, $i);
            if ($built) {
                $position = $built['state']['in_position'] ? 'ip' : 'oop';
                $kind = $built['state']['players_left'] > 2 ? 'agg_multi' : 'agg_hu';
                $hit = in_array($a['action'], ['bet', 'raise'], true) ? 1 : 0;
                foreach ([$kind, 'agg'] as $key) {
                    $cells[$position][$key]['n'] = ($cells[$position][$key]['n'] ?? 0) + 1;
                    $cells[$position][$key]['hit'] = ($cells[$position][$key]['hit'] ?? 0) + $hit;
                }
            }

            return;
        }
    }

    /** @return array<string, array<string, array{bet: float, n: int}>> */
    private function cellsProfile(array $cells): array
    {
        $out = [];
        foreach ($cells as $position => $kinds) {
            foreach ($kinds as $kind => $v) {
                $out[$position][$kind] = ['bet' => round(100 * $v['hit'] / max(1, $v['n']), 1), 'n' => $v['n']];
            }
        }

        return $out;
    }

    /** Su cbet para esta situación: la celda posición × heads-up/multiway si tiene manos de sobra; si no, la de la posición; si no, la general. */
    private function cbetFor(array $flop, array $state): float
    {
        $position = $state['in_position'] ? 'ip' : 'oop';
        $cell = $flop[$position][$state['players_left'] > 2 ? 'agg_multi' : 'agg_hu'] ?? null;
        if ($cell && $cell['n'] >= 30) {
            return $cell['bet'];
        }

        return $flop[$position]['agg']['bet'] ?? ($flop['cbet'] ?? $flop['bet'] ?? 50);
    }

    /** Lo que haría un jugador que solo conoce su perfil (misma frecuencia para cualquier mano). */
    private function baseline(array $flop, bool $facing, array $state): array
    {
        if ($facing) {
            $fold = ($flop['f2bet'] ?? 40) / 100;
            $raise = (1 - $fold) * ($flop['raise_vs_bet'] ?? 10) / 100;

            return ['fold' => $fold, 'check' => 0.0, 'call' => 1 - $fold - $raise, 'aggr' => $raise];
        }
        $initiative = collect($state['history'])->filter(fn ($h) => $h[0] !== $this->street && in_array($h[2], ['bet', 'raise'], true))->last()[1] ?? null;
        $bet = (($initiative === $state['position'] ? $this->cbetFor($flop, $state) : ($flop['donk'] ?? $flop['bet'] ?? 10))) / 100;

        return ['fold' => 0.0, 'check' => 1 - $bet, 'call' => 0.0, 'aggr' => $bet];
    }

    private function summary(array $report, array $modes): void
    {
        $this->newLine();
        $with = count(array_filter($report, fn ($r) => $r['has_range']));
        $this->line("Decisiones con rango propio: {$with} de ".count($report));
        $who = ['base' => 'Solo el perfil'] + array_combine($modes, array_map(fn ($m) => "IA {$m}", $modes));
        foreach (['todas' => null, 'ante apuesta' => 'ante apuesta', 'primero en hablar' => 'primero en hablar'] as $label => $spot) {
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
                    $dist = $key === 'base' ? $r['base'] : $r['modes'][$key];
                    $p = max(0.01, $dist[$r['real']] ?? 0);
                    $m['p'] += $p;
                    $m['ll'] += -log($p);
                    $m['acc'] += (array_search(max($dist), $dist) === $r['real']) ? 1 : 0;
                    foreach (self::CLASSES as $k) {
                        $pred[$k] += $dist[$k];
                    }
                }
                $this->line(sprintf('  %-15s acierta la más probable %3d%% · prob. media a la real %2d%% · pérdida log %.3f · media: %s',
                    $name, round(100 * $m['acc'] / $n), round(100 * $m['p'] / $n), $m['ll'] / $n, $this->fmt(array_map(fn ($v) => $v / $n, $pred))));
            }
        }
    }
}
