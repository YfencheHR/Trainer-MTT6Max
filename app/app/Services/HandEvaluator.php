<?php

namespace App\Services;

use App\Models\TournamentHand;

class HandEvaluator
{
    private TournamentHand $hand;
    private array $playerHoleCards = [];
    private array $communityCards = [];

    private const RANKS = ['2', '3', '4', '5', '6', '7', '8', '9', 'T', 'J', 'Q', 'K', 'A'];
    private const HAND_RANKINGS = [
        'high_card' => 1,
        'pair' => 2,
        'two_pair' => 3,
        'three_of_a_kind' => 4,
        'straight' => 5,
        'flush' => 6,
        'full_house' => 7,
        'four_of_a_kind' => 8,
        'straight_flush' => 9,
        'royal_flush' => 10,
    ];

    public function __construct(TournamentHand $hand)
    {
        $this->hand = $hand;
        $this->communityCards = $hand->community_cards ?? [];
    }

    public function evaluateHand(): array
    {
        $playersInHand = $this->hand->players_in_hand ?? [];
        $results = [];

        foreach ($playersInHand as $seat) {
            $holeCards = $this->getPlayerHoleCards($seat);
            $bestHand = $this->findBestHand($holeCards);
            $results[$seat] = [
                'seat' => $seat,
                'hand_type' => $bestHand['type'],
                'hand_rank' => self::HAND_RANKINGS[$bestHand['type']] ?? 0,
                'kickers' => $bestHand['kickers'] ?? [],
                'cards_used' => $bestHand['cards'] ?? [],
            ];
        }

        // Ordenar por ranking
        usort($results, function ($a, $b) {
            return $b['hand_rank'] <=> $a['hand_rank'];
        });

        return $results;
    }

    public function determineWinner(): array
    {
        $evaluation = $this->evaluateHand();

        if (empty($evaluation)) {
            return ['winner' => null, 'result' => 'no_winner'];
        }

        $topHand = $evaluation[0];
        $winner = [
            'seat' => $topHand['seat'],
            'hand_type' => $topHand['hand_type'],
            'hand_rank' => $topHand['hand_rank'],
        ];

        // Verificar split pot (mismo ranking)
        if (count($evaluation) > 1 && $evaluation[1]['hand_rank'] === $topHand['hand_rank']) {
            $winner['result'] = 'split';
            $winner['split_seats'] = array_map(fn($h) => $h['seat'], $evaluation);
        } else {
            $winner['result'] = 'win';
        }

        return $winner;
    }

    private function findBestHand(array $holeCards): array
    {
        $allCards = array_merge($holeCards, $this->communityCards);

        // Buscar las mejores 5 cartas de las 7 disponibles
        $bestHand = $this->evaluateAllCombinations($allCards);

        return $bestHand;
    }

    private function evaluateAllCombinations(array $cards): array
    {
        $best = ['type' => 'high_card', 'rank' => 1, 'kickers' => []];

        // Evaluar cada combinación posible de 5 cartas
        // Para simplificar, evaluaremos patrones comunes

        $pairs = $this->findPairs($cards);
        if (!empty($pairs)) {
            $best = ['type' => 'pair', 'rank' => 2, 'kickers' => $pairs['pairs']];
        }

        $flush = $this->findFlush($cards);
        if ($flush) {
            $best = ['type' => 'flush', 'rank' => 6, 'cards' => $flush];
        }

        $straight = $this->findStraight($cards);
        if ($straight) {
            $best = ['type' => 'straight', 'rank' => 5, 'cards' => $straight];
        }

        return $best;
    }

    private function findPairs(array $cards): array
    {
        $pairs = [];
        $ranks = $this->extractRanks($cards);

        foreach (array_count_values($ranks) as $rank => $count) {
            if ($count >= 2) {
                $pairs[] = $rank;
            }
        }

        return ['pairs' => $pairs];
    }

    private function findFlush(array $cards): ?array
    {
        $suits = $this->extractSuits($cards);

        foreach (array_count_values($suits) as $suit => $count) {
            if ($count >= 5) {
                return array_slice($cards, 0, 5);
            }
        }

        return null;
    }

    private function findStraight(array $cards): ?array
    {
        $ranks = $this->extractRanks($cards);
        $rankValues = array_map(fn($r) => array_search($r, self::RANKS), $ranks);
        sort($rankValues);

        for ($i = 0; $i < count($rankValues) - 4; $i++) {
            if ($rankValues[$i + 4] - $rankValues[$i] === 4) {
                return array_slice($cards, $i, 5);
            }
        }

        return null;
    }

    private function extractRanks(array $cards): array
    {
        return array_map(fn($card) => substr($card, 0, -1), $cards);
    }

    private function extractSuits(array $cards): array
    {
        return array_map(fn($card) => substr($card, -1), $cards);
    }

    private function getPlayerHoleCards(int $seat): array
    {
        // Buscar en hero_cards o en la mano
        if ($seat === $this->hand->hero_seat) {
            return (array) $this->hand->hero_cards;
        }

        // Para bots, generar cartas aleatorias (simplificado)
        return $this->generateRandomCards();
    }

    private function generateRandomCards(): array
    {
        $deck = $this->createDeck();
        return [array_shift($deck), array_shift($deck)];
    }

    private function createDeck(): array
    {
        $suits = ['s', 'h', 'd', 'c'];
        $deck = [];

        foreach ($suits as $suit) {
            foreach (self::RANKS as $rank) {
                $deck[] = $rank . $suit;
            }
        }

        shuffle($deck);
        return $deck;
    }
}
