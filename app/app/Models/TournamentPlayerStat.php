<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentPlayerStat extends Model
{
    protected $table = 'tournament_player_stats';

    protected $fillable = [
        'tournament_player_id', 'hands', 'vpip', 'pfr', 'three_bet_opp', 'three_bet',
        'fold_to_three_bet_opp', 'fold_to_three_bet', 'saw_flop', 'cbet_opp', 'cbet',
        'aggressive_actions', 'passive_actions', 'showdowns', 'showdown_wins',
    ];

    /** Contadores que se incrementan con el resultado de cada mano. */
    public const COUNTERS = [
        'hands', 'vpip', 'pfr', 'three_bet_opp', 'three_bet', 'fold_to_three_bet_opp', 'fold_to_three_bet',
        'saw_flop', 'cbet_opp', 'cbet', 'aggressive_actions', 'passive_actions', 'showdowns', 'showdown_wins',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(TournamentPlayer::class, 'tournament_player_id');
    }

    /**
     * Resumen para el HUD y los bots: porcentajes (null si no hay muestra) + tamaños de muestra.
     */
    public function summary(): array
    {
        $g = fn(string $k): int => (int) ($this->{$k} ?? 0);
        $pct = fn(int $n, int $d): ?int => $d > 0 ? (int) round($n / $d * 100) : null;

        $aggr = $g('aggressive_actions');
        $pass = $g('passive_actions');

        return [
            'hands' => $g('hands'),
            'vpip' => $pct($g('vpip'), $g('hands')),
            'pfr' => $pct($g('pfr'), $g('hands')),
            'three_bet' => $pct($g('three_bet'), $g('three_bet_opp')),
            'three_bet_opp' => $g('three_bet_opp'),
            'fold_to_three_bet' => $pct($g('fold_to_three_bet'), $g('fold_to_three_bet_opp')),
            'fold_to_three_bet_opp' => $g('fold_to_three_bet_opp'),
            'cbet' => $pct($g('cbet'), $g('cbet_opp')),
            'cbet_opp' => $g('cbet_opp'),
            'af' => $pass > 0 ? round($aggr / $pass, 1) : ($aggr > 0 ? (float) $aggr : null),
            'wtsd' => $pct($g('showdowns'), $g('saw_flop')),
            'wsd' => $pct($g('showdown_wins'), $g('showdowns')),
        ];
    }
    /** Resumen vacío para jugadores sin manos registradas. */
    public static function emptySummary(): array
    {
        return (new self())->summary();
    }
}
