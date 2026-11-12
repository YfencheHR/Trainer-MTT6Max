<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentHand;

class HandEngine
{
    private Tournament $tournament;
    private array $playerStacks = [];
    private int $buttonSeat = 1;
    private array $deck = [];
    private array $discarded = [];
    private array $boardCards = [];

    public function __construct(Tournament $tournament)
    {
        $this->tournament = $tournament;
        $this->initializeStacks();
    }

    /**
     * Crea una nueva mano siguiendo el flujo del diagrama:
     * 1. ¿Es torneo nuevo? → Crear mano aleatoria
     * 2. Si no → Crear mano con flop establecido
     */
    public function createNewHand(): TournamentHand
    {
        $isNewTournament = $this->tournament->hands()->count() === 0;

        if ($isNewTournament) {
            return $this->createRandomHand();
        }

        return $this->createFlopEstablishedHand();
    }

    /**
     * Crea una mano aleatoria:
     * 1. Se barajan las cartas de forma aleatoria
     * 2. Se hacen 3 cartas se cambian de orden y se mezclan X 2
     * 3. Se distribuyen cartas hole
     * 4. Se genera el board (flop, turn, river)
     */
    private function createRandomHand(): TournamentHand
    {
        $config = $this->tournament->config;
        $blindConfig = $this->getBlindConfig();
        $numPlayers = count($this->playerStacks);

        // Crear mazo y mezclar según el diagrama
        $this->createAndShuffleDeck();

        // Crear registro de la mano
        $hand = TournamentHand::create([
            'tournament_id' => $this->tournament->id,
            'hand_number' => $this->getNextHandNumber(),
            'hand_started_at' => now(),
            'level' => $blindConfig['level'] ?? 1,
            'blind_level_config' => $blindConfig,
            'button_seat' => $this->buttonSeat,
            'small_blind_seat' => $this->getSmallBlindSeat($numPlayers),
            'big_blind_seat' => $this->getBigBlindSeat($numPlayers),
            'hero_seat' => 1,
            'stacks_before' => $this->playerStacks,
            'status' => 'preflop',
        ]);

        // Distribuir cartas hole de 1 en 1
        $this->dealHoleCards($hand, $numPlayers);

        // Generar board (flop, turn, river) pero sin mostrar aún
        $this->generateBoard($hand);

        return $hand;
    }

    /**
     * Crea mano con flop establecido:
     * Es mano flop siempre el mismo flop
     */
    private function createFlopEstablishedHand(): TournamentHand
    {
        $config = $this->tournament->config;
        $blindConfig = $this->getBlindConfig();
        $numPlayers = count($this->playerStacks);

        $this->createAndShuffleDeck();

        $hand = TournamentHand::create([
            'tournament_id' => $this->tournament->id,
            'hand_number' => $this->getNextHandNumber(),
            'hand_started_at' => now(),
            'level' => $blindConfig['level'] ?? 1,
            'blind_level_config' => $blindConfig,
            'button_seat' => $this->buttonSeat,
            'small_blind_seat' => $this->getSmallBlindSeat($numPlayers),
            'big_blind_seat' => $this->getBigBlindSeat($numPlayers),
            'hero_seat' => 1,
            'stacks_before' => $this->playerStacks,
            'status' => 'flop',
        ]);

        // Distribuir cartas hole
        $this->dealHoleCards($hand, $numPlayers);

        // Ya es flop establecido, mostrar el flop
        $this->revealFlop($hand);

        return $hand;
    }

    /**
     * Crea y mezcla el mazo:
     * 1. Se barajan las cartas de forma aleatoria
     * 2. Se hacen 3 cartas se cambian de orden y se mezclan las cartas X 2
     */
    private function createAndShuffleDeck(): void
    {
        $this->deck = $this->createDeck();

        // Primera mezcla
        shuffle($this->deck);

        // Cambiar orden de 3 cartas y mezclar nuevamente
        $indices = array_rand($this->deck, 3);
        if (!is_array($indices)) {
            $indices = [$indices];
        }

        $selectedCards = [];
        foreach ($indices as $idx) {
            $selectedCards[] = $this->deck[$idx];
        }
        shuffle($selectedCards);

        $idx = 0;
        foreach ($indices as $i) {
            $this->deck[$i] = $selectedCards[$idx++];
        }

        // Segunda mezcla
        shuffle($this->deck);
    }

    /**
     * Distribuye cartas hole de 1 en 1:
     * - Se reparte de mazo de manera aleatoria
     * - Se parte del SB (small blind)
     * - Se reparten dos cartas en 1 en 1
     * - Si solo hay 2 jugadores, se reparte primero a la BB
     */
    private function dealHoleCards(TournamentHand $hand, int $numPlayers): void
    {
        $holeCards = [];
        $sbSeat = $this->getSmallBlindSeat($numPlayers);
        $bbSeat = $this->getBigBlindSeat($numPlayers);

        // Si hay 2 jugadores, empezar desde BB
        $startSeat = ($numPlayers === 2) ? $bbSeat : $sbSeat;

        // Repartir 2 cartas a cada jugador de 1 en 1
        for ($card = 0; $card < 2; $card++) {
            $currentSeat = $startSeat;
            for ($i = 0; $i < $numPlayers; $i++) {
                if (!isset($holeCards["seat_$currentSeat"])) {
                    $holeCards["seat_$currentSeat"] = [];
                }

                // Tomar una carta del mazo
                if (!empty($this->deck)) {
                    $holeCards["seat_$currentSeat"][] = array_shift($this->deck);
                }

                // Siguiente posición
                $currentSeat = ($currentSeat % $numPlayers) + 1;
            }
        }

        // Guardar cartas hero
        $heroCards = $holeCards["seat_1"] ?? [];

        // Guardar como histórico
        $hand->update([
            'hero_cards' => $heroCards,
            'actions' => [
                'hole_cards' => $holeCards,
                'card_distribution' => 'Cartas distribuidas de 1 en 1 comenzando desde ' .
                    ($numPlayers === 2 ? 'BB' : 'SB'),
            ],
        ]);
    }

