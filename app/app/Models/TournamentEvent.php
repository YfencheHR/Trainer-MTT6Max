<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hecho relevante del torneo (eliminación, mesa rota, cambio de asiento, nivel…). */
class TournamentEvent extends Model
{
    protected $table = 'tournament_events';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(TournamentPlayer::class, 'tournament_player_id');
    }

    /** Registra un evento del torneo. */
    public static function record(
        Tournament|int $tournament,
        string $type,
        string $message,
        ?int $round = null,
        array $payload = [],
        ?int $tableId = null,
        ?int $playerId = null,
    ): self {
        return self::create([
            'tournament_id' => $tournament instanceof Tournament ? $tournament->id : $tournament,
            'round' => $round,
            'table_id' => $tableId,
            'tournament_player_id' => $playerId,
            'type' => $type,
            'message' => $message,
            'payload' => $payload ?: null,
            'created_at' => now(),
        ]);
    }
}
