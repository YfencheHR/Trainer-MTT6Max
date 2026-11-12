"""Monte Carlo equity calculations; this module does not solve strategic equilibrium."""

from itertools import combinations
from collections import defaultdict
import random


class Card:
    """Representa una carta de poker"""
    RANKS = {'A': 14, 'K': 13, 'Q': 12, 'J': 11, 'T': 10, '9': 9, '8': 8, '7': 7, '6': 6, '5': 5, '4': 4, '3': 3, '2': 2}
    SUITS = {'s': 0, 'h': 1, 'd': 2, 'c': 3}

    def __init__(self, rank, suit):
        if rank not in self.RANKS:
            raise ValueError(f"Invalid card rank: {rank}")
        if suit not in self.SUITS:
            raise ValueError(f"Invalid card suit: {suit}")
        self.rank = rank
        self.suit = suit
        self.rank_value = self.RANKS[rank]
        self.suit_value = self.SUITS[suit]

    @staticmethod
    def from_string(card_str):
        """Convierte string como 'As' a Card"""
        if not isinstance(card_str, str) or len(card_str) != 2:
            raise ValueError(f"Invalid card: {card_str}")
        return Card(card_str[0], card_str[1])

    def __str__(self):
        return f"{self.rank}{self.suit}"

    def __repr__(self):
        return str(self)


class HandEvaluator:
    """Evalúa fuerza de manos de poker"""

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


