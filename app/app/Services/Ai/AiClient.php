<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

/** Cliente del servicio de IA (Python → Ollama). Cualquier fallo es una excepción: quien llama decide qué hacer. */
class AiClient
{
    /** Modelo elegido en la instalación. */
    public function model(): string
    {
        $file = config('ai.model_file');
        // PowerShell 5.1 escribe los JSON con marca BOM: se quita antes de leer
        $data = is_file($file) ? json_decode(ltrim((string) file_get_contents($file), "\xEF\xBB\xBF"), true) : null;
        if (! is_array($data) || empty($data['model'])) {
            throw new \RuntimeException('No hay modelo de IA instalado ('.$file.')');
        }

        return $data['model'];
    }

    /**
     * @param  list<string>  $legalActions  acciones permitidas ahora
     * @return array<string,float> probabilidad de cada acción (suma 1)
     */
    public function decide(array $state, array $legalActions, int $seed): array
    {
        $response = Http::timeout(config('ai.timeout'))->post(rtrim(config('ai.url'), '/').'/ai/decide', [
            'state' => $state, 'model' => $this->model(), 'legal_actions' => $legalActions, 'seed' => $seed,
        ]);
        if (! $response->successful()) {
            throw new \RuntimeException('El servicio de IA respondió '.$response->status().': '.($response->json('message') ?? $response->body()));
        }

        return $response->json('distribution');
    }
}
