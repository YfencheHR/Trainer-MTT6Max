<?php

return [
    [
        'id' => 1,
        'label' => 'Todos (aleatorio)',
        'example' => []
    ],
    [
        'id' => 2,
        'label' => 'Tipo 1: ABB',
        'example' => [['cards' => ['As', 'Ks', 'Qs'], 'separator' => false]]
    ],
    [
        'id' => 3,
        'label' => 'Tipo 2: ABx',
        'example' => [['cards' => ['As', 'Kh', 'Qc'], 'separator' => false]]
    ],
    [
        'id' => 4,
        'label' => 'Tipo 3: Axy',
        'example' => [['cards' => ['As', 'Qh', 'Jc'], 'separator' => false]]
    ],
    [
        'id' => 5,
        'label' => 'Tipo 4: 2Bw',
        'example' => [['cards' => ['As', 'Ah', 'Kc'], 'separator' => false]]
    ],
    [
        'id' => 6,
        'label' => 'Tipo 5: BBB',
        'example' => [['cards' => ['Ks', 'Ks', 'Ks'], 'separator' => false]]
    ],
    [
        'id' => 7,
        'label' => 'Tipo 6: K/Q+2',
        'example' => [['cards' => ['Ks', 'Qs', 'Js'], 'separator' => false]]
    ],
    [
        'id' => 8,
        'label' => 'Tipo 7: J/T+2',
        'example' => [['cards' => ['Js', 'Ts', '9s'], 'separator' => false]]
    ],
    [
        'id' => 9,
        'label' => 'Tipo 8: J/T🔗',
        'example' => [['cards' => ['Js', 'Ts'], 'separator' => false]]
    ],
    [
        'id' => 10,
        'label' => 'Tipo 9: Low🔗',
        'example' => [['cards' => ['9s', '8s'], 'separator' => false]]
    ],
    [
        'id' => 11,
        'label' => 'Tipo 10: Low',
        'example' => [['cards' => ['9s', '8c', '7h'], 'separator' => false]]
    ],
    [
        'id' => 12,
        'label' => 'Tipo 11: Pair',
        'example' => [['cards' => ['As', 'Ah'], 'separator' => false]]
    ],
    [
        'id' => 13,
        'label' => 'Tipo 12: Trip',
        'example' => [['cards' => ['As', 'Ah', 'Ac'], 'separator' => false]]
    ],
];
