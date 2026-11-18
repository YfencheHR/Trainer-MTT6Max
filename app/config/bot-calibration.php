<?php

/**
 * Calibración del juego postflop de los bots, medida con el simulador sin cabeza (tournament:simulate + bots:audit-postflop).
 *
 * fold_gain: las reglas de situación (precio bueno ×0,3, farol plausible ×0,5, multiway ×1,2…) reparten el fold entre los
 * casos, pero no conservan su media: en un torneo de 600 bots, con las reglas puestas, el fold ante apuesta medio quedaba en
 * ~0,65 del que marcan las estadísticas del propio bot (37 % frente a 56 % en el flop, 32 frente a 53 en el turn, 41 frente a 64
 * en el river). Esta ganancia, por calle, devuelve la media al nivel de las estadísticas y deja que las reglas sigan moviéndolo
 * de un caso a otro. Se vuelve a medir con la auditoría (columnas «fold flop/turn/river») después de tocar reglas o estadísticas.
 */
return [
    'fold_gain' => [
        'flop' => 1.35,
        'turn' => 1.65,
        'river' => 1.35,
    ],
];

/*
 * Medido (torneo simulado de 300 jugadores, fold ante apuesta medio): sin ganancia 37 / 32 / 41 %; con 1,5 / 1,6 / 1,55 → 44 / 43 / 52 %;
 * con 1,9 / 2,0 / 1,95 → 45 / 42 / 48 % (ya no sube: los casos débiles saturan en 100 % y los de «precio bueno» siguen casi sin foldear).
 * Las estadísticas piden 56 / 53 / 64 %: lo que falta no se arregla con más ganancia, está en cómo el bot estima su equity contra el rango
 * rival (en ~47 % de las decisiones cree tener un precio muy bueno y casi nunca abandona).
 */
