"""Equity de una mano concreta contra un rango: probabilidad de ganar el bote al llegar al final, con las cartas que faltan.

Cuenta TODO lo que la mano puede llegar a ser: la mano hecha, los proyectos de color y escalera y las overcards que emparejan.
Es lo que hay que comparar con el precio de una apuesta (pot odds), no solo cuánto gana la mano hoy.
"""

import random

from trainer_mtt.poker.cards import Deck
from trainer_mtt.poker.fast_eval import FastEvaluator


class EquityEstimator:
    TRIALS = 3000

    def __init__(self, seed=11):
        self.rng = random.Random(seed)
        self.deck = Deck.full()

    def equity(self, hole, board, opponents, trials=None):
        """0 a 1: veces que gana (los empates cuentan la mitad) contra las manos de `opponents` = [((carta, carta), peso)].

        Se reparten al azar las cartas que faltan del board. None si no hay manos del rival con las que comparar.
        """
        if not opponents:
            return None
        trials = trials or self.TRIALS
        needed = 5 - len(board)
        hands = [cards for cards, _ in opponents]
        weights = [weight for _, weight in opponents]
        mine = list(hole) + list(board)
        known = {str(card) for card in mine}
        total = 0.0
        for rival in self.rng.choices(hands, weights=weights, k=trials):
            used = known | {str(card) for card in rival}
            runout = self.rng.sample([card for card in self.deck if str(card) not in used], needed) if needed > 0 else []
            ours = FastEvaluator.rank(mine + runout)
            theirs = FastEvaluator.rank(list(board) + runout + list(rival))
            total += 1.0 if ours > theirs else (0.5 if ours == theirs else 0.0)

        return total / trials
