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
     * Obtener el board como string
     */
    public function getBoardString(): string
    {
        if (!$this->board) {
            return 'No board yet';
        }

        $flop = $this->board[0] ?? '';
        $turn = $this->board[1] ?? '';
        $river = $this->board[2] ?? '';

        return collect([$flop, $turn, $river])
            ->filter()
            ->join(' ');
    }

    /**
     * Obtener odds de stack
     */
    public function getOdds(): float
    {
        if ($this->blinds_bb == 0) {
            return 0;
        }

        return round($this->stack_bb / $this->blinds_bb, 2);
    }

    /**
     * Cambiar estado mental del bot
     */
    public function setMentalState(string $estado): self
    {
        $this->estado_mental = $estado;
        return $this;
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
