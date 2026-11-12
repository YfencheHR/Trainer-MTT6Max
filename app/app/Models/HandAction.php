<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una acción de la mano, en orden (ciegas, fold/check/call/raise/allin y reparto de calles). */
class HandAction extends Model
{
    protected $table = 'hand_actions';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'cards' => 'array',
        'decision' => 'array',
        'created_at' => 'datetime',
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
