<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotGameState extends Model
{
    protected $table = 'bot_game_states';

    protected $fillable = [
        'bot_id',
        'posicion',
        'stack_bb',
        'blinds_bb',
        'mano',
        'street',
        'estado_mental',
        'ultima_accion',
        'apuesta_actual',
        'total_puesto',
        'board',
        'villanos',
        'ultimo_movimiento'
    ];

    protected $casts = [
        'board' => 'json',
        'villanos' => 'json',
        'stack_bb' => 'integer',
        'blinds_bb' => 'integer',
        'apuesta_actual' => 'integer',
        'total_puesto' => 'integer',
        'ultimo_movimiento' => 'datetime'
    ];

    /**
     * Relación inversa al bot
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Registrar acción
     */
    public function recordAction(string $accion, ?int $apuesta = null): self
    {
        $this->ultima_accion = $accion;
        $this->ultimo_movimiento = now();

        if ($apuesta !== null) {
            $this->apuesta_actual = $apuesta;
            $this->total_puesto = ($this->total_puesto ?? 0) + $apuesta;
        }

        return $this;
    }
}
