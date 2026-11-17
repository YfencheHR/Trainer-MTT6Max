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
        'source_bot_id',
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

    /**
     * El bot que juega este asiento. En torneos nuevos es una COPIA CONGELADA (archivada) del perfil original tal como
     * estaba al crear el torneo; en los anteriores a ese cambio, el propio perfil. Incluye los archivados.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class)->withoutGlobalScope('active');
    }

    /** El perfil "vivo" del que salió este jugador (puede haberse editado, reimportado o borrado desde entonces). */
    public function sourceBot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'source_bot_id')->withoutGlobalScope('active');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
