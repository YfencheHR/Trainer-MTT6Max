<?php

namespace App\Services\Ai;

/**
 * Catálogo de modelos gratuitos que pueden decidir por los bots y cuáles de ellos puede ejecutar un equipo.
 *
 * El catálogo (`resources/ai/models.json`) y el equipo medido por el instalador (`storage/app/ai/hardware.json`) son datos;
 * esta clase solo cruza los dos. La misma regla la aplica el instalador (scripts/ai-hardware.ps1) antes de construir nada.
 *
 * Equipo: ['ram_gb' => float, 'disk_free_gb' => float, 'cpu_threads' => int, 'gpus' => [['name' => string, 'vram_gb' => float]]]
 */
class ModelCatalog
{
    private array $catalog;

    public function __construct(?array $catalog = null)
    {
        $this->catalog = $catalog ?? json_decode((string) file_get_contents(resource_path('ai/models.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return list<array> */
    public function models(): array
    {
        return $this->catalog['models'];
    }

    public function minimum(): array
    {
        return collect($this->models())->firstWhere('id', $this->catalog['minimum']);
    }

    /**
     * Dónde puede ejecutarse un modelo: 'gpu' (tarjeta NVIDIA con memoria suficiente), 'cpu' (memoria del equipo suficiente, más lento) o null.
     * Cuando no puede, `reason` dice qué falta: 'disk' o 'memory'.
     *
     * @return array{fit: ?string, reason: ?string}
     */
    public function fit(array $model, array $hardware): array
    {
        if (($hardware['disk_free_gb'] ?? 0) < $model['disk_gb']) {
            return ['fit' => null, 'reason' => 'disk'];
        }
        $bestVram = max(array_merge([0], array_column($hardware['gpus'] ?? [], 'vram_gb')));
        if ($bestVram >= $model['vram_gb']) {
            return ['fit' => 'gpu', 'reason' => null];
        }
        if (($hardware['ram_gb'] ?? 0) >= $model['ram_gb']) {
            return ['fit' => 'cpu', 'reason' => null];
        }

        return ['fit' => null, 'reason' => 'memory'];
    }

    /** Los modelos que el equipo puede ejecutar, con dónde, del más capaz al menos. */
    public function usable(array $hardware): array
    {
        $out = [];
        foreach ($this->models() as $model) {
            $fit = $this->fit($model, $hardware);
            if ($fit['fit'] !== null) {
                $out[] = $model + ['runs_on' => $fit['fit']];
            }
        }
        usort($out, fn ($a, $b) => [$b['params_b'], $a['runs_on'] === 'gpu' ? 0 : 1] <=> [$a['params_b'], $b['runs_on'] === 'gpu' ? 0 : 1]);

        return $out;
    }

    /** ¿Cumple el equipo los requisitos mínimos del software (puede ejecutar el modelo mínimo)? */
    public function meetsMinimum(array $hardware): bool
    {
        return $this->fit($this->minimum(), $hardware)['fit'] !== null;
    }
}
