<?php

return [
    'pause_between_hands_ms' => 2000,
    'auto_replay_speed' => 1.0,
    'show_hole_cards' => [
        'during_hand' => false,
        'at_showdown' => true,
        'at_end' => true,
    ],
    'player_time_modes' => [
        'unlimited' => 'Sin Tiempo',
        'standard' => 'Tiempo Normal',
    ],
    'player_time_defaults' => [
        'standard' => [
            'time_per_decision_seconds' => 30,
            'bank_time_seconds' => 300,
        ],
    ],
];
