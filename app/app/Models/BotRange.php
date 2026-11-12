<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotRange extends Model
{
    protected $table = 'bot_ranges';

    protected $fillable = [
        'bot_id',
        'version',
        'posicion',
        'accion',
        'rango_ciegas',
        'rango_raise',
        'rango_call',
        'rango_limp',
        'rango_fold',
        'notas',
        'activo'
    ];

    protected $casts = [
        'rango_raise' => 'json',
        'rango_call' => 'json',
        'rango_limp' => 'json',
        'rango_fold' => 'json',
        'activo' => 'boolean'
    ];

    /**
     * Relación inversa al bot
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Obtener peso de una mano en un rango
     */
    public function getPeso(string $mano, string $tipo = 'raise'): float
    {
        $rango = match($tipo) {
            'call' => $this->rango_call,
            'limp' => $this->rango_limp,
            'fold' => $this->rango_fold,
            default => $this->rango_raise
        };

        return $rango[$mano] ?? 0.0;
    }

    /**
     * Verificar si una mano está en un rango
     */
    public function manoEnRango(string $mano, string $tipo = 'raise'): bool
    {
        return $this->getPeso($mano, $tipo) > 0.0;
    }

    /**
     * Obtener todas las manos para un tipo de acción
     */
    public function getManos(string $tipo = 'raise'): array
    {
        $rango = match($tipo) {
            'call' => $this->rango_call,
            'limp' => $this->rango_limp,
            'fold' => $this->rango_fold,
            default => $this->rango_raise
        };

        return array_keys($rango ?? []);
    }

    /**
     * Ordenar manos por peso
     */
    public function getManosOrdenadas(string $tipo = 'raise'): array
    {
        $rango = match($tipo) {
            'call' => $this->rango_call,
            'limp' => $this->rango_limp,
            'fold' => $this->rango_fold,
            default => $this->rango_raise
        };

        if (!$rango) {
            return [];
        }

        arsort($rango);
        return array_keys($rango);
    }
}
