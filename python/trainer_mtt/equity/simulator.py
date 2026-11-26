"""Cálculo de equity por Monte Carlo (no resuelve estrategias de equilibrio)."""

import random

from trainer_mtt.equity.cache import EquityCache
from trainer_mtt.poker.cards import Deck
from trainer_mtt.poker.evaluator import HandEvaluator
from trainer_mtt.poker.starting_hands import StartingHands


class EquitySimulator:
    """Monte Carlo para equity preflop heads-up y multiway, con caché."""

    def __init__(self, seed=None, equity_cache_size=1000):
        self.deck = Deck.full()
        self.hands = StartingHands()
        self.rng = random.Random(seed)
        self.cache = EquityCache(equity_cache_size)

    def simulate_equity(self, hero_hand_str, villain_range_strs, iterations=5000):
        """
        Simula equity de hero_hand vs villain_range

        Args:
            hero_hand_str: str - ej 'AKs'
            villain_range_strs: list - ej ['AA', 'KK', 'QQ', 'AKs', 'AKo']
            iterations: int - número de simulaciones

        Returns:
            dict - {equity: 0.52, wins: 2600, losses: 2400, ties: 0}
        """
        return self.simulate_multiway_equity(hero_hand_str, [villain_range_strs], iterations)

    def simulate_multiway_equity(self, hero_hand_str, opponent_ranges, iterations=5000):
        """Simula equity frente a varios rangos, repartiendo los empates del bote. Con caché."""
        if not isinstance(iterations, int) or iterations < 1:
            raise ValueError("iterations must be a positive integer")
        if not isinstance(opponent_ranges, list) or not opponent_ranges:
            raise ValueError("At least one opponent range is required")

        cache_key = self.cache.key(hero_hand_str, opponent_ranges)
        cached = self.cache.get(cache_key)
        if cached is not None:
            return cached

        player_ranges = [self.hands.expand([hero_hand_str])]
        for opponent_range in opponent_ranges:
            if not isinstance(opponent_range, list) or not opponent_range:
                raise ValueError("Every opponent must have a non-empty range")
            player_ranges.append(self.hands.expand(opponent_range))

        wins = losses = ties = 0
        equity = 0.0
        for _ in range(iterations):
            hole_cards = self._sample_hole_cards(player_ranges)
            used_cards = {str(card) for hand in hole_cards for card in hand}
            available = [card for card in self.deck if str(card) not in used_cards]
            board = self.rng.sample(available, 5)
            hero_share = self.evaluate_showdown(hole_cards, board)[0]
            equity += hero_share

            if hero_share == 1:
                wins += 1
            elif hero_share > 0:
                ties += 1
            else:
                losses += 1

        result = {
            'equity': round(equity / iterations, 4),
            'wins': wins,
            'losses': losses,
            'ties': ties,
            'iterations': iterations,
            'opponents': len(opponent_ranges),
        }
        self.cache.store(cache_key, result)

        return result

    def _sample_hole_cards(self, player_ranges):
        """Rejection-sample compatible combos so every valid tuple has equal weight."""
        for _ in range(2000):
            deal = [self.rng.choice(hand_range) for hand_range in player_ranges]
            cards = [str(card) for hand in deal for card in hand]
            if len(cards) == len(set(cards)):
                return deal
        raise ValueError("Could not deal non-overlapping hands from the supplied ranges")

    def evaluate_showdown(self, hole_cards, board):
        """Returns each player's fraction of the pot for a fixed showdown."""
        if len(board) != 5 or any(len(hand) != 2 for hand in hole_cards):
            raise ValueError("A showdown requires two hole cards per player and five board cards")
        all_cards = [card for hand in hole_cards for card in hand] + list(board)
        if len({str(card) for card in all_cards}) != len(all_cards):
            raise ValueError("A showdown cannot contain duplicate physical cards")

        evaluations = [HandEvaluator.best_rank(list(hand) + list(board)) for hand in hole_cards]
        best = max(evaluations)
        winners = [index for index, value in enumerate(evaluations) if value == best]
        share = 1.0 / len(winners)

        return [share if index in winners else 0.0 for index in range(len(hole_cards))]

    def get_cache_stats(self):
        return self.cache.stats()

    def clear_cache(self):
        self.cache.clear()
