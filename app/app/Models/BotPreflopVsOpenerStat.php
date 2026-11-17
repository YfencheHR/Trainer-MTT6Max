<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respuesta preflop de un bot ante una apertura, según la posición de quien abre.
 * Es agregada (sin mano inicial): modula la frecuencia, la forma la da bot_preflop_hand_stats.
 */
class BotPreflopVsOpenerStat extends Model
{
    protected $fillable = [
        'bot_id',
        'posicion',
        'vs_posicion',
        'opportunities',
        'three_bets',
        'calls',
        'folds',
    ];

    protected $casts = [
        'opportunities' => 'integer',
        'three_bets' => 'integer',
        'calls' => 'integer',
        'folds' => 'integer',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
