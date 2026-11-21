import unittest

from tests.test_ai_decision import FakeClient
from tests.test_street_services import FACING_BET, FLOP_SERVICE, PREFLOP
from trainer_mtt.ai.streets.opponent import OpponentRead
from trainer_mtt.poker.cards import Card
from trainer_mtt.poker.hand_range import HandRange
from trainer_mtt.poker.range_narrowing import RangeNarrower
from trainer_mtt.poker.range_scorer import RangeScorer

SPOTS = {spot.name: spot for spot in FLOP_SERVICE.spots}


def rival(flop, vb_ratio):
    return {'position': 'BB', 'stack_bb': 40.0, 'profile': {'preflop': {}, 'postflop': {'flop': flop, 'vb_ratio': vb_ratio}}}


MANIAC = rival({'af': 3.9, 'cr': 15, 'raise_vs_bet': 30, 'f2bet': 12, 'cbet': 85, 'donk': 30, 'sample_size': 200}, 0.8)
NIT = rival({'af': 0.8, 'cr': 2, 'raise_vs_bet': 8, 'f2bet': 55, 'cbet': 70, 'donk': 3, 'sample_size': 100}, 4.0)


class OpponentReadTest(unittest.TestCase):
    def test_a_maniac_reads_as_very_aggressive_and_never_folding(self):
        read = OpponentRead(MANIAC, 'flop')

        self.assertEqual(OpponentRead.level(read.aggression()), 'very high')
        self.assertLess(read.fold_tendency(), -0.5)
        self.assertEqual(read.confidence, 1.0)
        self.assertEqual(read.raise_frequency(), 26.4)  # (100 - 12) × 30 %

    def test_a_selective_player_reads_as_low_aggression_and_folding(self):
        read = OpponentRead(NIT, 'flop')

        self.assertEqual(OpponentRead.level(read.aggression()), 'low')
        self.assertGreater(read.fold_tendency(), 0)

    def test_without_statistics_there_is_nothing_to_adapt_to(self):
        read = OpponentRead({'position': 'BB', 'profile': {}}, 'flop')

        self.assertEqual(read.confidence, 0.0)
        self.assertEqual(read.aggression(), 0.0)
        self.assertIn('unknown', read.lines()[0])

    def test_few_hands_make_the_read_less_reliable(self):
        read = OpponentRead(rival({'af': 3.0, 'sample_size': 30}, None), 'flop')

        self.assertEqual(read.confidence, 0.5)


