<?php

namespace App\Services\Bots\Brain;

use App\Models\Bot;

/**
 * Cerebros por defecto de los 9 perfiles. Se generan con el propio sistema de reglas (JSON), de modo
 * que se pueden guardar en cada bot, editar y heredar en los jugadores.
 *
 * Cada cerebro combina:
 *   - HABILIDAD (tier): qué tan bien percibe (escaleras sutiles, proyectos…) y cuánto se equivoca.
 *   - RANGOS (VPIP / PFR / 3-bet del bot) para el juego preflop.
 *   - ESTILO postflop: frecuencia de apuesta de continuación y de farol, cuánto paga, etc.
 *   - ESPECIALES (solo perfiles avanzados): slowplay, check-raise, float, farol con subida.
 *   - ADAPTACIÓN (solo perfiles avanzados): reglas que usan las estadísticas observadas del rival.
 */
class BrainTemplates
{
    /** Habilidad por nivel: percepción, ruido al estimar e imprecisión de la matriz. */
    private const TIERS = [
        'pro' => [
            'perception' => ['straight' => 1.0, 'flush' => 1.0, 'straight_flush' => 1.0, 'complexity_penalty' => 0.0, 'draws' => 1.0, 'flush_draws' => 1.0, 'straight_draws' => 1.0, 'backdoor_flush' => 0.95, 'backdoor_straight' => 0.85, 'board_awareness' => 1.0],
            'noise' => ['rank_sigma' => 1.5, 'equity_sigma' => 1.0, 'rank_bias' => 0, 'equity_bias' => 0, 'bluff_sigma' => 3],
            'suspicion' => 0.0,
            'imprecision' => ['rate' => 1, 'mistakes' => ['fold' => 50, 'call' => 50], 'protect' => ['allin']],
        ],
        'semi' => [
            'perception' => ['straight' => 0.94, 'flush' => 0.97, 'straight_flush' => 0.92, 'complexity_penalty' => 0.10, 'draws' => 0.85, 'flush_draws' => 0.9, 'straight_draws' => 0.8, 'backdoor_flush' => 0.75, 'backdoor_straight' => 0.6, 'board_awareness' => 0.95],
            'noise' => ['rank_sigma' => 3.0, 'equity_sigma' => 2.0, 'rank_bias' => -1, 'equity_bias' => 1, 'bluff_sigma' => 5],
            'suspicion' => 0.08,
            'imprecision' => ['rate' => 4, 'mistakes' => ['fold' => 50, 'call' => 50], 'protect' => ['allin']],
        ],
        'weak' => [
            'perception' => ['straight' => 0.86, 'flush' => 0.97, 'straight_flush' => 0.80, 'complexity_penalty' => 0.15, 'draws' => 0.60, 'flush_draws' => 0.7, 'straight_draws' => 0.5, 'backdoor_flush' => 0.4, 'backdoor_straight' => 0.2, 'board_awareness' => 0.60],
            'noise' => ['rank_sigma' => 6.0, 'equity_sigma' => 5.0, 'rank_bias' => -6, 'equity_bias' => 4, 'bluff_sigma' => 8],
            'suspicion' => 0.55,
            'imprecision' => ['rate' => 9, 'mistakes' => ['fold' => 35, 'call' => 35, 'bet_pot' => 30], 'protect' => []],
        ],
        'whale' => [
            'perception' => ['straight' => 0.55, 'flush' => 0.96, 'straight_flush' => 0.50, 'complexity_penalty' => 0.25, 'draws' => 0.40, 'flush_draws' => 0.5, 'straight_draws' => 0.3, 'backdoor_flush' => 0.2, 'backdoor_straight' => 0.08, 'board_awareness' => 0.35],
            'noise' => ['rank_sigma' => 10.0, 'equity_sigma' => 9.0, 'rank_bias' => -9, 'equity_bias' => 7, 'bluff_sigma' => 10],
            'suspicion' => 0.70,
            'imprecision' => ['rate' => 14, 'mistakes' => ['fold' => 25, 'call' => 25, 'bet_pot' => 25, 'allin' => 25], 'protect' => []],
        ],
    ];

    /** Parámetros de estilo por tipo de bot. */
    private const PROFILES = [
        'setminer' => ['tier' => 'semi', 'vpip' => 9, 'pfr' => 7, 'three_bet' => 2, 'cbet' => 35, 'bluff' => 0, 'margin' => 8, 'max_continue' => 30, 'style' => 'setminer'],
        'roca' => ['tier' => 'semi', 'vpip' => 13, 'pfr' => 9, 'three_bet' => 3, 'cbet' => 40, 'bluff' => 0, 'margin' => 8, 'max_continue' => 30, 'style' => 'rock'],
        'nit' => ['tier' => 'pro', 'vpip' => 16, 'pfr' => 14, 'three_bet' => 5, 'cbet' => 70, 'bluff' => 5, 'margin' => 4, 'max_continue' => 35, 'style' => 'nit', 'adapt' => true],
        'omc' => ['tier' => 'semi', 'vpip' => 22, 'pfr' => 6, 'three_bet' => 3, 'cbet' => 25, 'bluff' => 0, 'margin' => 3, 'max_continue' => 45, 'style' => 'limper'],
        'tag' => ['tier' => 'pro', 'vpip' => 21, 'pfr' => 19, 'three_bet' => 7, 'cbet' => 65, 'bluff' => 12, 'margin' => 0, 'max_continue' => 100, 'style' => 'tag', 'adapt' => true,
            'specials' => ['slowplay' => 15, 'check_raise' => 8, 'bluff_raise' => 6]],
        'lag' => ['tier' => 'pro', 'vpip' => 28, 'pfr' => 26, 'three_bet' => 10, 'cbet' => 75, 'bluff' => 25, 'margin' => -2, 'max_continue' => 100, 'style' => 'lag', 'adapt' => true,
            'specials' => ['slowplay' => 10, 'check_raise' => 10, 'bluff_raise' => 14, 'float_call' => 12]],
        'calling_station' => ['tier' => 'weak', 'vpip' => 40, 'pfr' => 15, 'three_bet' => 2, 'cbet' => 20, 'bluff' => 0, 'margin' => -8, 'max_continue' => 100, 'style' => 'station'],
        'maniaco' => ['tier' => 'weak', 'vpip' => 50, 'pfr' => 38, 'three_bet' => 20, 'cbet' => 85, 'bluff' => 45, 'margin' => -10, 'max_continue' => 100, 'style' => 'maniac'],
        'ballena' => ['tier' => 'whale', 'vpip' => 60, 'pfr' => 8, 'three_bet' => 3, 'cbet' => 30, 'bluff' => 10, 'margin' => -10, 'max_continue' => 100, 'style' => 'whale'],
    ];

