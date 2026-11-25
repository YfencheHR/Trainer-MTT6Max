<?php

namespace App\Console\Commands;

use App\Services\Ai\AiBrain;
use App\Services\Replay\RealHandReplay;
use Illuminate\Console\Command;

/**
 * Compara motores de decisión sobre las MISMAS decisiones reales (réplica con las cartas conocidas: el héroe y quien llegó al showdown):
 *   rules      el motor de reglas de los bots (el que juega hoy)
 *   ai         el modelo local con una respuesta directa (~2 s por decisión)
 *   ai-brief   el modelo escribe antes un análisis breve y luego da las probabilidades
 *   ai-think   el modo de razonamiento del modelo (~60 s por decisión: solo sobre un subconjunto, --think-limit)
 *
 * Se mide, igual que en la réplica, cuántas veces la acción más probable coincide con la que hizo la persona. Un motor que falla en una
 * decisión la pierde para todos: la comparación se hace sobre las decisiones que contestaron todos. Con cartas conocidas faltan los foldeos
 * preflop (quien llega al showdown nunca foldea): mide qué tal se parece cada motor jugando manos que se juegan, no la calibración global.
 */
class BotsCompareEnginesCommand extends Command
{
    protected $signature = 'bots:compare-engines {file : TSV id_hand<TAB>history (volcado de tourney_hand_histories)}
        {--limit=300 : decisiones por motor}
        {--every=2 : usar 1 de cada N manos (por hash)}
        {--engines=rules,ai,ai-brief : motores a comparar (rules, ai, ai-brief, ai-think)}
        {--think-limit=60 : decisiones de ai-think (lento: ~1 minuto cada una); son las primeras de la misma secuencia}
        {--streets=preflop,flop,turn,river : calles a incluir}
        {--out=ai/compare-engines.json : resultado detallado (dentro de storage/app)}';

    protected $description = 'Compara el motor de reglas y la IA (directa, con análisis breve, con razonamiento) sobre las mismas decisiones reales';

    public function handle(RealHandReplay $replay, AiBrain $ai): int
    {
        $byName = $replay->botsByPlayerName();
        if (! $byName) {
            $this->error('No hay jugadores asignados a perfiles Pool: importa primero un historial de PokerTracker.');

            return self::FAILURE;
        }
        $engines = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('engines')))));
        $results = [];
        $seconds = [];
        $failed = [];
        foreach ($engines as $engine) {
            if (! in_array($engine, ['rules', 'ai', 'ai-brief', 'ai-think'], true)) {
                $this->error("Motor desconocido: {$engine}");

                return self::FAILURE;
            }
            config(['ai.reasoning' => ['ai-brief' => 'brief', 'ai-think' => 'think'][$engine] ?? null]);
            $decider = $engine === 'rules' ? null : fn(array $ctx, $bot, int $seed) => $ai->decide($ctx, $seed);
            $limit = $engine === 'ai-think' ? (int) $this->option('think-limit') : (int) $this->option('limit');
            $this->info("Motor {$engine}: {$limit} decisiones…");
            $started = microtime(true);
            try {
                $result = $replay->run(RealHandReplay::handsFromTsv($this->argument('file')), $byName, [
                    'limit' => $limit, 'every' => (int) $this->option('every'), 'streets' => array_filter(explode(',', (string) $this->option('streets'))),
                    'marginal' => false, 'candidates' => 0, 'decider' => $decider,
                ]);
            } finally {
                config(['ai.reasoning' => null]);   // aunque falle una pasada, el modo de razonamiento no se queda puesto
            }
            $seconds[$engine] = (microtime(true) - $started) / max(1, count($result['rows']));
            $results[$engine] = $result['rows'];
            $failed[$engine] = $result['errors'];
            $this->line('  '.count($result['rows']).' decisiones · '.round($seconds[$engine], 1).' s por decisión'
                .($result['errors'] ? " · {$result['errors']} errores: {$result['first_error']}" : ''));
        }
        config(['ai.reasoning' => null]);

        $main = array_values(array_filter($engines, fn($e) => $e !== 'ai-think'));
        $common = $this->commonIds($results, $main);
        $this->report('Decisiones contestadas por todos los motores', $results, $engines, $common, $seconds, $failed);
        if (isset($results['ai-think'])) {
            $subset = array_values(array_intersect($common, array_column($results['ai-think'], 'id')));
            $this->report('Subconjunto que también contestó ai-think', $results, $engines, $subset, $seconds, $failed);
        }
        $this->agreement($results, $main, $common);
        file_put_contents(storage_path('app/'.$this->option('out')), json_encode($results, JSON_UNESCAPED_UNICODE));
        $this->line('Detalle: '.storage_path('app/'.$this->option('out')));

        return self::SUCCESS;
    }

    /** @return array<int, string> ids de las decisiones que contestaron todos los motores indicados */
    private function commonIds(array $results, array $engines): array
    {
        $common = null;
        foreach ($engines as $engine) {
            $ids = array_column($results[$engine] ?? [], 'id');
            $common = $common === null ? $ids : array_values(array_intersect($common, $ids));
        }

        return $common ?? [];
    }

    private function report(string $title, array $results, array $engines, array $ids, array $seconds, array $failed): void
    {
        $this->newLine();
        $this->info($title.': '.count($ids));
        $keep = array_flip($ids);
        $rows = [];
        foreach ($engines as $engine) {
            $subset = array_values(array_filter($results[$engine] ?? [], fn($r) => isset($keep[$r['id']])));
            if (! $subset) {
                continue;
            }
            $all = RealHandReplay::summarize($subset)[0];
            $rows[] = [$engine, $all['n'], $all['accuracy'].' %', '± '.$all['ci'], $all['probability'].' %', $all['loss'], $all['baseline'].' %', round($seconds[$engine] ?? 0, 1).' s', (string) ($failed[$engine] ?? 0)];
            foreach (RealHandReplay::summarize($subset) as $line) {
                if (str_starts_with($line['name'], 'calle ')) {
                    $rows[] = ['  '.$line['name'], $line['n'], $line['accuracy'].' %', '± '.$line['ci'], $line['probability'].' %', $line['loss'], $line['baseline'].' %', '', ''];
                }
            }
        }
        $this->table(['Motor', 'n', 'Acierta la más probable', '95 %', 'Prob. media de lo real', 'Pérdida log', 'Siempre lo más común', 'Tiempo', 'Sin respuesta'], $rows);
    }

    /** Cuántas veces dos motores eligen la misma acción más probable (qué tan distintos son entre sí). */
    private function agreement(array $results, array $engines, array $ids): void
    {
        if (count($engines) < 2 || ! $ids) {
            return;
        }
        $top = function (array $row): string {
            arsort($row['dist']);

            return (string) array_key_first($row['dist']);
        };
        $byId = [];
        foreach ($engines as $engine) {
            foreach ($results[$engine] as $row) {
                $byId[$engine][$row['id']] = $top($row);
            }
        }
        $this->newLine();
        $this->info('Coincidencia entre motores (misma acción más probable):');
        $lines = [];
        foreach ($engines as $i => $a) {
            foreach (array_slice($engines, $i + 1) as $b) {
                $same = count(array_filter($ids, fn($id) => ($byId[$a][$id] ?? null) === ($byId[$b][$id] ?? 0)));
                $lines[] = ["{$a} ↔ {$b}", round(100 * $same / count($ids)).' %'];
            }
        }
        $this->table(['Pareja', 'Coinciden'], $lines);
    }
}
