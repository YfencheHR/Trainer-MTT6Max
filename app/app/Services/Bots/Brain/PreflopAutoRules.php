<?php

namespace App\Services\Bots\Brain;

/**
 * Genera las reglas preflop de un bot A PARTIR DE SUS DATOS (ver BotProfile), por posición:
 *
 *   sin subidas       abre con su % de apertura · limpea con su % de limp · el resto fold (o check en la BB)
 *   una subida        3-bet con su % de 3-bet · paga con su % de cold call / defensa · el resto fold
 *                     (squeeze si hay callers detrás de la subida)
 *   le hacen 3-bet    (si él había subido) continúa con (100 − fold a 3-bet) % de su rango: 4-bet o paga
 *   le hacen 4-bet    continúa con (100 − fold a 4-bet) % de su rango de 3-bet
 *
 * Todo en percentiles de la mano inicial (preflop_rank). Si el bot tiene estadísticas por profundidad de stack
 * o rangos explícitos por mano, se generan reglas más específicas con prioridad mayor que sustituyen a las generales.
 *
 * Las reglas salen en el mismo formato JSON que las que escribe una persona: se pueden leer, copiar y editar.
 */
class PreflopAutoRules
{
    /**
     * @param array $profile  salida de BotProfile
     * @param array $opts     open_mix (acción => peso) · adapt (bool, reglas de adaptación al rival)
     */
    public static function generate(array $profile, array $opts): array
    {
        $openMix = $opts['open_mix'] ?? ['open_2_5bb' => 70, 'open_3bb' => 30];
        $rules = [];

        foreach (BotProfile::POSITIONS as $pos) {
            array_push($rules, ...self::positionRules($pos, $profile['positions'][$pos], $openMix, !empty($opts['adapt']), null, 0));
        }

        // Variantes por profundidad de stack: sustituyen a las generales dentro de su tramo
        foreach ($profile['buckets'] as $bucket => $positions) {
            foreach ($positions as $pos => $d) {
                array_push($rules, ...self::positionRules($pos, $d, $openMix, !empty($opts['adapt']), $bucket, 50));
            }
        }

        $rules[] = self::notRaiser();
        array_push($rules, ...self::headsUp($profile['positions'], $openMix));

        // Rangos explícitos por mano: mandan sobre todo lo anterior
        array_push($rules, ...self::explicitRanges($profile['ranges'], $openMix));

        return [
            'id' => 'preflop',
            'name' => 'Preflop (autoconfigurado desde los datos del bot)',
            'when' => self::c('street', '=', 'preflop'),
            'auto' => true,
            'rules' => $rules,
        ];
    }

    // ------------------------------------------------------------ por posición

