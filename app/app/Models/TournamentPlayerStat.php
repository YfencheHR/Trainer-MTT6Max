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
        ...self::STREET_COUNTERS,
    ];

    /** Por calle postflop: oportunidades y veces que apuesta sin apuesta delante y que foldea ante una apuesta. */
    public const STREET_COUNTERS = [
        'flop_bet_opp', 'flop_bet', 'flop_f2bet_opp', 'flop_f2bet',
        'turn_bet_opp', 'turn_bet', 'turn_f2bet_opp', 'turn_f2bet',
        'river_bet_opp', 'river_bet', 'river_f2bet_opp', 'river_f2bet',
    ];

    /** Contadores que se incrementan con el resultado de cada mano. */
    public const COUNTERS = [
        'hands', 'vpip', 'pfr', 'three_bet_opp', 'three_bet', 'fold_to_three_bet_opp', 'fold_to_three_bet',
        'saw_flop', 'cbet_opp', 'cbet', 'aggressive_actions', 'passive_actions', 'showdowns', 'showdown_wins',
        ...self::STREET_COUNTERS,
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

        $streets = [];
        foreach (['flop', 'turn', 'river'] as $street) {
            $streets[$street] = [
                'bet' => $pct($g("{$street}_bet"), $g("{$street}_bet_opp")), 'bet_opp' => $g("{$street}_bet_opp"),
                'f2bet' => $pct($g("{$street}_f2bet"), $g("{$street}_f2bet_opp")), 'f2bet_opp' => $g("{$street}_f2bet_opp"),
            ];
        }

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
            'af_n' => $aggr + $pass,
            'streets' => $streets,
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
