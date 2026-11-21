"""De qué está hecho un rango sobre un board: valor, proyectos y puro farol.

Un rival que apuesta no tiene solo manos hechas: parte de su apuesta son proyectos (semifaroles: color, escalera) y parte puro farol sin
nada. Cada familia pide una respuesta distinta: contra valor se foldea la pareja débil, contra proyectos una pareja o una mano con equity
paga, y contra un puro farol se paga con casi todo. Foldear una mano con equity contra un rango lleno de proyectos es un error.
"""

from collections import OrderedDict

from trainer_mtt.poker.evaluator import HandEvaluator
from trainer_mtt.poker.range_scorer import RangeScorer

TWO_PAIR_OR_BETTER = 'two pair or better'
TOP_PAIR = 'top pair or overpair'
WEAKER_PAIR = 'weaker pair'
STRONG_DRAW = 'strong draw (flush draw or open-ended)'
WEAK_DRAW = 'weak draw (gutshot)'
AIR = 'pure bluff (nothing)'

FAMILY = {TWO_PAIR_OR_BETTER: 'value', TOP_PAIR: 'value', WEAKER_PAIR: 'value',
          STRONG_DRAW: 'draw', WEAK_DRAW: 'draw', AIR: 'bluff'}


class RangeComposition:
    def __init__(self, board):
        self.board = list(board)
        self._scorer = RangeScorer(self.board)

    def classify(self, hole):
        """A qué familia pertenece una mano sobre este board."""
        mine = HandEvaluator.best_rank(self.board + list(hole))
        if mine[0] >= 1:
            return TWO_PAIR_OR_BETTER
        hole_ranks = [card.rank_value for card in hole]
        top = max(card.rank_value for card in self.board)
        if mine[0] == 0:
            pair = mine[1]
            if pair in hole_ranks:
                return TOP_PAIR if (pair >= top and (pair == top or hole_ranks.count(pair) == 2)) else WEAKER_PAIR

            return AIR  # la pareja es del board: su mano no añade nada
        outs = self._scorer._outs(hole, mine)
        if outs >= 8:
            return STRONG_DRAW
        if outs >= 4:
            return WEAK_DRAW

        return AIR

    def summarize(self, combos):
        """{familia: % del rango} (con peso), solo las que tienen algo; vacío si no hay combos."""
        total = sum(weight for _, weight in combos)
        if total <= 0:
            return OrderedDict()
        shares = {name: 0.0 for name in FAMILY}
        for hole, weight in combos:
            shares[self.classify(hole)] += weight

        return OrderedDict((name, round(100 * shares[name] / total, 1)) for name in FAMILY if shares[name] > 0)

    @staticmethod
    def families(shares):
        """Resumen en tres familias: {'value': %, 'draw': %, 'bluff': %}."""
        out = {'value': 0.0, 'draw': 0.0, 'bluff': 0.0}
        for name, percent in shares.items():
            out[FAMILY[name]] += percent

        return {family: round(percent, 1) for family, percent in out.items()}
