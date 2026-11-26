<?php

return [
    // Motor por defecto de los bots: 'rules' (reglas actuales) o 'ai' (modelo local). Cada bot lo puede cambiar con metadata.engine.
    'engine' => env('AI_ENGINE', 'rules'),

    // Razonamiento del modelo (experimento): null = respuesta directa (2 s), 'brief' = un análisis breve antes de las probabilidades,
    // 'think' = el modo de razonamiento del modelo (~60 s por decisión).
    'reasoning' => env('AI_REASONING'),

    // Calles que decide la IA (cada una tiene su servicio en python/streets). Las demás siguen con reglas.
    'streets' => array_values(array_filter(array_map('trim', explode(',', env('AI_STREETS', 'preflop,flop,turn,river'))))),

    'url' => env('PYTHON_SERVICE_URL', 'http://python-service:5000'),

    // Segundos máximos por decisión (un modelo grande tarda; el tiempo no es un problema, la calidad sí)
    'timeout' => (int) env('AI_TIMEOUT', 600),

    // Cuánto se adapta cada bot al rival concreto según su habilidad (nivel del cerebro): de 0 (juega siempre igual) a 1 (adaptación completa).
    // Un pro nota que el maníaco sube y farolea y ajusta; un whale casi no se da cuenta.
    'adaptation' => ['pro' => 1.0, 'semi' => 0.7, 'weak' => 0.35, 'whale' => 0.1, 'default' => 0.5],

    // Modelo elegido en la instalación (lo escribe init.ps1)
    'model_file' => storage_path('app/ai/model.json'),
];
