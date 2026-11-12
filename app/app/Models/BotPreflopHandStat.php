<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotPreflopHandStat extends Model
{
    protected $fillable = [
        'bot_id',
        'posicion',
        'mano',
        'open_opportunities',
        'open_raises',
        'limps',
        'vs_open_opportunities',
        'three_bets',
        'vs_3bet_opportunities',
        'open_reraises',
        'open_calls',
        'open_folds',
        'limp_raise_opportunities',
        'limp_raises',
        'limp_pushes',
    ];

    protected $casts = [
        'open_opportunities' => 'integer',
        'open_raises' => 'integer',
        'limps' => 'integer',
        'vs_open_opportunities' => 'integer',
        'three_bets' => 'integer',
        'vs_3bet_opportunities' => 'integer',
        'open_reraises' => 'integer',
        'open_calls' => 'integer',
        'open_folds' => 'integer',
        'limp_raise_opportunities' => 'integer',
        'limp_raises' => 'integer',
        'limp_pushes' => 'integer',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
