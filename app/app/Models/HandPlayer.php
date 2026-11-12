<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un jugador sentado en una mano concreta (asiento, stacks, cartas y resultado). */
class HandPlayer extends Model
{
    protected $table = 'hand_players';

    protected $guarded = [];

    protected $casts = [
        'hole_cards' => 'array',
        'stats_before' => 'array',
        'went_to_showdown' => 'boolean',
    ];

    public function hand(): BelongsTo
    {
        return $this->belongsTo(TournamentHand::class, 'tournament_hand_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(TournamentPlayer::class, 'tournament_player_id');
    }
}
