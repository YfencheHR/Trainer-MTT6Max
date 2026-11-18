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
        return $this->dir('runs') . "/{$id}.log";
    }

    /** ¿Hay una simulación en marcha (sin marcador de fin y con registro reciente)? */
    public function running(): ?string
    {
        $state = $this->dir() . '/simulation.json';
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
        file_put_contents($this->dir() . '/simulation.json', json_encode(['id' => $id, 'players' => $players, 'speed' => $speed]));
        $log = $this->logPath($id);
        file_put_contents($log, "Simulación de {$players} jugadores en marcha…\n");

        $php = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('artisan'));
        $script = sprintf(
            '%1$s tournament:simulate --purge && %1$s tournament:simulate --players=%2$d --speed=%3$s && %1$s bots:audit-postflop --only-simulated',
            $php, $players, escapeshellarg($speed)
        );
        $cmd = sprintf(
            '(nohup sh -c %s >/dev/null 2>&1 &)',
            escapeshellarg(sprintf('(%s) >> %s 2>&1 && echo __DONE__ >> %s || echo __FAIL__ >> %s', $script, escapeshellarg($log), escapeshellarg($log), escapeshellarg($log)))
        );
        Process::fromShellCommandline($cmd)->run();
    }

    private function dir(string $sub = ''): string
    {
        $d = storage_path('app/import' . ($sub ? "/$sub" : ''));
        if (! is_dir($d)) {
            mkdir($d, 0775, true);
        }

        return $d;
    }
}
