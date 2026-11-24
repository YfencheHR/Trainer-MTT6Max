"""Herramienta de análisis de mano: qué tengo con este board, qué proyectos y backdoors tengo y contra cuántas manos pierdo.

Es cálculo exacto (sin modelo): el modelo recibe el resultado ya hecho en su cuestionario y no tiene que adivinar la fuerza
de su mano. Sirve para cualquier calle con el board visible (3 a 5 cartas); en el flop los proyectos son de una carta por llegar.

El análisis de combos cuenta TODAS las manos que puede tener el rival (las cartas que no se ven). Es una vara de medir
objetiva: la fuerza de la mano contra el rango real del rival depende de su perfil, y eso lo valora el modelo con los datos.
"""

from collections import Counter
from itertools import combinations

from trainer_mtt.poker.cards import Card, Deck
from trainer_mtt.poker.equity_estimator import EquityEstimator
from trainer_mtt.poker.evaluator import HandEvaluator

RANK_NAME = {14: 'ace', 13: 'king', 12: 'queen', 11: 'jack', 10: 'ten', 9: 'nine', 8: 'eight', 7: 'seven',
             6: 'six', 5: 'five', 4: 'four', 3: 'three', 2: 'two'}
CATEGORY = {-1: 'high card', 0: 'pair', 1: 'two pair', 2: 'three of a kind', 3: 'straight', 4: 'flush',
            5: 'full house', 6: 'four of a kind', 7: 'straight flush', 8: 'royal flush'}
FLUSH_CATEGORIES = (4, 7, 8)


