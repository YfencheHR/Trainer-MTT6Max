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
        'stack_bucket',
        'defense_fold',
        'cold3_raise',
        'cold3_call',
        'cold4_raise',
        'cold4_call',
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
        'cold3_raise' => 'float',
        'cold3_call' => 'float',
        'cold4_raise' => 'float',
        'cold4_call' => 'float',
        'is_manual' => 'boolean',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
