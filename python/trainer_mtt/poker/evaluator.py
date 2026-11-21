"""Evaluación de manos de póker."""

from collections import defaultdict
from itertools import combinations


class HandEvaluator:
    """Evalúa la fuerza de manos de póker. Los rankings son tuplas: se comparan directamente."""

    @staticmethod
    def evaluate_hand(cards):
        """
        Evalúa 5 cartas. Devuelve ranking.
        Ranking: (clasificación, tiebreaker1, tiebreaker2, ...)
        - 8: Royal Flush
        - 7: Straight Flush
        - 6: Four of a Kind
        - 5: Full House
        - 4: Flush
        - 3: Straight
        - 2: Three of a Kind
        - 1: Two Pair
        - 0: One Pair
        - -1: High Card
        """
        if len(cards) != 5:
            raise ValueError("A hand must contain exactly five cards")
        if len({str(card) for card in cards}) != 5:
            raise ValueError("A hand cannot contain duplicate cards")

        cards = sorted(cards, key=lambda c: c.rank_value, reverse=True)

        # Verificar flush
        is_flush = len(set(c.suit_value for c in cards)) == 1

        # Verificar straight
        ranks = [c.rank_value for c in cards]
        is_straight = (ranks[0] - ranks[4] == 4 and len(set(ranks)) == 5)

        # Wheel (A-2-3-4-5)
        if set(ranks) == {14, 5, 4, 3, 2}:
            is_straight = True
            ranks = [5, 4, 3, 2, 1]  # Ace is low

        # Contar valores
        rank_counts = defaultdict(int)
        for c in cards:
            rank_counts[c.rank_value] += 1

        counts = sorted(rank_counts.values(), reverse=True)
        unique_ranks = sorted(rank_counts.keys(), key=lambda r: (rank_counts[r], r), reverse=True)

        # Clasificar
        if is_straight and is_flush:
            if ranks[0] == 14 and ranks[1] == 13:  # Royal Flush
                return (8, ranks[0])
            return (7, ranks[0])

        if counts == [4, 1]:
            return (6, unique_ranks[0], unique_ranks[1])

        if counts == [3, 2]:
            return (5, unique_ranks[0], unique_ranks[1])

        if is_flush:
            return (4, *ranks)

        if is_straight:
            return (3, ranks[0])

        if counts == [3, 1, 1]:
            return (2, unique_ranks[0], *unique_ranks[1:])

        if counts == [2, 2, 1]:
            pair_ranks = sorted([unique_ranks[0], unique_ranks[1]], reverse=True)
            return (1, pair_ranks[0], pair_ranks[1], unique_ranks[2])

        if counts == [2, 1, 1, 1]:
            return (0, unique_ranks[0], *unique_ranks[1:])

        return (-1, *ranks)

    @classmethod
    def best_rank(cls, cards):
        """Ranking de la mejor mano de 5 cartas entre las dadas (5, 6 o 7)."""
        cards = list(cards)
        if len(cards) < 5:
            raise ValueError("At least five cards are needed")

        return max(cls.evaluate_hand(list(five)) for five in combinations(cards, 5))
