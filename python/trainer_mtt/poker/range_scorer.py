"""Ordena las manos de un rango de la más fuerte a la más débil sobre un board concreto y reparte el rango por acciones.

La fuerza de una mano es su EQUITY contra el rango del rival: la probabilidad de ganar al llegar al final contando todo lo que la mano puede
llegar a ser (la mano hecha, los proyectos de color y escalera, las overcards que emparejan). Se calcula sobre unos cuantos tableros completos
repartidos al azar, los MISMOS para todas las manos del rango, así que el orden entre ellas es estable aunque cada valor tenga algo de ruido.
"""

import random
from bisect import bisect_left, bisect_right
from collections import Counter
from itertools import accumulate

from trainer_mtt.poker.cards import Deck
from trainer_mtt.poker.evaluator import HandEvaluator
from trainer_mtt.poker.fast_eval import FastEvaluator


class RangeScorer:
    RUNOUTS = 60       # tableros completos de simulación (en el river solo hay uno: el real)
    MAX_OUTS = 15

    def __init__(self, board, seed=7):
        self.board = list(board)
        needed = 5 - len(self.board)
        rng = random.Random(seed)
        free = Deck.without(self.board)
        boards = [self.board + rng.sample(free, needed) for _ in range(self.RUNOUTS)] if needed > 0 else [self.board]
        self._boards = [
            ([c.rank_value for c in b], [c.suit_value for c in b], {self._cid(c) for c in b}) for b in boards
        ]

    @staticmethod
    def _cid(card):
        return card.rank_value * 4 + card.suit_value

    def table(self, rival_combos):
        """Para cada tablero de simulación, las manos del rival ordenadas por fuerza (con su peso acumulado), para comparar rápido."""
        prepared = [
            (c1.rank_value, c1.suit_value, self._cid(c1), c2.rank_value, c2.suit_value, self._cid(c2), weight)
            for (c1, c2), weight in rival_combos
        ]
        tables = []
        for ranks, suits, ids in self._boards:
            entries = []
            for r1, s1, i1, r2, s2, i2, weight in prepared:
                if i1 not in ids and i2 not in ids:
                    entries.append((FastEvaluator.rank_ints(ranks + [r1, r2], suits + [s1, s2]), weight))
            entries.sort(key=lambda entry: entry[0])
            tables.append(([entry[0] for entry in entries], [0.0] + list(accumulate(entry[1] for entry in entries))))

        return tables

    def score(self, hole, tables):
        """Equity (0 a 1) de una mano contra el rango del rival representado por `tables`."""
        hole_ranks = [card.rank_value for card in hole]
        hole_suits = [card.suit_value for card in hole]
        hole_ids = {self._cid(card) for card in hole}
        total, boards = 0.0, 0
        for (ranks, suits, ids), (rival_ranks, cumulative) in zip(self._boards, tables):
            if ids & hole_ids or cumulative[-1] <= 0:
                continue
            mine = FastEvaluator.rank_ints(ranks + hole_ranks, suits + hole_suits)
            below = cumulative[bisect_left(rival_ranks, mine)]
            tied = cumulative[bisect_right(rival_ranks, mine)] - below
            total += (below + tied / 2) / cumulative[-1]
            boards += 1

        return total / boards if boards else 0.0

    def scored(self, my_combos, tables):
        """[(fuerza, peso)] de cada combo del rango propio, de la más fuerte a la más débil."""
        return sorted(((self.score(cards, tables), weight) for cards, weight in my_combos), key=lambda item: -item[0])

    def draw_odds(self, hole):
        """Probabilidad de que los proyectos de esta mano mejoren con las cartas que vienen (0 si no tiene proyectos)."""
        return self._improve_probability(self._outs(hole, HandEvaluator.best_rank(self.board + list(hole))))

    def _improve_probability(self, outs):
        """Probabilidad de mejorar con alguna de las cartas que vienen: dos en el flop, una en el turn, ninguna en el river."""
        unseen = 52 - len(self.board) - 2
        if len(self.board) == 3:
            return 1 - (1 - outs / unseen) * (1 - outs / (unseen - 1))
        if len(self.board) == 4:
            return outs / unseen

        return 0.0

    def _outs(self, hole, mine):
        """Cartas que completan un color o una escalera (cálculo estructural rápido; sin contar cartas muertas de los rivales)."""
        if mine[0] >= 3:
            return 0
        cards = list(hole) + self.board
        outs = 0
        for suit, count in Counter(card.suit for card in cards).items():
            if count == 4 and any(card.suit == suit for card in hole):
                outs += 9

        ranks = {card.rank_value for card in cards}
        hole_ranks = {card.rank_value for card in hole}
        if 14 in ranks:
            ranks.add(1)
        if 14 in hole_ranks:
            hole_ranks.add(1)
        completing = set()
        for rank in range(2, 15):
            if rank in ranks:
                continue
            with_card = ranks | {rank} | ({1} if rank == 14 else set())
            for low in range(1, 11):
                window = set(range(low, low + 5))
                if rank in window and window <= with_card and window & hole_ranks:
                    completing.add(rank)
                    break

        return min(self.MAX_OUTS, outs + 4 * len(completing))


class RangeSplit:
    """El rango ordenado por fuerza y repartido en zonas de acción (de la más fuerte a la más débil)."""

    def __init__(self, scored):
        self.scored = scored  # [(fuerza, peso)] de mayor a menor fuerza
        self.total = sum(weight for _, weight in scored)

    def top_fraction(self, score):
        """Qué fracción del rango (0 a 1) es MÁS fuerte que esta mano: 0 = la mejor del rango, 1 = la peor."""
        if self.total <= 0:
            return 1.0
        stronger = sum(weight for s, weight in self.scored if s > score + 1e-9)
        equal = sum(weight for s, weight in self.scored if abs(s - score) <= 1e-9)

        return (stronger + equal / 2) / self.total

    @staticmethod
    def locate(top_fraction, zones):
        """(zona, (zona más fuerte, puntos hasta ella) o None, (zona más débil, puntos hasta ella) o None) de una mano.

        `zones` = [(nombre, porcentaje)] de la acción más fuerte a la más débil; los porcentajes suman 100. Los puntos son
        puntos del rango: cuántos faltan para cruzar el límite con esa zona vecina.
        """
        position = top_fraction * 100
        start = 0.0
        for index, (name, percent) in enumerate(zones):
            end = start + percent
            if position <= end or index == len(zones) - 1:
                stronger = (zones[index - 1][0], round(max(0.0, position - start), 1)) if index > 0 else None
                weaker = (zones[index + 1][0], round(max(0.0, end - position), 1)) if index < len(zones) - 1 else None

                return name, stronger, weaker
            start = end

        return zones[-1][0], None, None

    @classmethod
    def zone(cls, top_fraction, zones):
        """(nombre de la zona, puntos hasta el límite más cercano) para una mano en esa posición del rango."""
        name, stronger, weaker = cls.locate(top_fraction, zones)
        distances = [points for neighbour in (stronger, weaker) if neighbour for points in [neighbour[1]]]

        return name, min(distances) if distances else 100.0
