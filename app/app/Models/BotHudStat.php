<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotHudStat extends Model
{
    protected $table = 'bot_hud_stats';

    protected $fillable = [
        'bot_id',
        'nivel',
        'posicion',
        'accion',
        'rango_ciegas',
        'vpip',
        'pfr',
        'af_preflop',
        'three_bet',
        'fold_to_3bet',
        'four_bet',
        'fold_to_4bet',
        'limp',
        'limp_raise',
        'limp_push',
        'call_vs_3bet',
        'reraise_vs_3bet',
        'rfi',
        'squeeze',
        'cold_call',
        'sb_defense',
        'bb_defense',
        'af_flop',
        'cbet',
        'cr_flop',
        'f2cbet',
        'call_vs_bet',
        'raise_vs_bet',
        'cbet_ip',
        'cbet_oop',
        'donk',
        'float',
        'f2raise_flop',
        'af_turn',
        'beturn',
        'cr_turn',
        'f2beturn',
        'barrel',
        'f2raise_turn',
        'checkcall_turn',
        'af_river',
        'betriver',
        'f2betriver',
        'vb_ratio',
        'bluff',
        'shove',
        'wsd',
        'sample_size',
        'confidence'
    ];

    protected $casts = [
        'vpip' => 'integer',
        'pfr' => 'integer',
        'af_preflop' => 'float',
        'af_flop' => 'float',
        'af_turn' => 'float',
        'af_river' => 'float',
        'vb_ratio' => 'float',
        'limp' => 'float',
        'limp_raise' => 'float',
        'limp_push' => 'float',
        'call_vs_3bet' => 'float',
        'reraise_vs_3bet' => 'float',
        'confidence' => 'float',
        'sample_size' => 'integer'
    ];

    /**
     * Relación inversa al bot
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Obtener aggression factor para un street
     */
    public function getAF(string $street = 'preflop'): ?float
    {
        return match($street) {
            'flop' => $this->af_flop,
            'turn' => $this->af_turn,
            'river' => $this->af_river,
            default => $this->af_preflop
        };
    }

    /**
     * Obtener continuación bet para flop
     */
    public function getCBET(): ?int
    {
        return $this->cbet;
    }

    /**
     * Verificar si estadísticas tienen confianza suficiente
     */
    public function isConfident(float $threshold = 0.5): bool
    {
        return $this->confidence >= $threshold && $this->sample_size >= 30;
    }
}
