<?php

namespace App\Services\Tournament;

use Symfony\Component\Process\Process;

/**
 * Lanza en segundo plano la simulación de un torneo solo de bots y su análisis (lo que hace la página de importación):
 *   1. borra los torneos simulados anteriores (para que el análisis solo vea este),
 *   2. tournament:simulate  → juega el torneo completo,
 *   3. bots:audit-postflop --only-simulated → analiza cómo jugaron los bots frente a sus estadísticas.
 * Todo escribe en el mismo registro, que termina en __DONE__ o __FAIL__ (el mismo formato que la importación, así se consulta igual).
 */
class SimulationLauncher
{
    public const SPEEDS = ['tipica', 'rapida', 'muy_rapida'];

    public function logPath(string $id): string
    {
        return $this->dir('runs')."/{$id}.log";
    }

    /** ¿Hay una simulación en marcha (sin marcador de fin y con registro reciente)? */
    public function running(): ?string
    {
        $state = $this->dir().'/simulation.json';
        $id = is_file($state) ? (json_decode((string) file_get_contents($state), true)['id'] ?? null) : null;
        if (! $id || ! preg_match('/^[a-f0-9-]{36}$/', $id) || ! is_file($log = $this->logPath($id))) {
            return null;
        }
        $text = (string) file_get_contents($log);
        $finished = str_contains($text, '__DONE__') || str_contains($text, '__FAIL__');

        return ! $finished && filemtime($log) > time() - 6 * 3600 ? $id : null;
    }

    public function launch(string $id, int $players, string $speed): void
    {
        file_put_contents($this->dir().'/simulation.json', json_encode(['id' => $id, 'players' => $players, 'speed' => $speed]));
        $log = $this->logPath($id);
        file_put_contents($log, "Simulación de {$players} jugadores en marcha…\n");

        $php = escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path('artisan'));
        // Las líneas «FASE n/3» las lee progress() para decir en qué paso está
        $script = sprintf(
            'echo "FASE 1/3 preparando" && %1$s tournament:simulate --purge && echo "FASE 2/3 simulando" && %1$s tournament:simulate --players=%2$d --speed=%3$s && echo "FASE 3/3 analizando" && %1$s bots:audit-postflop --only-simulated',
            $php, $players, escapeshellarg($speed)
        );
        $cmd = sprintf(
            '(nohup sh -c %s >/dev/null 2>&1 &)',
            escapeshellarg(sprintf('(%s) >> %s 2>&1 && echo __DONE__ >> %s || echo __FAIL__ >> %s', $script, escapeshellarg($log), escapeshellarg($log), escapeshellarg($log)))
        );
        Process::fromShellCommandline($cmd)->run();
    }

    /**
     * Avance de una simulación a partir de su registro, para que la página muestre que el proceso sigue vivo:
     * fase (1 a 3), % de jugadores eliminados, ronda, jugadores que quedan, mesas y segundos. null si el registro no es de una simulación.
     *
     * @return array{phase: int, phase_name: string, percent: int, round: ?int, alive: ?int, total: ?int, tables: ?int, seconds: ?int}|null
     */
    public static function progress(string $log): ?array
    {
        if (! preg_match_all('/^FASE (\d)\/3 (\w+)/m', $log, $phases, PREG_SET_ORDER) && ! str_starts_with($log, 'Simulación de')) {
            return null;
        }
        $phase = $phases ? (int) end($phases)[1] : 1;
        $total = preg_match('/^\[\d+\/\d+\] .*: (\d+) jugadores$/m', $log, $m) ? (int) $m[1] : null;

        $round = $alive = $tables = $seconds = null;
        if (preg_match_all('/^   ronda (\d+): (\d+) jugadores, (\d+) mesas · (\d+) s/m', $log, $rows, PREG_SET_ORDER)) {
            [, $round, $alive, $tables, $seconds] = array_map('intval', end($rows));
        }

        $percent = match (true) {
            $phase >= 3 => 100,
            $phase === 2 && $total && $alive !== null => (int) min(99, max(1, round(100 * ($total - $alive) / max(1, $total - 1)))),
            default => $phase === 2 ? 1 : 0,
        };

        return [
            'phase' => $phase,
            'phase_name' => $phases ? end($phases)[2] : 'preparando',
            'percent' => $percent,
            'round' => $round, 'alive' => $alive, 'total' => $total, 'tables' => $tables, 'seconds' => $seconds,
        ];
    }

    private function dir(string $sub = ''): string
    {
        $d = storage_path('app/import'.($sub ? "/$sub" : ''));
        if (! is_dir($d)) {
            mkdir($d, 0775, true);
        }

        return $d;
    }
}
