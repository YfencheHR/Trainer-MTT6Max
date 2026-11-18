<?php

return [
    'title' => 'Simulate a tournament and analyse how the bots played',
    'intro' => 'Plays a whole bots-only tournament (headless, all tables at once) with the imported profiles spread evenly, then analyses their decisions: how often each profile folds, bets or raises compared with its PokerTracker stats, plus leaks and the worst decisions. Use it after an import to check the bots play as expected.',
    'players' => 'Players (12 to 600)',
    'speed' => 'Blind speed',
    'speed_tipica' => 'Typical',
    'speed_rapida' => 'Fast',
    'speed_muy_rapida' => 'Very fast',
    'button' => 'Simulate and analyse',
    'working' => 'Simulating…',
    'replaces' => 'Previous simulated tournaments are deleted (tournaments you create are untouched). With 600 players it can take a while; you can leave the page open and watch the progress.',
    'already_running' => 'A simulation is already running. Wait for it to finish.',
    'done' => '✔ Simulation and analysis finished',
    'failed' => '✗ The simulation failed (see the log)',
    'progress' => [
        'phase' => 'Step',
        'preparando' => 'Preparing',
        'simulando' => 'Playing the tournament',
        'analizando' => 'Analysing the decisions',
        'waiting' => 'Creating the tournament and seating the players…',
        'round' => 'Round',
        'players' => 'players',
        'tables' => 'tables',
    ],
    'how_to_read' => 'Reading the analysis: "frequencies" compares what the bots did with their profile stats (large gaps are flagged); "leaks" are single decisions with poor equity; "worst decisions" are those that lost the most EV.',
];
