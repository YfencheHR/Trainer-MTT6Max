<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Tournament extends Model
{
    protected $table = 'tournaments';

    protected $fillable = [
        'title',
        'status',
        'config',
        'current_level',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'config' => 'json',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected $appends = ['is_active', 'hand_count'];

    public function hands(): HasMany
    {
        return $this->hasMany(TournamentHand::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(TournamentPlayer::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }

    public function activeTables(): HasMany
    {
        return $this->hasMany(Table::class)->where('is_active', true);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'in_progress';
    }

    public function getHandCountAttribute(): int
    {
        return $this->hands()->count();
    }

    public static function generateTitle(): string
    {
        return 'MTT-' . now()->timestamp . substr(uniqid(), -3);
    }

    public function getConfig(string $key = null)
    {
        if ($key === null) {
            return $this->config;
        }

        return data_get($this->config, $key);
    }
}
