<?php

return [
    'title' => 'Simular un torneo y analizar cómo jugaron los bots',
    'intro' => 'Juega un torneo completo solo de bots (sin pantalla, todas las mesas a la vez) con los perfiles importados repartidos a partes iguales, y al terminar analiza sus decisiones: cuánto abandonan, apuestan o suben cada perfil frente a sus estadísticas de PokerTracker, las fugas y las peores decisiones. Sirve para comprobar que los bots juegan como se espera tras una importación.',
    'players' => 'Jugadores (12 a 600)',
    'speed' => 'Velocidad de ciegas',
    'speed_tipica' => 'Típica',
    'speed_rapida' => 'Rápida',
    'speed_muy_rapida' => 'Muy rápida',
    'button' => 'Simular y analizar',
    'working' => 'Simulando…',
    'replaces' => 'Se borran los torneos simulados anteriores (los torneos que creas tú no se tocan). Con 600 jugadores puede tardar un buen rato; puedes dejar la página abierta y ver el progreso.',
    'already_running' => 'Ya hay una simulación en marcha. Espera a que termine.',
    'done' => '✔ Simulación y análisis terminados',
    'failed' => '✗ La simulación ha fallado (mira el registro)',
    'how_to_read' => 'Cómo leer el análisis: «frecuencias» compara lo que hicieron los bots con las estadísticas de su perfil (marca las diferencias grandes); «fugas» son decisiones concretas con mala equity; «peores decisiones» las que más EV perdieron.',
];
