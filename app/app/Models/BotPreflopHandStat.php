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
        'vs_open_calls',
        'squeeze_opportunities',
        'squeezes',
        'vs_4bet_opportunities',
        'vs_4bet_calls',
        'vs_4bet_raises',
        'vs_4bet_folds',
        'vs_limp_opportunities',
        'vs_limp_raises',
        'vs_limp_calls',
        'cold3_opportunities',
        'cold3_calls',
        'cold3_raises',
    ];

    protected $casts = [
        'vs_open_calls' => 'integer',
        'squeeze_opportunities' => 'integer',
        'squeezes' => 'integer',
        'vs_4bet_opportunities' => 'integer',
        'vs_4bet_calls' => 'integer',
        'vs_4bet_raises' => 'integer',
        'vs_4bet_folds' => 'integer',
        'vs_limp_opportunities' => 'integer',
        'vs_limp_raises' => 'integer',
        'vs_limp_calls' => 'integer',
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
