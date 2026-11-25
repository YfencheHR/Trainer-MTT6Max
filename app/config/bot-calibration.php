<?php

/**
 * Calibración del juego postflop de los bots.
 *
 * fold_gain: multiplica el fold ante apuesta que sale de las estadísticas del perfil. Se puso (1,35 / 1,65 / 1,35) porque en torneos simulados
 * bot contra bot el fold medio quedaba en ~0,65 del que marcan las estadísticas (37 % frente a 56 % en el flop, 32 frente a 53 en el turn, 41
 * frente a 64 en el river). Con la réplica de manos REALES (`php artisan bots:replay … --marginal`, 4.000 decisiones postflop ante apuesta) el efecto
 * contrario: las estadísticas piden 57 / 52 / 58 %, la gente foldea 57 / 49 / 55 % y el bot, con esa ganancia, foldeaba 65 / 64 / 65 % (7-15 puntos de
 * más; en apuestas de 33-66 % del bote 65-70 % frente a 49-58 % real). Sin ganancia: 59 / 54 / 61 %, que ya es lo que piden las estadísticas, y con 1,15
 * 62 / 57 / 63 %. Como el objetivo es parecerse a las personas, la ganancia vuelve a 1 (neutra). Es una palanca de calibración: si se cambia,
 * se vuelve a medir con la réplica (`--fold-gain=` permite probar valores sin tocar este archivo).
 */
return [
    'fold_gain' => [
        'flop' => 1.0,
        'turn' => 1.0,
        'river' => 1.0,
    ],
];
