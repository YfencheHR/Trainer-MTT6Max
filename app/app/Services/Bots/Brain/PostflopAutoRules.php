<?php

namespace App\Services\Bots\Brain;

/**
 * Reglas postflop de un bot construidas DESDE SUS ESTADÍSTICAS (el "prior") y MODULADAS por la situación.
 *
 * Cómo se rellena la matriz (parte de fold en todas las celdas):
 *
 *   1. MODULADORES  (prioridad 0-4)  reglas de situación que ajustan el prior con multiplicadores:
 *                                    tamaño de la apuesta, precio (pot odds), agresividad del rival, lectura de su línea,
 *                                    cuántos rivales quedan…
 *   2. PRIOR        (prioridad 5)    pinta fold / call / subida (o apuesta / check) a partir de las estadísticas del
 *                                    jugador y de la fuerza de su mano dentro de su rango (ver StatsPrior).
 *   3. REFINAMIENTO (prioridad 8+)   tamaños de las apuestas, manos monstruo que van all-in, acciones especiales…
 *
 * Así un jugador con 0 % de fold a la cbet parte de una matriz sin fold y solo cambia si una regla lo empuja;
 * una calling station con 0 % de subida no sube nunca hasta el river y, con la mejor mano, casi siempre solo paga.
 */
class PostflopAutoRules
{
    public static function generate(array $p): array
    {
        $tier = $p['tier'];
        $style = $p['style'];
        $overvalues = in_array($tier, ['weak', 'whale'], true);
        $adapt = !empty($p['adapt']);
        $sp = $p['specials'] ?? [];
        $raiseAction = in_array($style, ['maniac', 'lag'], true) ? 'bet_pot' : 'bet_twothirds';

        // ---------------------------------------------------------------- sin apuesta delante
        $lead = [
            'id' => 'post_sin_apuesta', 'name' => 'Nadie ha apostado delante',
            'when' => self::c('facing_bet', '=', false),
            'rules' => [
                [
                    'id' => 'lead_mod_multiway', 'priority' => 0, 'name' => 'Con varios rivales apuesta menos',
                    'when' => self::c('opponents', '>=', 2),
                    'paint' => [['mod' => ['bet_mult' => 0.7]]],
                ],
                [
                    'id' => 'lead_mod_board_mojado', 'priority' => 1, 'name' => 'Board mojado: protege el valor y se arriesga menos de farol',
                    'when' => self::c('board_wetness', '>=', 45),
                    'paint' => [['mod' => ['bet_mult' => 1.1]]],
                ],
                [
                    'id' => 'lead_mod_valor_ip_river', 'priority' => 2, 'name' => 'Valor en river IP: apuesta más cuando los rivales pagan con frecuencia',
                    'when' => ['all' => [
                        self::c('street', '=', 'river'),
                        self::c('in_position', '=', true),
                        self::c('value_combo_pct', '<=', 15),
                        self::c('opponent_call_frequency', '>=', 55),
                        self::c('opponent_stats_confidence', '>=', 0.3),
                    ]],
                    'paint' => [['mod' => ['bet_mult' => 1.35]]],
                ],
                [
                    'id' => 'lead_prior', 'priority' => 5, 'name' => 'Apuesta según sus estadísticas, su ratio valor:farol y su mano en su rango',
                    'paint' => [['prior' => 'lead', 'bet' => 'bet_half']],
                ],
                [
                    'id' => 'lead_tamano_valor', 'priority' => 8, 'name' => 'Manos de valor: apuestas más grandes',
                    'when' => self::c('range_rank', '<=', 12),
                    'paint' => [['mix' => ['bet_twothirds' => 40, 'bet_pot' => 25, 'bet_half' => 35], 'only' => ['bet_half']]],
                ],
                [
                    'id' => 'lead_tamano_farol', 'priority' => 8, 'name' => 'Faroles: apuestas pequeñas',
                    'when' => self::c('bluff_candidate', '=', true),
                    'paint' => [['mix' => ['bet_third' => 55, 'bet_half' => 45], 'only' => ['bet_half']]],
                ],
                [
                    'id' => 'lead_monstruo', 'priority' => 20, 'name' => 'Mano muy fuerte: apuesta por valor',
                    'when' => self::c('range_rank', '<=', 6),
                    'paint' => [['mix' => ['bet_twothirds' => 55, 'bet_pot' => 45], 'only' => ['check']]],
                    'rules' => self::leadSpecials($sp),
                ],
            ],
        ];

        // ---------------------------------------------------------------- hay una apuesta que igualar
        $mods = [
            ['lead' => 'face_mod_apuesta_grande', 'when' => self::c('bet_vs_pot', '>=', 80), 'mod' => ['fold_mult' => 1.5], 'name' => 'Apuesta grande: abandona más'],
            ['lead' => 'face_mod_apuesta_pequena', 'when' => self::c('bet_vs_pot', '<=', 33), 'mod' => ['fold_mult' => 0.5], 'name' => 'Apuesta pequeña: abandona menos'],
            ['lead' => 'face_mod_precio_bueno', 'when' => ['f' => 'range_equity', 'op' => '>=', 'v' => ['ref' => 'pot_odds', 'add' => 15]], 'mod' => ['fold_mult' => 0.3], 'name' => 'Su equity contra el rango del rival supera con holgura el precio: casi nunca abandona'],
            ['lead' => 'face_mod_precio_malo', 'when' => ['f' => 'range_equity', 'op' => '<', 'v' => ['ref' => 'pot_odds', 'add' => -10]], 'mod' => ['fold_mult' => 1.4], 'name' => 'Su equity contra el rango del rival no llega al precio: abandona más'],
            // Subir exige una mano fuerte en términos absolutos, no solo estar arriba de su rango
            // (los proyectos fuertes quedan fuera: pueden subir de semifarol, y cuánto lo hacen lo marcan sus estadísticas)
            ['lead' => 'face_mod_no_subir_con_media', 'when' => ['all' => [self::c('perceived_rank', '>', 25), self::c('draw_outs', '<', 8)]], 'mod' => ['raise_mult' => 0.15], 'name' => 'Mano media sin proyecto: casi nunca sube'],
            ['lead' => 'face_mod_subir_con_fuerte', 'when' => self::c('perceived_rank', '<=', 12), 'mod' => ['raise_mult' => 1.6], 'name' => 'Mano fuerte: sube más'],
            ['lead' => 'face_mod_no_subir_vs_valor', 'when' => ['all' => [self::c('bluff_likelihood', '<=', 15), self::c('perceived_rank', '>', 8)]], 'mod' => ['raise_mult' => 0.3], 'name' => 'Ante una línea de valor no sube sin una mano muy fuerte'],
            ['lead' => 'face_mod_multiway', 'when' => self::c('opponents', '>=', 2), 'mod' => ['fold_mult' => 1.2, 'raise_mult' => 0.6], 'name' => 'Varios rivales: más cauto'],
            // Lectura de la línea: la usan TODOS; la diferencia entre un experto y un pez está en cómo la perciben
            ['lead' => 'face_mod_farol_plausible', 'when' => self::c('bluff_likelihood', '>=', 40), 'mod' => ['fold_mult' => 0.5], 'name' => 'Cree que pueden estar farolendo: abandona menos'],
            ['lead' => 'face_mod_linea_de_valor', 'when' => ['all' => [self::c('bluff_likelihood', '<=', 15), self::c('bet_vs_pot', '>=', 30)]], 'mod' => ['fold_mult' => 2.5], 'name' => 'La línea y el board casi no tienen faroles: abandona manos medias'],
        ];
        if ($adapt) {
            $mods[] = ['lead' => 'adapt_vs_agresivo', 'when' => ['all' => [self::c('agg_known', '=', true), self::c('agg_af', '>=', 3)]], 'mod' => ['fold_mult' => 0.7], 'name' => 'Adaptación: contra un rival muy agresivo paga más ancho'];
            $mods[] = ['lead' => 'adapt_vs_tight', 'when' => ['all' => [self::c('agg_known', '=', true), self::c('agg_vpip', '<=', 16), self::c('bet_vs_pot', '>=', 50)]], 'mod' => ['fold_mult' => 1.3], 'name' => 'Adaptación: un rival tight que apuesta fuerte suele tenerla'];
        }

        $facing = [];
        foreach ($mods as $i => $m) {
            $facing[] = ['id' => $m['lead'], 'priority' => 1 + ($i % 3), 'name' => $m['name'], 'when' => $m['when'], 'paint' => [['mod' => $m['mod']]]];
        }
        $facing[] = [
            'id' => 'face_prior', 'priority' => 5,
            'name' => 'Abandona / paga / sube según sus estadísticas y la fuerza de su mano en su rango',
            'paint' => [['prior' => 'facing_bet', 'raise' => $raiseAction]],
        ];
        $facing[] = [
            'id' => 'face_monstruo', 'priority' => 20,
            'name' => $style === 'station'
                ? 'Calling station: solo compromete el stack con mano fuerte, SPR bajo y estadísticas que lo justifican'
                : ($overvalues ? 'Jugador flojo: con escalera o color (o mejor) que ve, all-in' : 'Mano monstruo: all-in, sin margen de error'),
            'when' => ['all' => [
                self::c('can_raise', '=', true),
                self::c('own_raise_vs_bet', '>=', 20),
                self::c('opponent_call_frequency', '>=', 50),
                self::c('opponent_stats_confidence', '>=', 0.3),
                self::c('spr_after_call', '<=', 1),
                $style === 'station'
                    ? ['all' => [
                    self::c('street', '=', 'river'),
                    self::c('made_category', '>=', 3),
                    ['any' => [self::c('board_four_flush', '=', false), self::c('made_category', '>=', 5)]],
                ]]
                    : ['any' => array_values(array_filter([
                    self::c('perceived_rank', '<=', 3),
                    self::c('made', 'in', ['straight_flush', 'royal_flush', 'quads', 'full_house']),
                    // Los flojos sobrevaloran lo que ven: cualquier escalera o color ya es "monstruo" para ellos
                    $overvalues ? self::c('made_category', '>=', 4) : null,
                ]))],
            ]],
            'paint' => [['action' => 'allin']],
        ];
        array_push($facing, ...self::facingSpecials($sp));

        return [
            'id' => 'postflop', 'name' => 'Flop, turn y river (desde sus estadísticas)',
            'when' => self::c('street', '!=', 'preflop'),
            'auto' => true,
            'rules' => [$lead, [
                'id' => 'post_con_apuesta', 'name' => 'Hay una apuesta que igualar',
                'when' => self::c('facing_bet', '=', true),
                'rules' => $facing,
            ]],
        ];
    }