    private static function positionRules(string $pos, array $d, array $openMix, bool $adapt, ?string $bucket, int $priority): array
    {
        $tag = $bucket ? "{$bucket}_{$pos}" : $pos;
        $conds = fn(array $extra) => ['all' => array_values(array_filter([
            self::c('position', '=', $pos),
            $bucket ? self::c('effective_stack_bb', 'between', [BotProfile::BUCKETS[$bucket][0], BotProfile::BUCKETS[$bucket][1]]) : null,
            ...$extra,
        ]))];
        // En una variante por tramo, primero se limpia lo que pintó la regla general
        $reset = $bucket ? [['action' => 'fold']] : [];

        $out = [];

        // ---- Sin subidas
        $out[] = [
            'id' => "pre_abrir_{$tag}", 'priority' => $priority, 'name' => "{$pos}: nadie ha subido",
            'when' => $conds([self::c('preflop_raises', '=', 0)]),
            'paint' => $reset,
            'rules' => array_values(array_filter([
                $d['limp'] > 0 ? [
                    'id' => "pre_limp_{$tag}", 'priority' => 5, 'name' => "Limp {$d['limp']} %",
                    'when' => ['all' => [self::c('preflop_rank', '>', $d['open']), self::c('preflop_rank', '<=', $d['open'] + $d['limp'])]],
                    'paint' => [['action' => 'call']],
                ] : null,
                [
                    'id' => "pre_open_{$tag}", 'priority' => 10, 'name' => "Abre {$d['open']} %",
                    'when' => self::c('preflop_rank', '<=', $d['open']),
                    'paint' => [['mix' => $openMix, 'by' => 'rows']],
                    'rules' => [[
                        'id' => "pre_push_corto_{$tag}", 'name' => 'Stack corto: all-in',
                        'when' => self::c('effective_stack_bb', '<=', 12),
                        'paint' => [['action' => 'allin']],
                    ]],
                ],
            ])),
        ];

        // ---- Una subida delante
        $v = $d['vs_open'];
        $out[] = [
            'id' => "pre_contra_subida_{$tag}", 'priority' => $priority, 'name' => "{$pos}: una subida delante",
            'when' => $conds([self::c('preflop_raises', '=', 1)]),
            'paint' => $reset,
            'rules' => array_values(array_filter([
                [
                    'id' => "pre_pagar_{$tag}", 'priority' => 5, 'name' => 'Paga hasta ' . ($v['three_bet'] + $v['call']) . ' %',
                    'when' => self::c('preflop_rank', '<=', $v['three_bet'] + $v['call']),
                    'paint' => [['action' => 'call']],
                    'rules' => $adapt ? [[
                        'id' => "adapt_abridor_tight_{$tag}",
                        'name' => 'Adaptación: contra un abridor muy tight suelta lo marginal',
                        'when' => ['all' => [self::c('agg_known', '=', true), self::c('agg_pfr', '<=', 9), self::c('preflop_rank', '>', $v['three_bet'] + $v['call'] * 0.5)]],
                        'paint' => [['action' => 'fold', 'only' => ['call']]],
                    ]] : [],
                ],
                [
                    'id' => "pre_3bet_{$tag}", 'priority' => 10, 'name' => "3-bet {$v['three_bet']} %",
                    'when' => self::c('preflop_rank', '<=', $v['three_bet']),
                    'paint' => [['mix' => ['raise_x3' => 75, 'allin' => 25], 'by' => 'rows']],
                    'rules' => [[
                        'id' => "pre_3bet_corto_{$tag}", 'name' => 'Stack corto: all-in',
                        'when' => self::c('effective_stack_bb', '<=', 25),
                        'paint' => [['action' => 'allin']],
                    ]],
                ],
                [
                    'id' => "pre_squeeze_{$tag}", 'priority' => 12, 'name' => "Squeeze {$d['squeeze']} % (hay callers detrás de la subida)",
                    'when' => ['all' => [self::c('callers_after_raise', '>=', 1), self::c('preflop_rank', '<=', $d['squeeze'])]],
                    'paint' => [['action' => 'raise_x4']],
                ],
            ])),
        ];

        // ---- Había subido y le hacen 3-bet
        $t = $d['vs_3bet'];
        $out[] = [
            'id' => "pre_contra_3bet_{$tag}", 'priority' => $priority, 'name' => "{$pos}: había subido y le hacen 3-bet",
            'when' => $conds([self::c('preflop_raises', '=', 2), self::c('i_raised_preflop', '=', true)]),
            'paint' => $reset,
            'rules' => [
                [
                    'id' => "pre_pagar_3bet_{$tag}", 'priority' => 5, 'name' => "Continúa {$t['continue']} %",
                    'when' => self::c('preflop_rank', '<=', $t['continue']),
                    'paint' => [['action' => 'call']],
                ],
                [
                    'id' => "pre_4bet_{$tag}", 'priority' => 10, 'name' => "4-bet {$t['four_bet']} %",
                    'when' => self::c('preflop_rank', '<=', $t['four_bet']),
                    'paint' => [['mix' => ['raise_x2_2' => 55, 'allin' => 45], 'by' => 'rows']],
                    'rules' => [[
                        'id' => "pre_4bet_corto_{$tag}", 'when' => self::c('effective_stack_bb', '<=', 40),
                        'paint' => [['action' => 'allin']],
                    ]],
                ],
            ],
        ];

        // ---- Resubió y le hacen 4-bet
        $out[] = [
            'id' => "pre_contra_4bet_{$tag}", 'priority' => $priority, 'name' => "{$pos}: le hacen 4-bet",
            'when' => $conds([self::c('preflop_raises', '>=', 3), self::c('i_raised_preflop', '=', true)]),
            'paint' => $reset,
            'rules' => [[
                'id' => "pre_continuar_4bet_{$tag}", 'priority' => 5, 'name' => "Continúa {$d['vs_4bet']['continue']} %",
                'when' => self::c('preflop_rank', '<=', $d['vs_4bet']['continue']),
                'paint' => [['action' => 'allin']],
            ]],
        ];

        return $out;
    }

    /** Hay 3-bet o más y él no había subido: solo continúa con premium. */
    private static function notRaiser(): array
    {
        return [
            'id' => 'pre_contra_3bet_sin_haber_subido', 'name' => 'Dos o más subidas y él no había subido: solo premium',
            'when' => ['all' => [self::c('preflop_raises', '>=', 2), self::c('i_raised_preflop', '=', false)]],
            'rules' => [
                ['id' => 'pre_premium_paga', 'priority' => 5, 'when' => self::c('preflop_rank', '<=', 2.5), 'paint' => [['action' => 'call']]],
                ['id' => 'pre_premium_all_in', 'priority' => 10, 'when' => self::c('preflop_rank', '<=', 1.2), 'paint' => [['mix' => ['allin' => 70, 'raise_x2_2' => 30], 'by' => 'rows']]],
            ],
        ];
    }

