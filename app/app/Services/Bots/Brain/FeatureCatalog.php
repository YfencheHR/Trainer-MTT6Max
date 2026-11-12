<?php

namespace App\Services\Bots\Brain;

/**
 * Características (features) que las reglas pueden usar en sus condiciones.
 *
 * IMPORTANTE: son lo que el bot PERCIBE, no la realidad. La habilidad del bot limita lo que ve
 * (por ejemplo, un jugador flojo puede no reconocer una escalera intercalada). Las características
 * "reales" solo se guardan en el historial para analizar; las reglas no pueden usarlas.
 */
class FeatureCatalog
{
    /** @return array<string, array{type:string, desc:string}> */
    public static function all(): array
    {
        $f = [
            // Situación
            'street' => ['string', 'preflop | flop | turn | river'],
            'street_index' => ['int', '0 preflop · 1 flop · 2 turn · 3 river'],
            'position' => ['string', 'BTN | SB | BB | UTG | HJ | CO'],
            'position_group' => ['string', 'early | middle | late | blinds'],
            'opponents' => ['int', 'rivales que siguen en la mano'],
            'limpers' => ['int', 'jugadores que han igualado la ciega sin subir (preflop)'],
            'preflop_raises' => ['int', 'subidas hechas preflop'],
            'raises_this_street' => ['int', 'subidas en la calle actual'],
            'is_preflop_aggressor' => ['bool', 'fue el último en subir preflop'],
            'has_initiative' => ['bool', 'tiene la iniciativa en esta calle (flop: subió preflop; turn y river: apostó en la calle anterior)'],
            'i_bet_this_street' => ['bool', 'ya ha apostado o subido en esta calle (si ahora se enfrenta a una apuesta es una subida a la suya)'],
            'in_position' => ['bool', 'actúa después que todos los rivales que siguen en la mano (IP); false = fuera de posición'],
            'stats_source' => ['string', 'de dónde salen sus cifras en esta decisión: defensor_ip | defensor_oop | agresor_ip | agresor_oop | ante_subida (si no está, las generales de la calle)'],
            'i_raised_preflop' => ['bool', 'ha subido en algún momento preflop (para saber si le hacen 3-bet a su apertura)'],
            'callers_after_raise' => ['int', 'jugadores que han pagado la subida (preflop): habilita el squeeze'],
            'facing_bet' => ['bool', 'hay una apuesta que igualar'],
            'facing_allin' => ['bool', 'igualar supone todo su stack'],
            'can_raise' => ['bool', 'puede subir'],
            // Tamaños
            'stack_bb' => ['float', 'stack en ciegas grandes'],
            'pot_bb' => ['float', 'bote en ciegas grandes'],
            'to_call_bb' => ['float', 'lo que cuesta igualar, en ciegas grandes'],
            'bet_vs_pot' => ['float', 'apuesta rival como % del bote anterior a ella'],
            'pot_odds' => ['float', 'equity mínima (%) para que igualar sea rentable'],
            'spr' => ['float', 'stack / bote'],
            'spr_after_call' => ['float', 'SPR efectivo que queda después de igualar'],
            'effective_stack_bb' => ['float', 'menor stack inicial efectivo entre el bot y los rivales que siguen en la mano'],
            'bet_size_factor' => ['float', 'ajuste del tamaño de apuesta por valor según la respuesta estimada de los rivales'],
            // Mano de salida
            'hand_pair' => ['bool', 'pareja de bolsillo'],
            'hand_suited' => ['bool', 'cartas del mismo palo'],
            'hand_connected' => ['bool', 'cartas consecutivas'],
            'hand_code' => ['string', 'la mano como AA, AKs, 72o…'],
            'hand_gap' => ['int', 'huecos entre las dos cartas (0 = consecutivas)'],
            'hand_high' => ['int', 'carta alta de la mano (0 = 2 … 12 = As)'],
            'preflop_rank' => ['float', 'percentil de la mano inicial (0 = la mejor, 100 = la peor)'],
            'preflop_rank_pos' => ['float', 'idem ajustado por posición (en posición tardía la mano "sube")'],
            'preflop_group' => ['string', 'premium | strong | good | playable | speculative | trash'],
            // Mesa
            'board_count' => ['int', 'cartas comunitarias'],
            'board_paired' => ['bool', 'board con pareja'],
            'board_flush_possible' => ['bool', 'hay 3 o más cartas del mismo palo'],
            'board_four_flush' => ['bool', 'hay 4 cartas del mismo palo: el color está a una carta'],
            'board_straight_possible' => ['bool', 'hay 3 o más cartas en una ventana de 5 rangos'],
            'board_high' => ['int', 'carta más alta del board'],
            'board_has_ace' => ['bool', 'hay un As en el board'],
            'board_wetness' => ['int', '0-100: lo peligroso/conectado que es el board'],
            // Mano hecha (percibida)
            'made' => ['string', 'high_card | pair | two_pair | trips | straight | flush | full_house | quads | straight_flush | royal_flush'],
            'made_category' => ['int', '0 carta alta … 8 escalera de color (percibida)'],
            'perceived_rank' => ['float', '% de manos rivales posibles que le ganan (0 = nuts) según lo que ve'],
            'hole_cards_used' => ['int', 'cuántas de sus 2 cartas forman su mano'],
            'draw_type' => ['string', 'none | gutshot | oesd | flush | combo'],
            'draw_outs' => ['int', 'cartas que completan su proyecto (color + escalera)'],
            'backdoor_flush' => ['bool', 'flop: tiene tres del mismo palo y le faltan DOS cartas para el color (valor pequeño pero real)'],
            'backdoor_straight' => ['bool', 'flop: tiene tres rangos dentro de una ventana de cinco y le faltan DOS cartas para la escalera'],
            'flush_draw_outs' => ['int', 'cartas que completan su proyecto de COLOR (9 = proyecto completo)'],
            'straight_draw_outs' => ['int', 'cartas que completan su proyecto de ESCALERA (8 = abierto, 4 = gutshot)'],
            'equity' => ['float', 'probabilidad estimada de ganar (%) contra rivales aleatorios'],
            'range_rank' => ['float', '% de manos DE SU PROPIO RANGO que le ganan (0 = lo mejor de su rango, 100 = lo peor)'],
            'range_equity' => ['float', 'equity aproximada (%) contra su rango: lo que no le gana de su rango, más sus proyectos'],
            'opponent_range_pct' => ['float', 'porcentaje aproximado de manos con el que llega cada rival a esta calle'],
            'value_combo_pct' => ['float', 'porcentaje de combos rivales que vencen la mano actual'],
            'value_combos' => ['int', 'combos rivales que vencen la mano actual'],
            'worse_combos' => ['int', 'combos rivales que pierde la mano actual'],
            'opponent_call_frequency' => ['float', 'frecuencia estimada de call de los rivales'],
            'opponent_fold_frequency' => ['float', 'frecuencia estimada de fold de los rivales'],
            'opponent_raise_frequency' => ['float', 'frecuencia estimada de subida de los rivales'],
            'opponent_bet_frequency' => ['float', 'frecuencia estimada de apuesta de los rivales'],
            'opponent_aggression' => ['float', 'agresividad postflop media de los rivales'],
            'opponent_stats_confidence' => ['float', 'confianza de las estadísticas postflop de los rivales (0-1)'],
            'opponent_bluff_frequency' => ['float', 'máxima capacidad de farol respaldada por apuestas river vistas en showdown'],
            'bluff_candidate' => ['bool', 'sin valor de showdown (aire): candidata a farol'],
            // Sus estadísticas en esta calle (de su perfil / PokerTracker): base de la matriz
            'own_f2bet' => ['float', 'su % de fold ante una apuesta en esta calle'],
            'own_raise_vs_bet' => ['float', 'su % de subida del total que continúa ante una apuesta (0 = nunca sube)'],
            'own_bet_freq' => ['float', 'su % de apostar cuando le toca (cbet si fue el agresor, donk / barril en el resto)'],
            'own_vb_ratio' => ['float', 'su ratio apuestas de valor : faroles (2 = dos de valor por cada farol)'],
            'own_af' => ['float', 'su factor de agresión en esta calle'],
            'own_cr' => ['float', 'su % de check-raise en esta calle'],
            // Rival que apostó / subió (estadísticas observadas)
            'agg_known' => ['bool', 'hay datos suficientes del agresor'],
            'agg_vpip' => ['float', 'VPIP del agresor'],
            'agg_pfr' => ['float', 'PFR del agresor'],
            'agg_three_bet' => ['float', '3-bet del agresor'],
            'agg_af' => ['float', 'agresión del agresor'],
            'agg_hands' => ['int', 'manos observadas del agresor'],
            'agg_barrels' => ['int', 'calles anteriores en las que el agresor ya apostó (línea de valor)'],
            'bluff_likelihood' => ['float', 'probabilidad estimada (%) de que la apuesta sea un farol, según la secuencia y el board. Un jugador flojo la lee con sesgo a "me farolean"'],
            // Intenciones (memoria del propio jugador en esta mano)
            'flag_check_raise' => ['bool', 'hizo check con intención de subir'],
            'flag_float' => ['bool', 'igualó con intención de apostar después'],
            'flag_slowplay' => ['bool', 'está haciendo slowplay'],
        ];

        $out = [];
        foreach ($f as $name => [$type, $desc]) {
            $out[$name] = ['type' => $type, 'desc' => $desc];
        }

        return $out;
    }

    public static function exists(string $name): bool
    {
        return isset(self::all()[$name]);
    }
}