    public static function forBot(Bot $bot): array
    {
        $p = (self::PROFILES[$bot->templateTipo()] ?? self::PROFILES['tag']) + ['tipo' => $bot->templateTipo()];
        $p['vpip'] = $bot->vpip ?? $p['vpip'];
        $p['pfr'] = $bot->pfr ?? $p['pfr'];

        // El preflop sale de los datos del bot (estadísticas por posición / acción / ciegas y rangos)
        return self::build($bot->nombre ?? $bot->tipo, $p, BotProfile::forBot($bot));
    }

    public static function forType(string $tipo): array
    {
        $p = (self::PROFILES[$tipo] ?? self::PROFILES['tag']) + ['tipo' => $tipo];

        return self::build(ucfirst($tipo), $p);
    }

    /** Genera el cerebro completo a partir de los parámetros de estilo. */
    public static function build(string $name, array $p, ?array $profile = null): array
    {
        $tier = self::TIERS[$p['tier']];
        $profile ??= BotProfile::fromScalars($p['tipo'] ?? 'tag', (float) $p['vpip'], (float) $p['pfr']);

        return [
            'name' => $name,
            'version' => 1,
            'default' => 'fold',
            'skill' => [
                'tier' => $p['tier'],
                'perception' => $tier['perception'],
                'noise' => $tier['noise'],
                'suspicion' => $tier['suspicion'],
                'equity_samples' => 250,
            ],
            'imprecision' => $tier['imprecision'],
            'auto' => ['preflop' => true, 'postflop' => true, 'preflop_source' => $profile['source']],
            // Estadísticas propias: base de la matriz postflop (prior) y rango sobre el que se mide la fuerza de la mano
            'stats' => [
                'vpip' => $profile['global']['vpip'],
                'pfr' => $profile['global']['pfr'],
                'three_bet' => $profile['global']['three_bet'],
            ] + $profile['postflop'],
            'rules' => [self::preflopRules($p, $profile), self::postflop($p)],
        ];
    }

    // ------------------------------------------------------------------ helpers

    private static function c(string $f, string $op, mixed $v): array
    {
        return ['f' => $f, 'op' => $op, 'v' => $v];
    }

    private static function mix(array $weights, ?array $only = null, string $by = 'rows'): array
    {
        return [['mix' => $weights, 'by' => $by] + ($only ? ['only' => $only] : [])];
    }

    /** Región de las primeras N filas pintada con una acción (frecuencia de una acción especial). */
    private static function topRows(float $pct, string $action, ?array $only = null): array
    {
        return [['action' => $action, 'pct' => max(0.0, min(100.0, $pct))] + ($only ? ['only' => $only] : [])];
    }

    // ------------------------------------------------------------------ preflop

    /** Cómo abre cada estilo (tamaños de apertura). */
    private static function openMix(string $style): array
    {
        return match ($style) {
            'maniac' => ['open_3bb' => 55, 'open_2_5bb' => 25, 'bet_pot' => 20],
            'nit', 'rock', 'setminer', 'limper' => ['open_3bb' => 100],
            'whale' => ['open_2bb' => 40, 'open_3bb' => 30, 'call' => 30],
            default => ['open_2_5bb' => 70, 'open_3bb' => 30],
        };
    }

    /** Preflop autoconfigurado a partir del perfil de datos del bot (BotProfile). */
    private static function preflopRules(array $p, array $profile): array
    {
        $rules = PreflopAutoRules::generate($profile, [
            'open_mix' => self::openMix($p['style']),
            'adapt' => !empty($p['adapt']),
        ]);

        if ($p['style'] === 'setminer') {
            // Las parejas de bolsillo se pagan por el precio buscando set (aunque salgan de su rango de % )
            $rules['rules'][] = [
                'id' => 'pre_parejas_buscan_set', 'priority' => 20,
                'name' => 'Setminer: paga una subida pequeña con cualquier pareja',
                'when' => ['all' => [self::c('hand_pair', '=', true), self::c('preflop_raises', '<=', 1), self::c('to_call_bb', '<=', 5)]],
                'paint' => [['action' => 'call', 'only' => ['fold']]],
            ];
        }

        return $rules;
    }
    // ------------------------------------------------------------------ postflop

    /** Postflop construido desde las estadísticas del bot (prior) y modulado por reglas de situación. */
    private static function postflop(array $p): array
    {
        return PostflopAutoRules::generate($p);
    }
}