class EquitySimulator:
    """Monte Carlo evaluator for heads-up and multiway preflop equity."""

    def __init__(self, seed=None):
        self.deck = self._create_deck()
        self.hand_names = self._generate_hand_names()
        self.rng = random.Random(seed)
        self._hand_combinations_cache = {}

    def _create_deck(self):
        """Crea baraja completa"""
        ranks = ['A', 'K', 'Q', 'J', 'T', '9', '8', '7', '6', '5', '4', '3', '2']
        suits = ['s', 'h', 'd', 'c']
        return [Card(r, s) for r in ranks for s in suits]

    def _generate_hand_names(self):
        """Genera nombres de todas las manos (169)"""
        ranks = ['A', 'K', 'Q', 'J', 'T', '9', '8', '7', '6', '5', '4', '3', '2']
        hands = []

        # Pares
        for r in ranks:
            hands.append(f"{r}{r}")

        # No pares (AK, AQ, etc.)
        for i, r1 in enumerate(ranks):
            for r2 in ranks[i+1:]:
                hands.append(f"{r1}{r2}s")  # Suited
                hands.append(f"{r1}{r2}o")  # Offsuit

        return hands

    def _parse_hand_string(self, hand_str):
        """Convierte una mano canónica, como 'AKs', en un combo físico."""
        return list(self._hand_combinations(hand_str)[0])

    def _hand_combinations(self, hand_str):
        """Expande una mano canónica en todos sus combos físicos."""
        if hand_str in self._hand_combinations_cache:
            return self._hand_combinations_cache[hand_str]
        if hand_str not in self.hand_names:
            raise ValueError(f"Invalid starting hand: {hand_str}")

        suits = list(Card.SUITS)
        if len(hand_str) == 2:
            combos = [
                (Card(hand_str[0], suit_a), Card(hand_str[1], suit_b))
                for suit_a, suit_b in combinations(suits, 2)
            ]
        elif hand_str[2] == 's':
            combos = [(Card(hand_str[0], suit), Card(hand_str[1], suit)) for suit in suits]
        else:
            combos = [
                (Card(hand_str[0], suit_a), Card(hand_str[1], suit_b))
                for suit_a in suits
                for suit_b in suits
                if suit_a != suit_b
            ]

        self._hand_combinations_cache[hand_str] = combos
        return combos

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
        return self.simulate_multiway_equity(
            hero_hand_str,
            [villain_range_strs],
            iterations,
        )

    def simulate_multiway_equity(self, hero_hand_str, opponent_ranges, iterations=5000):
        """Simula equity frente a varios rangos, repartiendo los empates del bote."""
        if not isinstance(iterations, int) or iterations < 1:
            raise ValueError("iterations must be a positive integer")
        if not isinstance(opponent_ranges, list) or not opponent_ranges:
            raise ValueError("At least one opponent range is required")

        player_ranges = [self._expand_range([hero_hand_str])]
        for opponent_range in opponent_ranges:
            if not isinstance(opponent_range, list) or not opponent_range:
                raise ValueError("Every opponent must have a non-empty range")
            player_ranges.append(self._expand_range(opponent_range))

        wins = 0
        losses = 0
        ties = 0
        equity = 0.0

        for _ in range(iterations):
            hole_cards = self._sample_hole_cards(player_ranges)
            used_cards = {str(card) for hand in hole_cards for card in hand}
            available = [card for card in self.deck if str(card) not in used_cards]
            board = self.rng.sample(available, 5)
            shares = self.evaluate_showdown(hole_cards, board)
            hero_share = shares[0]
            equity += hero_share

            if hero_share == 1:
                wins += 1
            elif hero_share > 0:
                ties += 1
            else:
                losses += 1

        return {
            'equity': round(equity / iterations, 4),
            'wins': wins,
            'losses': losses,
            'ties': ties,
            'iterations': iterations,
            'opponents': len(opponent_ranges),
        }

    def _expand_range(self, hand_range):
        combos = []
        for hand_name in hand_range:
            combos.extend(self._hand_combinations(hand_name))
        if not combos:
            raise ValueError("A hand range must contain at least one valid hand")
        return combos

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

        evaluations = [
            HandEvaluator.evaluate_hand(self._best_five_cards(list(hand) + list(board)))
            for hand in hole_cards
        ]
        best = max(evaluations)
        winners = [index for index, value in enumerate(evaluations) if value == best]
        share = 1.0 / len(winners)
        return [share if index in winners else 0.0 for index in range(len(hole_cards))]

    def _best_five_cards(self, seven_cards):
        """Encuentra las 5 mejores cartas de 7"""
        best_hand = None
        best_eval = None

        for five_cards in combinations(seven_cards, 5):
            eval_result = HandEvaluator.evaluate_hand(list(five_cards))
            if best_eval is None or eval_result > best_eval:
                best_eval = eval_result
                best_hand = five_cards

        return list(best_hand)

    def calculate_ev(self, equity, pot_bb, stack_bb, action_cost_bb):
        """
        Calcula EV de una acción

        Args:
            equity: float - equity (0-1)
            pot_bb: float - bote actual en bb
            stack_bb: float - stack remaining en bb
            action_cost_bb: float - costo de la acción en bb

        Returns:
            float - EV en bb
        """
        # Simplificado: expected value = (equity * pot) - action_cost
        # Versión básica, puede refinarse
        return equity * pot_bb - action_cost_bb

    def generate_attack_table(self, stack_depth_bb, defense_range_percent=20):
        """
        Genera una tabla heurística de equity de ataque para un stack depth.

        Args:
            stack_depth_bb: int - profundidad en bb
            defense_range_percent: int - % usado para construir el rango rival

        Returns:
            dict - {hand: {equity, ev, include}}

        This percentage-threshold table is not an equilibrium strategy.
        """
        # Generar rango de defensa base (top X%)
        num_hands_defending = max(1, int(169 * defense_range_percent / 100))
        defense_range = self.hand_names[:num_hands_defending]  # Primeras N manos (más fuertes)

        result = {}

        for hand in self.hand_names:
            sim = self.simulate_equity(hand, defense_range, iterations=2000)
            equity = sim['equity']

            # Heuristic cutoff only; this does not model a betting game.
            include = equity > 0.5

            result[hand] = {
                'equity': equity,
                'ev': equity * 2.0 - 1.0,  # Aproximación simple
                'include': include,
                'iterations': sim['iterations']
            }

        return result

    def generate_defense_table(self, stack_depth_bb, attack_range_percent=15):
        """
        Genera una tabla heurística de equity defensiva para un stack depth.

        This percentage-threshold table is not an equilibrium strategy.
        """
        num_hands_attacking = max(1, int(169 * attack_range_percent / 100))
        attack_range = self.hand_names[:num_hands_attacking]

        result = {}

        for hand in self.hand_names:
            # Calcular equity contra cada mano del rango de ataque
            total_equity = 0
            for attack_hand in attack_range:
                sim = self.simulate_equity(hand, [attack_hand], iterations=1000)
                total_equity += sim['equity']

            avg_equity = total_equity / len(attack_range)
            include = avg_equity > 0.45  # Threshold más bajo para defensa

            result[hand] = {
                'equity': avg_equity,
                'ev': avg_equity * 2.0 - 1.0,
                'include': include,
                'iterations': 1000
            }

        return result