    // ------------------------------------------------------------ especiales (solo perfiles avanzados)

    private static function leadSpecials(array $sp): array
    {
        $rules = [];
        if (isset($sp['slowplay'])) {
            $rules[] = [
                'id' => 'especial_slowplay', 'name' => 'Slowplay en mesas secas con pocos rivales',
                'when' => ['all' => [self::c('board_wetness', '<=', 45), self::c('opponents', '<=', 2)]],
                'paint' => self::topRows($sp['slowplay'], 'slowplay'),
            ];
        }
        if (isset($sp['check_raise'])) {
            $rules[] = [
                'id' => 'especial_check_raise', 'name' => 'Trampa: check con intención de subir',
                'when' => self::c('opponents', '=', 1),
                'paint' => self::topRows($sp['check_raise'] + ($sp['slowplay'] ?? 0), 'check_raise'),
            ];
        }

        return $rules;
    }

    private static function facingSpecials(array $sp): array
    {
        $rules = [];
        if (isset($sp['check_raise'])) {
            $rules[] = [
                'id' => 'especial_ejecuta_check_raise', 'priority' => 30,
                'name' => 'Había hecho check con trampa: ahora sube',
                'when' => self::c('flag_check_raise', '=', true),
                'paint' => [['action' => 'check_raise']],
            ];
        }
        if (isset($sp['bluff_raise'])) {
            $rules[] = [
                'id' => 'especial_farol_subida', 'priority' => 9,
                'name' => 'Farol: convierte parte de sus folds en subidas grandes',
                'when' => ['all' => [['f' => 'equity', 'op' => '<', 'v' => ['ref' => 'pot_odds']], self::c('can_raise', '=', true)]],
                'paint' => self::topRows($sp['bluff_raise'], 'bluff_raise', ['fold']),
            ];
        }
        if (isset($sp['float_call'])) {
            $rules[] = [
                'id' => 'especial_float', 'priority' => 9,
                'name' => 'Float en el flop: paga sin equity con intención de robar después',
                'when' => ['all' => [self::c('street', '=', 'flop'), self::c('opponents', '=', 1), self::c('perceived_rank', '<=', 60)]],
                'paint' => self::topRows($sp['float_call'], 'float_call', ['fold']),
            ];
        }

        return $rules;
    }

    private static function topRows(float $pct, string $action, ?array $only = null): array
    {
        return [['action' => $action, 'pct' => max(0.0, min(100.0, $pct))] + ($only ? ['only' => $only] : [])];
    }

    private static function c(string $f, string $op, mixed $v): array
    {
        return ['f' => $f, 'op' => $op, 'v' => $v];
    }
}