    /**
     * Genera el board completo (flop, turn, river):
     * 1. Descarta 1 carta antes de cada calle
     * 2. Reparte 3 cartas para flop, 1 para turn, 1 para river
     * Las cartas se generan pero no se muestran hasta que se juegue
     */
    private function generateBoard(TournamentHand $hand): void
    {
        $actions = $hand->actions ?? [];

        // FLOP: Descartar 1 carta, repartir 3
        $this->discarded[] = array_shift($this->deck);
        $flop = [
            array_shift($this->deck),
            array_shift($this->deck),
            array_shift($this->deck),
        ];

        // TURN: Descartar 1 carta, repartir 1
        $this->discarded[] = array_shift($this->deck);
        $turn = array_shift($this->deck);

        // RIVER: Descartar 1 carta, repartir 1
        $this->discarded[] = array_shift($this->deck);
        $river = array_shift($this->deck);

        // Guardar como histórico pero NOT mostrar aún
        $actions['board_generation'] = [
            'flop' => $flop,
            'turn' => $turn,
            'river' => $river,
            'discarded_cards' => $this->discarded,
            'remaining_deck' => $this->deck,
        ];

        $hand->update([
            'actions' => $actions,
        ]);

        // Guardar para revelar después
        $this->boardCards = array_merge($flop, [$turn, $river]);
    }

    /**
     * Revela el flop (llamado cuando ya estamos en flop)
     */
    private function revealFlop(TournamentHand $hand): void
    {
        $this->generateBoard($hand);

        $actions = $hand->actions ?? [];
        $boardGen = $actions['board_generation'] ?? [];
        $flop = $boardGen['flop'] ?? [];

        $hand->update([
            'community_cards' => $flop,
            'status' => 'flop',
        ]);
    }

    /**
     * Crea un mazo estándar de 52 cartas
     */
    private function createDeck(): array
    {
        $suits = ['s', 'h', 'd', 'c']; // spades, hearts, diamonds, clubs
        $ranks = ['2', '3', '4', '5', '6', '7', '8', '9', 'T', 'J', 'Q', 'K', 'A'];

        $deck = [];
        foreach ($suits as $suit) {
            foreach ($ranks as $rank) {
                $deck[] = $rank . $suit;
            }
        }

        return $deck;
    }

    /**
     * Obtiene configuración de ciegos basada en manos jugadas
     * Ahora usa hands_per_level en lugar de duration
     */
    private function getBlindConfig(): array
    {
        $config = $this->tournament->config;
        $speed = $config['blind_structure']['speed'] ?? 'tipica';
        $handCount = $this->tournament->hands()->count();
        $handsPerLevel = $config['blind_structure']['hands_per_level']
            ?? data_get($config, 'blind_structure.levels.0.hands_per_level')
            ?? config("blind-levels.{$speed}.hands_per_level", 10);
        $baseBigBlind = (int) ($config['basic_settings']['big_blind'] ?? 10);
        $antesByLevel = data_get($config, 'blind_structure.antes', []);

        return app(\App\Services\Tournament\BlindStructureService::class)
            ->forRound($baseBigBlind, $speed, $handCount + 1, (int) $handsPerLevel, $antesByLevel);
    }

    /**
     * Obtiene posición del Small Blind
     */
    private function getSmallBlindSeat(int $numPlayers): int
    {
        if ($numPlayers < 2) {
            return 1;
        }

        $sbSeat = ($this->buttonSeat % $numPlayers) + 1;
        return ($sbSeat === 0 || $sbSeat > $numPlayers) ? 1 : $sbSeat;
    }

    /**
     * Obtiene posición del Big Blind
     */
    private function getBigBlindSeat(int $numPlayers): int
    {
        if ($numPlayers < 2) {
            return 1;
        }

        $sbSeat = $this->getSmallBlindSeat($numPlayers);
        $bbSeat = ($sbSeat % $numPlayers) + 1;
        return ($bbSeat === 0 || $bbSeat > $numPlayers) ? 1 : $bbSeat;
    }

    /**
     * Obtiene el número de la siguiente mano
     */
    private function getNextHandNumber(): int
    {
        $lastHand = $this->tournament->hands()->latest('hand_number')->first();
        return ($lastHand ? $lastHand->hand_number : 0) + 1;
    }

    /**
     * Inicializa los stacks de los jugadores
     */
    private function initializeStacks(): void
    {
        $config = $this->tournament->config;
        $players = $config['players'] ?? [];

        foreach ($players as $player) {
            $seat = $player['seat'] ?? null;
            $stack = $player['starting_stack'] ?? $config['basic_settings']['starting_stack'] ?? 1000;

            if ($seat) {
                $this->playerStacks[$seat] = $stack;
            }
        }
    }

    /**
     * Mueve el botón a la siguiente posición
     */
    public function updateButtonPosition(): void
    {
        $numPlayers = count($this->playerStacks);
        if ($numPlayers > 0) {
            $this->buttonSeat = ($this->buttonSeat % $numPlayers) + 1;
        }
    }

    /**
     * Retorna la instancia para métodos encadenados
     */
    public function getHandEngine(): self
    {
        return $this;
    }
}
