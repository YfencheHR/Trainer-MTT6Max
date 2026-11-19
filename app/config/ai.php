<?php

return [
    // Motor por defecto de los bots: 'rules' (reglas actuales) o 'ai' (modelo local). Cada bot lo puede cambiar con metadata.engine.
    'engine' => env('AI_ENGINE', 'rules'),

    // Calles que decide la IA (cada una tiene su servicio en python/streets). Las demás siguen con reglas.
    'streets' => ['flop'],

    'url' => env('PYTHON_SERVICE_URL', 'http://python-service:5000'),

    // Segundos máximos por decisión (un modelo grande tarda; el tiempo no es un problema, la calidad sí)
    'timeout' => (int) env('AI_TIMEOUT', 600),

    // Modelo elegido en la instalación (lo escribe init.ps1)
    'model_file' => storage_path('app/ai/model.json'),
];
