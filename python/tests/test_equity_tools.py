import random
import unittest
from itertools import combinations

from trainer_mtt.poker.cards import Card, Deck
from trainer_mtt.poker.equity_estimator import EquityEstimator
from trainer_mtt.poker.evaluator import HandEvaluator
from trainer_mtt.poker.fast_eval import FastEvaluator
from trainer_mtt.poker.hand_analyzer import HandAnalyzer
from trainer_mtt.poker.range_composition import (
    AIR, STRONG_DRAW, TOP_PAIR, TWO_PAIR_OR_BETTER, WEAK_DRAW, WEAKER_PAIR, RangeComposition,
)


def cards(*texts):
    return [Card.from_string(text) for text in texts]


def sign(value):
    return (value > 0) - (value < 0)


class FastEvaluatorTest(unittest.TestCase):
    def test_it_orders_seven_card_hands_exactly_like_the_reference_evaluator(self):
        rng = random.Random(3)
        deck = Deck.full()
        for _ in range(400):
            sample = rng.sample(deck, 14)
            a, b = sample[:7], sample[7:]
            reference = sign((HandEvaluator.best_rank(a) > HandEvaluator.best_rank(b)) - (HandEvaluator.best_rank(a) < HandEvaluator.best_rank(b)))
            self.assertEqual(sign((FastEvaluator.rank(a) > FastEvaluator.rank(b)) - (FastEvaluator.rank(a) < FastEvaluator.rank(b))), reference)

    def test_it_knows_the_categories_the_reference_gets_wrong_to_confuse(self):
        self.assertEqual(FastEvaluator.rank(cards('As', '2h', '3d', '4c', '5s', 'Kd', 'Qd'))[0], 4)   # escalera con el as bajo
        self.assertEqual(FastEvaluator.rank(cards('2h', '3h', '4h', '5h', '6h', 'Kd', 'Qd'))[0], 8)   # escalera de color
        self.assertEqual(FastEvaluator.rank(cards('Ks', 'Kd', 'Kh', '2c', '2s', '2d', '9h'))[0], 6)   # full con dos tríos


class EquityEstimatorTest(unittest.TestCase):
    @staticmethod
    def everyone(hole, board):
        return [(pair, 1.0) for pair in combinations(Deck.without(hole + board), 2)]

    def test_a_set_is_a_huge_favourite_against_a_random_hand(self):
        hole, board = cards('7s', '7d'), cards('7h', 'Kc', '2d')

        self.assertGreater(EquityEstimator().equity(hole, board, self.everyone(hole, board)), 0.88)

    def test_king_high_with_two_overcards_a_flush_draw_and_an_open_ender_is_a_favourite(self):
        hole, board = cards('Kc', 'Qh'), cards('3h', 'Th', 'Jh')

        equity = EquityEstimator().equity(hole, board, self.everyone(hole, board))

        self.assertTrue(0.60 < equity < 0.80, equity)   # no es «rey alto con un 44 %»: vale mucho más

    def test_without_opponent_hands_there_is_no_equity(self):
        self.assertIsNone(EquityEstimator().equity(cards('As', 'Kd'), cards('2c', '7h', 'Jd'), []))


class AnalysisCountsEverythingTheHandCanBecomeTest(unittest.TestCase):
    def test_the_double_draw_with_overcards_is_described_with_its_equity(self):
        analyzer = HandAnalyzer()
        analysis = analyzer.analyze(['K♣', 'Q♥'], ['3♥', 'T♥', 'J♥'])

        self.assertEqual(analysis['made']['label'], 'no pair, king high')
        self.assertEqual(analysis['draws']['overcards'], 2)
        self.assertEqual(analysis['draws']['pair_outs'], 6)      # tres reyes y tres damas
        self.assertEqual(analysis['draws']['flush_outs'], 9)
        self.assertEqual(analysis['draws']['straight_outs'], 6)  # as y nueve que no son corazones
        self.assertTrue(60 < analysis['equity'] < 80)
        self.assertEqual(HandAnalyzer.situation(analysis), 'drawing')   # detrás hoy, pero con un proyecto fuerte
        text = ' '.join(analyzer.describe(analysis))
        self.assertIn('2 overcard(s) to the board (pairing one gives top pair: 6 outs)', text)
        self.assertIn('EQUITY by the river against that range', text)


class RangeCompositionTest(unittest.TestCase):
    board = cards('3h', 'Th', 'Jh')

    def test_each_hand_goes_to_its_family(self):
        composition = RangeComposition(self.board)

        self.assertEqual(composition.classify(cards('3s', '3d')), TWO_PAIR_OR_BETTER)   # set
        self.assertEqual(composition.classify(cards('Js', '2s')), TOP_PAIR)
        self.assertEqual(composition.classify(cards('Ts', '2s')), WEAKER_PAIR)
        self.assertEqual(composition.classify(cards('9s', '8s')), STRONG_DRAW)          # escalera abierta (el 89 del ejemplo)
        self.assertEqual(composition.classify(cards('Qs', '8c')), WEAK_DRAW)            # gutshot al nueve
        self.assertEqual(composition.classify(cards('As', '5s')), AIR)                  # puro farol

    def test_it_says_how_much_of_a_range_is_value_draws_and_bluffs(self):
        composition = RangeComposition(self.board)
        combos = [(cards('Js', '2s'), 1.0), (cards('9s', '8s'), 1.0), (cards('As', '5s'), 2.0)]

        shares = composition.summarize(combos)

        self.assertEqual(shares, {TOP_PAIR: 25.0, STRONG_DRAW: 25.0, AIR: 50.0})
        self.assertEqual(composition.families(shares), {'value': 25.0, 'draw': 25.0, 'bluff': 50.0})


if __name__ == '__main__':
    unittest.main()