class HandAnalyzer:
    """Analiza dos cartas propias contra un board: {made, draws, backdoors, combos}, y lo cuenta en texto."""

    def analyze(self, hole_cards, board_cards, opponent_range=None):
        hole =[Card.from_string(card) for card in hole_cards]
        board = [Card.from_string(card) for card in board_cards]
        if len(hole) != 2 or not 3 <= len(board) <= 5:
            raise ValueError('Two hole cards and three to five board cards are required')
        if len({str(card) for card in hole + board}) != len(hole) + len(board):
            raise ValueError('Duplicate cards')

        unseen = Deck.without(hole + board)
        mine = HandEvaluator.best_rank(hole + board)
        draws = self._draws(hole, board, unseen, mine)

        # Contra el rango del rival (con sus pesos) si se conoce; si no, contra todas las manos posibles
        opponents = opponent_range.combos(dead=hole + board) if opponent_range else []
        against = 'range' if opponents else 'all'
        if not opponents:
            opponents = [(pair, 1.0) for pair in combinations(unseen, 2)]

        return {
            'made': {'category': CATEGORY[mine[0]], 'label': self._made_label(hole, board, mine)},
            'draws': draws,
            'backdoors': self._backdoors(hole, board, draws, mine),
            'combos': {**self._combos(board, opponents, mine), 'against': against},
            'equity': self._equity(hole, board, opponents),
            'final': len(board) == 5,
        }

    def describe(self, analysis):
        """El análisis como líneas de texto para el cuestionario del modelo."""
        draws, back, combos = analysis['draws'], analysis['backdoors'], analysis['combos']

        parts = []
        if draws['flush_draw']:
            parts.append(f"{'nut ' if draws['nut_flush_draw'] else ''}flush draw ({draws['flush_outs']} outs)")
        if draws['straight_draw'] == 'open_ended':
            parts.append(f"open-ended or double-gutshot straight draw ({draws['straight_outs']} outs)")
        elif draws['straight_draw'] == 'gutshot':
            parts.append(f"gutshot straight draw ({draws['straight_outs']} outs)")
        if back['flush']:
            parts.append('backdoor flush draw')
        if back['straight']:
            parts.append('backdoor straight draw')
        if draws['overcards'] and analysis['made']['category'] == 'high card':
            parts.append(f"{draws['overcards']} overcard(s) to the board (pairing one gives top pair: {draws['pair_outs']} outs)")

        lines = [
            f"Made hand: {analysis['made']['label']}.",
            ('Draws: none (the river is dealt: no cards to come, the hand is what it is).' if analysis.get('final') else f"Draws: {'; '.join(parts) if parts else 'none'}"
            + (f" (about {draws['outs']} cards improve you to a straight or better)." if draws['outs'] else '.')),
            f"Against {self._scope(combos)}: you beat {self._count(combos['ahead'])}, tie {self._count(combos['tied'])} "
            f"and lose to {self._count(combos['behind'])}; as it stands now (made hand only, no draws) it ranks above {combos['rank_pct']}% of them.",
        ]
        if analysis.get('equity') is not None:
            what = 'EQUITY at showdown against that range' if analysis.get('final') else 'EQUITY by the river against that range, counting your draws and overcards'
            lines.append(f"{what}: {analysis['equity']}% "
                         '(this, not the made hand alone, is what your hand is really worth).')
        if combos['beaten_by']:
            lines.append('Hands that beat you: ' + ', '.join(f'{name} ({self._count(count)} combos)' for name, count in combos['beaten_by'].items()) + '.')

        return lines

    @staticmethod
    def _equity(hole, board, opponents):
        """% de ganar el bote al llegar al final contra esas manos, contando proyectos y overcards (simulando las cartas que faltan)."""
        equity = EquityEstimator().equity(hole, board, opponents)

        return None if equity is None else round(100 * equity, 1)

    @staticmethod
    def _count(value):
        return int(round(value)) if abs(value - round(value)) < 1e-9 else round(value, 1)

    def _scope(self, combos):
        if combos.get('against') == 'range':
            return f"the opponent's likely range ({self._count(combos['total'])} combos)"

        return f"all {self._count(combos['total'])} possible opponent hands"

    @staticmethod
    def _plural(rank):
        name = RANK_NAME[rank]

        return name + ('es' if name == 'six' else 's')

    def _made_label(self, hole, board, rank):
        """Texto de la mano hecha en relación al board (top pair, set, overpair...)."""
        category = rank[0]
        hole_ranks = [card.rank_value for card in hole]
        board_ranks = sorted((card.rank_value for card in board), reverse=True)
        plural = self._plural

        if category == -1:
            return f'no pair, {RANK_NAME[rank[1]]} high'

        if category == 0:
            pair = rank[1]
            mine = hole_ranks.count(pair)
            if mine == 2:
                if pair > board_ranks[0]:
                    return f'overpair (pocket {plural(pair)})'
                if pair < board_ranks[-1]:
                    return f'pocket pair below the board ({plural(pair)})'
                return f'pocket pair of {plural(pair)} between the board cards'
            if mine == 1:
                kicker = RANK_NAME[max(r for r in hole_ranks if r != pair)]
                place = 'top pair' if pair == board_ranks[0] else ('bottom pair' if pair == board_ranks[-1] else 'middle pair')
                return f'{place} of {plural(pair)}, {kicker} kicker'
            return f'only the pair of {plural(pair)} on the board; your cards add just a {RANK_NAME[max(hole_ranks)]} kicker'

        if category == 1:
            pairs = (rank[1], rank[2])
            used = sum(1 for r in hole_ranks if r in pairs)
            names = f'{plural(pairs[0])} and {plural(pairs[1])}'
            if used == 0:
                return f'two pair on the board ({names}); your cards add nothing'
            return f'two pair, {names} (using {used} of your cards)'

        if category == 2:
            mine = hole_ranks.count(rank[1])
            if mine == 2:
                return f'set of {plural(rank[1])}'
            if mine == 1:
                return f'three of a kind, {plural(rank[1])} (one of your cards plus a paired board)'
            return f'three of a kind on the board ({plural(rank[1])})'

        return f'{CATEGORY[category]} ({RANK_NAME[rank[1]]} high)'

    @staticmethod
    def _draws(hole, board, unseen, current):
        """Cartas que mejoran la mano a escalera o mejor (proyectos) y su tipo. En el river no hay cartas por venir: no hay proyectos."""
        if len(board) >= 5:
            return {"flush_draw": False, "nut_flush_draw": False, "flush_outs": 0, "straight_draw": None, "straight_outs": 0, "outs": 0, "overcards": 0, "pair_outs": 0}
        flush_outs, straight_outs, other_outs = [], [], []
        for card in unseen:
            new = HandEvaluator.best_rank(hole + board + [card])
            if new[0] < 3 or new[0] <= current[0]:
                continue
            # En turn/river puede que el board solo ya haga esa mano: entonces no mejora la nuestra
            if len(board) + 1 >= 5 and new <= HandEvaluator.best_rank(board + [card]):
                continue
            if new[0] in FLUSH_CATEGORIES:
                flush_outs.append(card)
            elif new[0] == 3:
                straight_outs.append(card)
            else:
                other_outs.append(card)

        nut_flush = False
        if flush_outs:
            suit = flush_outs[0].suit
            mine = [card.rank_value for card in hole if card.suit == suit]
            nut_flush = bool(mine) and all(card.rank_value < max(mine) for card in unseen if card.suit == suit)

        straight_draw = None
        if straight_outs:
            straight_draw = 'open_ended' if len({card.rank_value for card in straight_outs}) >= 2 else 'gutshot'

        top_board = max(card.rank_value for card in board)
        overcards = [card for card in hole if card.rank_value > top_board]
        unique_outs = {str(card) for card in flush_outs + straight_outs + other_outs}

        return {
            'flush_draw': bool(flush_outs), 'nut_flush_draw': nut_flush, 'flush_outs': len(flush_outs),
            'straight_draw': straight_draw, 'straight_outs': len(straight_outs),
            'outs': len(unique_outs), 'overcards': len(overcards),
            'pair_outs': sum(1 for card in unseen if card.rank_value in {c.rank_value for c in overcards}) if current[0] == -1 else 0,
        }

    @staticmethod
    def _backdoors(hole, board, draws, current):
        """Proyectos de «runner-runner»: solo existen en el flop (hacen falta dos cartas más)."""
        if len(board) != 3:
            return {'flush': False, 'straight': False}

        cards = hole + board
        suits = Counter(card.suit for card in cards)
        flush = (not draws['flush_draw'] and current[0] < 4
                 and any(count == 3 and any(card.suit == suit for card in hole) for suit, count in suits.items()))

        ranks = {card.rank_value for card in cards}
        hole_ranks = {card.rank_value for card in hole}
        if 14 in ranks:
            ranks.add(1)
        if 14 in hole_ranks:
            hole_ranks.add(1)
        straight = False
        if not draws['straight_draw'] and current[0] < 3:
            for low in range(1, 11):
                window = set(range(low, low + 5))
                if len(window & ranks) >= 3 and window & hole_ranks:
                    straight = True
                    break

        return {'flush': flush, 'straight': straight}

    @staticmethod
    def _combos(board, opponents, mine):
        """Contra cuántas de las manos posibles del rival gana, empata y pierde, y con qué manos pierde.

        `opponents` = [((carta, carta), peso)]: todas las manos posibles con peso 1, o su rango real con el peso de cada mano.
        """
        ahead = tied = behind = 0.0
        beaten_by = Counter()
        for (first, second), weight in opponents:
            theirs = HandEvaluator.best_rank(board + [first, second])
            if theirs > mine:
                behind += weight
                name = CATEGORY[theirs[0]]
                beaten_by[f'a better {name}' if theirs[0] == mine[0] else name] += weight
            elif theirs == mine:
                tied += weight
            else:
                ahead += weight
        total = ahead + tied + behind

        return {
            'total': total, 'ahead': ahead, 'tied': tied, 'behind': behind,
            'rank_pct': round(100 * (ahead + tied / 2) / total, 1) if total else 0.0,
            'beaten_by': dict(beaten_by.most_common()),
        }

    @staticmethod
    def situation(analysis):
        """Cómo está la mano contra ese rival (lo calcula una herramienta a partir de la equity, los proyectos y la mano hecha)."""
        equity = analysis.get('equity')
        if equity is None:
            return 'marginal'
        behind_now = analysis['combos']['rank_pct'] < 50
        if behind_now and analysis['draws']['outs'] >= 8 and equity >= 35:
            return 'drawing'
        if equity >= 75:
            return 'far_ahead'
        if equity >= 58:
            return 'ahead'
        if equity >= 45:
            return 'marginal'
        if equity >= 25:
            return 'behind'

        return 'far_behind'
