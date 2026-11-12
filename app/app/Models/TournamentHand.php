<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentHand extends Model
{
    protected $table = 'tournament_hands';

    protected $fillable = [
        'tournament_id',
        'table_id',
        'hand_number',
        'level',
        'blind_level_config',
        'button_seat',
        'small_blind_seat',
        'big_blind_seat',
        'hero_seat',
        'hero_cards',
        'hero_hand_type_id',
        'community_cards',
        'board_full',
        'pots',
        'ev_analyzed_at',
        'stacks_before',
        'stacks_after',
        'pot_preflop',
        'pot_flop',
        'pot_turn',
        'pot_river',
        'result',
        'winning_seat',
        'players_in_hand',
        'actions',
        'status',
    ];

    protected $casts = [
        'blind_level_config' => 'json',
        'hero_cards' => 'json',
        'community_cards' => 'json',
        'board_full' => 'json',
        'pots' => 'json',
        'ev_analyzed_at' => 'datetime',
        'stacks_before' => 'json',
        'stacks_after' => 'json',
        'players_in_hand' => 'json',
        'actions' => 'json',
        'pot_preflop' => 'float',
        'pot_flop' => 'float',
        'pot_turn' => 'float',
        'pot_river' => 'float',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function handPlayers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HandPlayer::class)->orderBy('seat');
    }

    public function handActions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HandAction::class)->orderBy('sequence');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function getStreetPot(string $street): ?float
    {
        $pots = [
            'preflop' => 'pot_preflop',
            'flop' => 'pot_flop',
            'turn' => 'pot_turn',
            'river' => 'pot_river',
        ];

        $column = $pots[$street] ?? 'pot_river';

        return (float) ($this->$column ?? 0);
    }

    public function isHeroWinner(): bool
    {
        return $this->result === 'win' || ($this->result === 'split' && in_array($this->hero_seat, (array) $this->winning_seat));
    }

    public function getTotalPot(): float
    {
        return ($this->pot_river ?? $this->pot_turn ?? $this->pot_flop ?? $this->pot_preflop ?? 0);
    }

    public function getHeroStackChange(): float
    {
        $before = data_get($this->stacks_before, 'seat_' . $this->hero_seat, 0);
        $after = data_get($this->stacks_after, 'seat_' . $this->hero_seat, 0);

        return $after - $before;
    }
}
