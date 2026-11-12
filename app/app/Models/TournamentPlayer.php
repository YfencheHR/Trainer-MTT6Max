<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TournamentPlayer extends Model
{
    protected $table = 'tournament_players';

    protected $fillable = [
        'tournament_id',
        'bot_id',
        'user_id',
        'name',
        'starting_stack',
        'current_stack',
        'is_eliminated',
        'placement',
        'is_bot',
        'brain_snapshot_id',
        'brain_overrides',
    ];

    protected $casts = [
        'is_eliminated' => 'boolean',
        'is_bot' => 'boolean',
        'brain_overrides' => 'array',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(TournamentPlayerStat::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
