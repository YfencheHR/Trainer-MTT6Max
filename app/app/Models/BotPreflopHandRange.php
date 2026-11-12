<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotPreflopHandRange extends Model
{
    protected $fillable = [
        'bot_id',
        'position',
        'hand',
        'attack_open',
        'attack_limp',
        'open_reraise',
        'open_call',
        'open_fold',
        'limp_raise',
        'limp_push',
        'defense_three_bet',
        'defense_call',
        'defense_fold',
        'is_manual',
    ];

    protected $casts = [
        'attack_open' => 'float',
        'attack_limp' => 'float',
        'open_reraise' => 'float',
        'open_call' => 'float',
        'open_fold' => 'float',
        'limp_raise' => 'float',
        'limp_push' => 'float',
        'defense_three_bet' => 'float',
        'defense_call' => 'float',
        'defense_fold' => 'float',
        'is_manual' => 'boolean',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