    /** Heads-up: el botón es la SB y abre muy ancho; la BB defiende ancho. */
    private static function headsUp(array $positions, array $openMix): array
    {
        $sb = $positions['SB'];
        $open = max($sb['open'], 70.0);
        $defend = max($positions['BB']['vs_open']['three_bet'] + $positions['BB']['vs_open']['call'], 55.0);

        return [
            [
                'id' => 'pre_heads_up_sb', 'priority' => 55, 'name' => 'Heads-up: la SB (botón) abre muy ancho',
                'when' => ['all' => [self::c('opponents', '=', 1), self::c('position', '=', 'SB'), self::c('preflop_raises', '=', 0)]],
                'paint' => [['action' => 'fold']],
                'rules' => [['id' => 'hu_abrir', 'when' => self::c('preflop_rank', '<=', $open), 'paint' => [['mix' => $openMix, 'by' => 'rows']]]],
            ],
            [
                'id' => 'pre_heads_up_bb', 'priority' => 55, 'name' => 'Heads-up: la BB defiende ancho',
                'when' => ['all' => [self::c('opponents', '=', 1), self::c('position', '=', 'BB'), self::c('preflop_raises', '=', 1)]],
                'paint' => [['action' => 'fold']],
                'rules' => [
                    ['id' => 'hu_pagar', 'priority' => 5, 'when' => self::c('preflop_rank', '<=', $defend), 'paint' => [['action' => 'call']]],
                    ['id' => 'hu_3bet', 'priority' => 10, 'when' => self::c('preflop_rank', '<=', $positions['BB']['vs_open']['three_bet'] + 4), 'paint' => [['action' => 'raise_x3']]],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------ rangos explícitos

    private static function explicitRanges(array $ranges, array $openMix): array
    {
        $rules = [];
        $raiseAction = ['opening' => array_key_first($openMix), 'vs_open' => 'raise_x3', 'defense_bb' => 'raise_x3', 'cold_call' => 'raise_x3',
            'squeeze' => 'raise_x4', 'vs_3bet' => 'raise_x2_2', 'vs_4bet' => 'allin'];

        foreach ($ranges as $pos => $byAction) {
            foreach ($byAction as $action => $byBucket) {
                foreach ($byBucket as $bucket => $hands) {
                    $when = ['all' => array_values(array_filter([
                        self::c('position', '=', $pos),
                        $bucket !== '*' && isset(BotProfile::BUCKETS[$bucket]) ? self::c('effective_stack_bb', 'between', BotProfile::BUCKETS[$bucket]) : null,
                        ...self::actionContext($action),
                    ]))];

                    // Agrupa las manos con las mismas frecuencias: una regla por grupo
                    $groups = [];
                    foreach ($hands as $code => $f) {
                        $raise = min(1.0, $f['raise'] ?? 0);
                        $call = min(1.0, ($f['call'] ?? 0) + ($f['limp'] ?? 0));
                        $key = round($raise, 2) . '|' . round($call, 2);
                        $groups[$key]['hands'][] = $code;
                        $groups[$key]['w'] = [$raise, $call];
                    }

                    $children = [];
                    foreach ($groups as $key => $g) {
                        [$raise, $call] = $g['w'];
                        $total = $raise + $call;
                        if ($total > 1) {
                            $raise /= $total;
                            $call /= $total;
                        }
                        $children[] = [
                            'id' => "rango_{$pos}_{$action}_{$bucket}_" . md5($key),
                            'when' => self::c('hand_code', 'in', $g['hands']),
                            'paint' => [['mix' => array_filter([
                                $raiseAction[$action] ?? 'raise_x3' => $raise * 100,
                                'call' => $call * 100,
                                'fold' => max(0, 1 - $raise - $call) * 100,
                            ], fn($w) => $w > 0), 'by' => 'rows']],
                        ];
                    }

                    $rules[] = [
                        'id' => "rango_{$pos}_{$action}_{$bucket}", 'priority' => 60, 'name' => "Rango explícito {$pos} / {$action}",
                        'when' => $when,
                        'paint' => [['action' => 'fold']], // el rango explícito es el rango completo
                        'rules' => $children,
                    ];
                }
            }
        }

        return $rules;
    }

    /** Situación preflop equivalente a cada acción de la tabla de rangos. */
    private static function actionContext(string $action): array
    {
        return match ($action) {
            'opening' => [self::c('preflop_raises', '=', 0)],
            'vs_open', 'cold_call' => [self::c('preflop_raises', '=', 1)],
            'defense_bb' => [self::c('preflop_raises', '=', 1), self::c('position', '=', 'BB')],
            'squeeze' => [self::c('preflop_raises', '=', 1), self::c('callers_after_raise', '>=', 1)],
            'vs_3bet' => [self::c('preflop_raises', '=', 2), self::c('i_raised_preflop', '=', true)],
            'vs_4bet' => [self::c('preflop_raises', '>=', 3), self::c('i_raised_preflop', '=', true)],
            default => [],
        };
    }

    private static function c(string $f, string $op, mixed $v): array
    {
        return ['f' => $f, 'op' => $op, 'v' => $v];
    }
}
