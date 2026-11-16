<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HandActionRange extends Model
{
    protected $fillable = [
        'hand_id',
        'player_id',
        'street',
        'sequence',
        'ranges',
        'metadata',
    ];

    protected $casts = [
        'ranges' => 'array',
        'metadata' => 'array',
    ];

    public function hand()
    {
        return $this->belongsTo(TournamentHand::class, 'hand_id');
    }

    public function player()
    {
        return $this->belongsTo(TournamentPlayer::class, 'player_id');
    }
}
