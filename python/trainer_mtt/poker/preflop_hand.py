"""Herramienta de la mano preflop: qué mano es, cuánto vale entre las 169 y cuánta equity tiene contra el rango del rival.

El orden de las 169 manos sale de trainer_mtt/poker/data/preflop_order.json (equity contra una mano al azar, ver tools/build_preflop_order.py).
Todo lo que se cuenta aquí lo calcula la herramienta; el modelo solo lo lee.
"""

import json
from pathlib import Path

from trainer_mtt.poker.cards import Card
from trainer_mtt.poker.equity_estimator import EquityEstimator
from trainer_mtt.poker.hand_range import HandRange
from trainer_mtt.poker.starting_hands import RANK_ORDER, StartingHands

ORDER_FILE = Path(__file__).resolve().parent / 'data' / 'preflop_order.json'
TOTAL_COMBOS = 1326
RANK_NAMES = {'A': 'aces', 'K': 'kings', 'Q': 'queens', 'J': 'jacks', 'T': 'tens', '9': 'nines', '8': 'eights', '7': 'sevens',
              '6': 'sixes', '5': 'fives', '4': 'fours', '3': 'threes', '2': 'twos'}
BROADWAY = set('AKQJT')


class PreflopHand:
    _order = None   # [(mano, equity contra una mano al azar)] de la más fuerte a la más débil
    _top = None     # {mano: % de las manos posibles que son más fuertes (0 = la mejor)}

    SEED = 5   # la equity de un mismo estado sale siempre igual (se reinicia el azar en cada cálculo)

    def __init__(self):
        self.hands = StartingHands()
        self._load()

    @classmethod
    def _load(cls):
        if cls._order is not None:
            return
        data = json.loads(ORDER_FILE.read_text())['hands']
        cls._order = [(item['hand'], item['equity']) for item in data]
        hands = StartingHands()
        before, cls._top = 0.0, {}
        for hand, _ in cls._order:
            combos = len(hands.combinations(hand))
            cls._top[hand] = round(100 * (before + combos / 2) / TOTAL_COMBOS, 1)
            before += combos

    # ------------------------------------------------------------------ qué mano es

    @staticmethod
    def name(hole):
        """'AKs', 'AKo' o 'AA' a partir de las dos cartas (texto o Card)."""
        first, second = [card if isinstance(card, Card) else Card.from_string(card) for card in hole]
        if first.rank == second.rank:
            return first.rank + second.rank
        high, low = sorted((first, second), key=lambda card: -card.rank_value)

        return f"{high.rank}{low.rank}{'s' if high.suit == low.suit else 'o'}"

    def top_percent(self, hand):
        """Qué % de todas las manos iniciales es MÁS fuerte que esta (0 = AA, 100 = la peor), contando combinaciones."""
        return self._top[hand]

    def equity_against_random(self, hand):
        return dict(self._order)[hand]

    def kind(self, hand):
        """Tipo de mano en palabras: «pocket pair of eights», «suited broadway», «offsuit ace with a weak kicker»…"""
        high, low = hand[0], hand[1]
        if high == low:
            return f'pocket pair of {RANK_NAMES[high]}'
        suited = hand.endswith('s')
        suit = 'suited' if suited else 'offsuit'
        gap = RANK_ORDER.index(low) - RANK_ORDER.index(high) - 1
        if high in BROADWAY and low in BROADWAY:
            return f'{suit} broadway ({hand})'
        if high == 'A':
            return f"{suit} ace with a {'strong' if low in 'KQJT' else 'weak'} kicker ({hand})"
        if gap == 0:
            return f'{suit} connector ({hand})'
        if gap == 1:
            return f'{suit} one-gapper ({hand})'
        if high in BROADWAY:
            return f'{suit} broadway with a weak card ({hand})'

        return f"{suit} {'hand with a gap' if suited else 'junk hand'} ({hand})"

    def describe(self, hole):
        """Líneas del cuestionario: qué mano es, cuánto vale entre las 169 y qué tipo de mano es."""
        hand = self.name(hole)
        top = self.top_percent(hand)
        suit_note = ('suited hands make flushes and better draws, ' if hand.endswith('s') else '') + (
            'pairs win by hitting sets' if hand[0] == hand[1] else 'it plays best when it connects with the flop')

        return [
            f'Hand: {self.kind(hand)}.',
            f'Strength: it beats a random hand {round(100 * self.equity_against_random(hand))}% of the time; it is in the top {top}% of all starting hands.',
            f'Playability: {suit_note}.',
        ]

    def top_range(self, percent):
        """El rango de las manos más fuertes que suman este % de todas las manos (el que se supone a un rival del que solo se sabe cuánto juega)."""
        percent = max(0.0, min(100.0, float(percent)))
        weights, covered = {}, 0.0
        for hand, _ in self._order:
            combos = len(self.hands.combinations(hand))
            share = min(1.0, (percent / 100 * TOTAL_COMBOS - covered) / combos)
            if share <= 0:
                break
            weights[hand] = round(share, 3)
            covered += combos * share

        return HandRange(weights, self.hands)

    # ------------------------------------------------------------------ contra el rango del rival

    def equity_against(self, hole, rival_range, trials=2500):
        """Equity (%) de la mano contra un rango (HandRange / ComboRange) llegando hasta el final. None si no hay rango."""
        if not rival_range:
            return None
        cards = [Card.from_string(card) for card in hole]
        combos = rival_range.combos(dead=cards)
        if not combos:
            return None
        equity = EquityEstimator(seed=self.SEED).equity(cards, [], combos, trials)

        return None if equity is None else round(100 * equity, 1)
