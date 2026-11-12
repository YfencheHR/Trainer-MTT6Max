<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\BotHudStat;
use App\Models\BotRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

/**
 * Importación de datos reales para los bots desde una COPIA de PokerTracker 4 (zip o volcado SQL).
 * El archivo se sube troceado (PHP limita las subidas) y el comando pt4:import lo procesa en segundo plano.
 */
class ImportController extends Controller
{
    private const CHUNK_SIZE_KB = 1536;
    private const MAX_CHUNKS = 4096;

    private function dir(string $sub = ''): string
    {
        $d = storage_path('app/import' . ($sub ? "/$sub" : ''));
        if (!is_dir($d)) {
            mkdir($d, 0775, true);
        }

        return $d;
    }

    public function index()
    {
        return view('import.index', [
            'ours' => ['bots' => Bot::count(), 'hud_stats' => BotHudStat::count(), 'ranges' => BotRange::count()],
            'pool' => Bot::where('tipo', 'pool')->count(),
        ]);
    }

    /** POST /import/upload — recibe un trozo; con el último lanza la importación. */
    public function chunk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'regex:/^[a-f0-9-]{36}$/'],
            'idx' => ['required', 'integer', 'min:0', 'max:' . (self::MAX_CHUNKS - 1)],
            'total' => ['required', 'integer', 'min:1', 'max:' . self::MAX_CHUNKS],
            'name' => 'required|string|max:200',
            'min_hands' => 'nullable|integer|min:10|max:5000',
            'reset' => 'nullable|boolean',
            'chunk' => ['required', 'file', 'max:' . self::CHUNK_SIZE_KB],
        ]);

        $idx = (int) $data['idx'];
        $total = (int) $data['total'];
        if ($idx >= $total) {
            return response()->json(['success' => false, 'message' => 'El índice del trozo no coincide con el total indicado.'], 422);
        }

        $ext = strtolower(pathinfo($data['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['zip', 'backup', 'sql'], true)) {
            return response()->json(['success' => false, 'message' => 'Solo se admite .zip, .backup o .sql'], 422);
        }

        $uploads = $this->dir('uploads');
        $part = $uploads . "/{$data['id']}.part";
        $meta = $uploads . "/{$data['id']}.json";
        $lockHandle = fopen($uploads . "/{$data['id']}.lock", 'c');
        if ($lockHandle === false) {
            return response()->json(['success' => false, 'message' => 'No se pudo bloquear el archivo de importación.'], 503);
        }
        if (!flock($lockHandle, LOCK_EX)) {
            fclose($lockHandle);
            return response()->json(['success' => false, 'message' => 'No se pudo bloquear el archivo de importación.'], 503);
        }

        try {
            $reset = $request->boolean('reset');
            $minHands = (int) ($data['min_hands'] ?? 20);

            if ($idx === 0) {
                if (file_put_contents($part, '') === false) {
                    throw new \RuntimeException('No se pudo iniciar la recepción del archivo.');
                }
                $state = [
                    'name' => $data['name'],
                    'total' => $total,
                    'min_hands' => $minHands,
                    'reset' => $reset,
                    'next_idx' => 0,
                ];
            } else {
                $state = is_file($meta) ? json_decode((string) file_get_contents($meta), true) : null;
                if (!is_array($state)
                    || ($state['name'] ?? null) !== $data['name']
                    || (int) ($state['total'] ?? 0) !== $total
                    || (int) ($state['min_hands'] ?? 20) !== $minHands
                    || (bool) ($state['reset'] ?? false) !== $reset
                    || (int) ($state['next_idx'] ?? -1) !== $idx
                    || !is_file($part)) {
                    return response()->json(['success' => false, 'message' => 'Trozo inesperado o fuera de orden. Reinicia la importación.'], 409);
                }
            }

            $source = fopen($request->file('chunk')->getRealPath(), 'rb');
            $destination = fopen($part, 'ab');
            if ($source === false || $destination === false) {
                if (is_resource($source)) fclose($source);
                if (is_resource($destination)) fclose($destination);
                throw new \RuntimeException('No se pudo guardar el trozo recibido.');
            }

            $copied = stream_copy_to_stream($source, $destination);
            fclose($source);
            fclose($destination);
            if ($copied === false || $copied !== $request->file('chunk')->getSize()) {
                @unlink($part);
                @unlink($meta);
                throw new \RuntimeException('El trozo no se guardó completo. Reinicia la importación.');
            }

            $state['next_idx'] = $idx + 1;
            if (file_put_contents($meta, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                @unlink($part);
                @unlink($meta);
                throw new \RuntimeException('No se pudo guardar el estado de la importación. Reinicia la importación.');
            }

            if ($idx + 1 < $total) {
                return response()->json(['success' => true, 'done' => false]);
            }

            $file = $uploads . "/{$data['id']}.$ext";
            if (is_file($file)) unlink($file);
            if (!rename($part, $file)) {
                throw new \RuntimeException('No se pudo preparar el archivo para importarlo.');
            }
            @unlink($meta);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }

        $log = $this->dir('runs') . "/{$data['id']}.log";
        file_put_contents($log, "Archivo recibido (" . round(filesize($file) / 1048576) . " MB)\n");

        $cmd = sprintf(
            '(nohup sh -c %s >/dev/null 2>&1 &)',
            escapeshellarg(sprintf(
                '%s %s pt4:import %s --min-hands=%d%s >> %s 2>&1 && echo __DONE__ >> %s || echo __FAIL__ >> %s; rm -f %s',
                escapeshellarg(PHP_BINARY), escapeshellarg(base_path('artisan')), escapeshellarg($file), $minHands, $reset ? ' --reset' : '',
                escapeshellarg($log), escapeshellarg($log), escapeshellarg($log), escapeshellarg($file)
            ))
        );
        Process::fromShellCommandline($cmd)->run();

        return response()->json(['success' => true, 'done' => true, 'run' => $data['id']]);
    }

    /** GET /import/status/{run} — progreso (últimas líneas del registro). */
    public function status(string $run): JsonResponse
    {
        abort_unless(preg_match('/^[a-f0-9-]{36}$/', $run), 404);
        $log = $this->dir('runs') . "/$run.log";
        $text = is_file($log) ? (string) file_get_contents($log) : '';
        $state = str_contains($text, '__DONE__') ? 'done' : (str_contains($text, '__FAIL__') ? 'failed' : 'running');

        return response()->json([
            'state' => $state,
            'log' => trim(str_replace(['__DONE__', '__FAIL__'], '', preg_replace('/\e\[[\d;]*m/', '', $text))),
            'ours' => ['bots' => Bot::count(), 'hud_stats' => BotHudStat::count(), 'pool' => Bot::where('tipo', 'pool')->count()],
        ]);
    }
}
