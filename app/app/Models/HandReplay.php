<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Una repetición de una mano jugada con las mismas cartas y decisiones nuevas de los bots (ver HandReplayService). */
class HandReplay extends Model
{
    protected $table = 'hand_replays';

    protected $guarded = [];

    protected $casts = [
        'diverged' => 'boolean',
        'same_winner' => 'boolean',
        'first_divergence' => 'array',
        'original_result' => 'array',
        'replay_result' => 'array',
    ];

    public function hand(): BelongsTo
    {
        return $this->belongsTo(TournamentHand::class, 'tournament_hand_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(HandReplayAction::class)->orderBy('seq');
    }
}
