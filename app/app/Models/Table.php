<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Table extends Model
{
    protected $fillable = [
        'tournament_id',
        'table_number',
        'max_players',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function hands(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TournamentHand::class, 'table_id');
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(
            TournamentPlayer::class,
            'table_players',
            'table_id',
            'tournament_player_id'
        )->withPivot('seat_number', 'is_active')
        ->withTimestamps();
    }

    /**
     * Obtener cantidad de jugadores activos en la mesa
     */
    public function getActivePlayerCount(): int
    {
        return $this->players()->where('table_players.is_active', true)->count();
    }

    /**
     * Obtener asientos disponibles
     */
    public function getAvailableSeats(): array
    {
        $occupied = $this->players()->pluck('table_players.seat_number')->toArray();
        $available = [];

        for ($i = 1; $i <= $this->max_players; $i++) {
            if (!in_array($i, $occupied)) {
                $available[] = $i;
            }
        }

        return $available;
    }

    /**
     * Verificar si la mesa está llena
     */
    public function isFull(): bool
    {
        return $this->getActivePlayerCount() >= $this->max_players;
    }

    /**
     * Verificar si la mesa está vacía
     */
    public function isEmpty(): bool
    {
        return $this->getActivePlayerCount() === 0;
    }
}
