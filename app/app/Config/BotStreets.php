<?php

namespace App\Config;

/**
 * Estadísticas de un bot agrupadas por calle (pre-flop, flop, turn, river).
 *
 * Todas viven en una sola fila general de bot_hud_stats (nivel 1, sin posición/acción/ciegas); la calle es solo una
 * forma de agruparlas al mostrarlas y editarlas. "Rigurosas" = las que miden el estilo; "adaptativas" = las que
 * dependen de la situación.
 */
class BotStreets
{
    public const STREETS = [
        'preflop' => ['nombre' => 'Pre-flop', 'stats' => ['vpip', 'pfr', 'af_preflop', 'three_bet', 'fold_to_3bet', 'four_bet', 'fold_to_4bet'], 'adaptativas' => ['rfi', 'squeeze', 'cold_call', 'sb_defense', 'bb_defense']],
        'flop' => ['nombre' => 'Flop', 'stats' => ['af_flop', 'cbet', 'cr_flop', 'f2cbet'], 'adaptativas' => ['cbet_ip', 'cbet_oop', 'donk', 'float', 'f2raise_flop']],
        'turn' => ['nombre' => 'Turn', 'stats' => ['af_turn', 'beturn', 'cr_turn', 'f2beturn'], 'adaptativas' => ['barrel', 'f2raise_turn', 'checkcall_turn']],
        'river' => ['nombre' => 'River', 'stats' => ['af_river', 'betriver', 'f2betriver', 'vb_ratio'], 'adaptativas' => ['bluff', 'shove', 'wsd']],
    ];

    public const LABELS = [
        'vpip' => 'VPIP', 'pfr' => 'PFR', 'af_preflop' => 'AF Pre-flop', 'af_flop' => 'AF Flop', 'af_turn' => 'AF Turn', 'af_river' => 'AF River',
        'three_bet' => '3-Bet', 'fold_to_3bet' => 'Fold to 3-Bet', 'four_bet' => '4-Bet', 'fold_to_4bet' => 'Fold to 4-Bet',
        'rfi' => 'RFI', 'squeeze' => 'Squeeze', 'cold_call' => 'Cold Call', 'sb_defense' => 'SB Defense', 'bb_defense' => 'BB Defense',
        'cbet' => 'C-Bet', 'cr_flop' => 'Check-Raise', 'f2cbet' => 'Fold to C-Bet', 'cbet_ip' => 'C-Bet IP', 'cbet_oop' => 'C-Bet OOP',
        'donk' => 'Donk', 'float' => 'Float', 'f2raise_flop' => 'Fold to Raise',
        'beturn' => 'Bet Turn', 'cr_turn' => 'Check-Raise', 'f2beturn' => 'Fold to Bet', 'barrel' => 'Barrel', 'f2raise_turn' => 'Fold to Raise', 'checkcall_turn' => 'Check-Call',
        'betriver' => 'Bet River', 'f2betriver' => 'Fold to Bet', 'vb_ratio' => 'VB Ratio', 'bluff' => 'Bluff', 'shove' => 'Shove', 'wsd' => 'WSD',
    ];

    /** Estadísticas que no son porcentajes (ratios de agresión). */
    public static function isRatio(string $stat): bool
    {
        return str_starts_with($stat, 'af_') || $stat === 'vb_ratio';
    }

    public static function format(string $stat, $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return self::isRatio($stat) ? rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') : (int) round($value) . '%';
    }
}
