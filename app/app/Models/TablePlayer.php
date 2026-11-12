<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TablePlayer extends Pivot
{
    protected $table = 'table_players';

    protected $fillable = [
        'table_id',
        'tournament_player_id',
        'seat_number',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function tournamentPlayer(): BelongsTo
    {
        return $this->belongsTo(TournamentPlayer::class);
    }
}