class AdaptationOfTheZonesTest(unittest.TestCase):
    BASE_RESPONSE = [('raise', 6.0), ('call', 24.0), ('fold', 70.0)]
    BASE_BETTING = [('bet', 60.0), ('check', 40.0)]

    def test_against_a_maniac_it_continues_more_when_he_bets(self):
        zones, notes = SPOTS['facing_bet'].adapt(self.BASE_RESPONSE, OpponentRead(MANIAC, 'flop'), 1.0)

        self.assertAlmostEqual(dict(zones)['fold'], 58.4, delta=0.3)   # 15 puntos × 0,77 de agresión
        self.assertAlmostEqual(dict(zones)['call'], 35.6, delta=0.3)
        self.assertEqual(sum(percent for _, percent in zones), 100.0)
        self.assertIn('continue', notes[0])

    def test_against_a_selective_player_it_folds_more_when_he_bets(self):
        zones, _ = SPOTS['facing_bet'].adapt(self.BASE_RESPONSE, OpponentRead(NIT, 'flop'), 1.0)

        self.assertGreater(dict(zones)['fold'], 70.0)
        self.assertLess(dict(zones)['call'], 24.0)

    def test_against_a_maniac_who_bets_when_checked_to_the_strong_hands_check_to_trap(self):
        zones, notes = SPOTS['initiative'].adapt(self.BASE_BETTING, OpponentRead(MANIAC, 'flop'), 1.0)

        self.assertEqual([name for name, _ in zones], ['trap', 'bet', 'check'])
        self.assertAlmostEqual(dict(zones)['trap'], 19.3, delta=0.3)  # 25 puntos × 0,77 de agresión
        self.assertLess(dict(zones)['bet'], 60.0)           # y menos faroles: foldea poco y sube mucho
        self.assertEqual(sum(percent for _, percent in zones), 100.0)
        self.assertIn('CHECKS to trap him', notes[0])

    def test_against_a_player_who_folds_a_lot_it_bluffs_more_and_does_not_trap(self):
        zones, _ = SPOTS['initiative'].adapt(self.BASE_BETTING, OpponentRead(NIT, 'flop'), 1.0)

        self.assertGreater(dict(zones)['bet'], 60.0)
        self.assertEqual(dict(zones)['trap'], 0.0)

    def test_the_skill_of_the_bot_scales_how_much_it_adapts(self):
        read = OpponentRead(MANIAC, 'flop')

        expert, _ = SPOTS['facing_bet'].adapt(self.BASE_RESPONSE, read, 1.0)
        weak, _ = SPOTS['facing_bet'].adapt(self.BASE_RESPONSE, read, 0.35)
        none, notes = SPOTS['facing_bet'].adapt(self.BASE_RESPONSE, read, 0.0)

        self.assertLess(dict(expert)['fold'], dict(weak)['fold'])
        self.assertEqual(none, self.BASE_RESPONSE)
        self.assertEqual(notes, [])

    def test_the_questionnaire_carries_the_adaptation_and_the_read(self):
        client = FakeClient('{"opponent_type": "maniac", "my_situation": "ahead", "check": 1, "bet_half": 1}')
        history = PREFLOP + [['flop', 'BB', 'check', 0]]
        state = {**FACING_BET, 'to_call_bb': 0.0, 'history': history, 'adaptation': 1.0, 'rivals': [MANIAC],
                 'range': {'AA': 1, 'KK': 1, 'AKo': 1, 'QJs': 1, 'T9s': 1, '65s': 1, '87s': 1}}

        FLOP_SERVICE.decide(state, 'm', ['check', 'bet_half'], client)

        text = client.calls[0][1][1]['content']
        self.assertIn('Read of this opponent: aggression VERY HIGH', text)
        self.assertIn('ADAPTATION to this opponent', text)
        self.assertIn('CHECKS to trap him', text)
        self.assertIn('trap (= CHECK)', text)


class NarrowingTest(unittest.TestCase):
    def test_after_betting_he_keeps_the_best_hands_plus_a_share_of_bluffs(self):
        board = [Card.from_string(c) for c in ('Kc', '7h', '2d')]
        scorer = RangeScorer(board)
        combos = HandRange({'AA': 1, 'KQs': 1, 'T9s': 1, '65s': 1}).combos(dead=board)
        everything = HandRange({'AA': 1}).combos(dead=board)

        narrowed = RangeNarrower(scorer).after_action(combos, scorer.table(everything), 25.0, 2.0)

        kept = narrowed.combos()
        total = sum(weight for _, weight in combos)
        aces = sum(weight for cards, weight in kept if all(card.rank == 'A' for card in cards))
        self.assertAlmostEqual(sum(weight for _, weight in kept), 0.25 * total, places=6)
        self.assertAlmostEqual(aces, 0.25 * total * 2 / 3, places=6)  # el valor es 2 de cada 3 apuestas, y son sus mejores manos

    def test_without_a_frequency_the_range_is_not_narrowed(self):
        board = [Card.from_string(c) for c in ('Kc', '7h', '2d')]
        scorer = RangeScorer(board)
        combos = HandRange({'AA': 1}).combos(dead=board)

        self.assertIsNone(RangeNarrower(scorer).after_action(combos, scorer.table(combos), None))


if __name__ == '__main__':
    unittest.main()


class ComputedByToolsTest(unittest.TestCase):
    def test_the_opponent_type_is_computed_from_his_statistics(self):
        self.assertEqual(OpponentRead(MANIAC, 'flop').type(), 'maniac')
        self.assertEqual(OpponentRead(NIT, 'flop').type(), 'nit')
        self.assertEqual(OpponentRead({'position': 'BB', 'profile': {}}, 'flop').type(), 'unknown')
        station = rival({'af': 0.8, 'raise_vs_bet': 5, 'cr': 1, 'f2bet': 8, 'sample_size': 100}, 4.0)
        self.assertEqual(OpponentRead(station, 'flop').type(), 'calling_station')
        average = rival({'af': 1.5, 'raise_vs_bet': 20, 'cr': 6, 'f2bet': 40, 'sample_size': 100}, 2.0)
        self.assertEqual(OpponentRead(average, 'flop').type(), 'balanced')
