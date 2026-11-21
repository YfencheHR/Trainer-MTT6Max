import unittest

from tests.test_ai_decision import FakeClient
from tests.test_street_services import FACING_BET, FLOP_SERVICE
from trainer_mtt.poker.cards import Card
from trainer_mtt.poker.hand_analyzer import HandAnalyzer
from trainer_mtt.poker.hand_range import HandRange
from trainer_mtt.poker.range_scorer import RangeScorer, RangeSplit


def cards(*texts):
    return [Card.from_string(text) for text in texts]


class HandRangeTest(unittest.TestCase):
    def test_it_keeps_only_valid_hands_with_weight(self):
        hand_range = HandRange({'AA': 1, 'AKs': 0.5, 'XX': 1, 'KK': 0, 'QQ': 'mucho'})

        self.assertEqual(hand_range.weights, {'AA': 1.0, 'AKs': 0.5})
        self.assertFalse(HandRange({}))

    def test_it_removes_the_combos_that_use_known_cards(self):
        self.assertEqual(len(HandRange({'AA': 1}).combos()), 6)
        self.assertEqual(len(HandRange({'AA': 1}).combos(dead=cards('As'))), 3)

    def test_it_tells_what_share_of_all_hands_it_is(self):
        self.assertEqual(HandRange({'AA': 1}).percent_of_hands(), 0.5)  # 6 de 1326 combos
        self.assertEqual(HandRange({'AA': 0.5}).percent_of_hands(), 0.2)


class RangeScorerTest(unittest.TestCase):
    board = cards('Kc', '7h', '2d')

    def table(self, scorer, rival, dead):
        return scorer.table(HandRange(rival).combos(dead=dead))

    def test_a_hand_that_is_far_ahead_of_the_whole_rival_range_scores_close_to_one(self):
        hole = cards('As', 'Kd')
        scorer = RangeScorer(self.board)

        # Contra QQ gana siempre hoy; solo pierde cuando QQ mejora a trío (sale una dama): equity de 0,86 aprox.
        self.assertGreater(scorer.score(hole, self.table(scorer, {'QQ': 1}, self.board + hole)), 0.80)

    def test_a_flush_draw_is_worth_its_real_equity(self):
        board, hole = cards('Kh', '9h', '2c'), cards('Ah', '5h')
        scorer = RangeScorer(board)

        # Pierde hoy contra QQ pero tiene 9 outs de color: equity de unos 0,40 contando todas las cartas que vienen
        self.assertTrue(0.33 < scorer.score(hole, self.table(scorer, {'QQ': 1}, board + hole)) < 0.47)

    def test_the_range_is_ordered_from_the_strongest_hand_to_the_weakest(self):
        scorer = RangeScorer(self.board)
        rival = HandRange({'AA': 1, 'KK': 1, 'QQ': 1}).combos(dead=self.board)

        scored = scorer.scored(HandRange({'KQs': 1, 'T9s': 1}).combos(dead=self.board), scorer.table(rival))

        strengths = [strength for strength, _ in scored]
        self.assertEqual(strengths, sorted(strengths, reverse=True))
        self.assertGreater(strengths[0], strengths[-1])


class RangeSplitTest(unittest.TestCase):
    scored = [(0.9, 1), (0.5, 1), (0.1, 1), (0.1, 1)]

    def test_it_tells_what_fraction_of_the_range_is_stronger_than_a_hand(self):
        split = RangeSplit(self.scored)

        self.assertEqual(split.top_fraction(0.9), 0.125)   # la mejor: la mitad de su propio peso por encima
        self.assertEqual(split.top_fraction(0.5), 0.375)
        self.assertEqual(split.top_fraction(0.1), 0.75)    # empatada con otra
        self.assertEqual(RangeSplit([]).top_fraction(0.5), 1.0)

    def test_it_puts_the_hand_in_its_zone_and_says_how_close_it_is_to_the_boundary(self):
        zones = [('raise', 25), ('call', 25), ('fold', 50)]

        self.assertEqual(RangeSplit.zone(0.125, zones), ('raise', 12.5))
        self.assertEqual(RangeSplit.zone(0.375, zones), ('call', 12.5))
        self.assertEqual(RangeSplit.zone(0.75, zones), ('fold', 25.0))
        self.assertEqual(RangeSplit.zone(0.60, zones), ('fold', 10.0))


class AnalysisAgainstARangeTest(unittest.TestCase):
    def test_the_combos_are_counted_against_the_rival_range_with_its_weights(self):
        hole, board = ['A♠', 'K♦'], ['K♣', '7♥', '2♦']

        analysis = HandAnalyzer().analyze(hole, board, HandRange({'QQ': 1, 'AKs': 0.5}))
        combos = analysis['combos']

        self.assertEqual(combos['against'], 'range')
        self.assertEqual(combos['ahead'], 6.0)    # QQ: seis combos, pierde la pareja de damas
        self.assertEqual(combos['tied'], 0.5)     # AKs: solo queda A♥K♥ (A♠ y K♦ son nuestras, K♣ está en el board), con peso 0.5
        self.assertIn("the opponent's likely range (6.5 combos)", ' '.join(HandAnalyzer().describe(analysis)))

    def test_without_a_range_it_is_against_all_the_hands(self):
        analysis = HandAnalyzer().analyze(['A♠', 'K♦'], ['K♣', '7♥', '2♦'])

        self.assertEqual(analysis['combos']['against'], 'all')


class RangeQuestionnaireTest(unittest.TestCase):
    OWN = {'AA': 1.0, 'AKo': 1.0, 'K7s': 1.0, 'QJs': 1.0, 'T9s': 1.0, '65s': 1.0}
    RIVAL = {'QQ': 1.0, 'JJ': 1.0, 'AKs': 1.0, 'T9s': 1.0}

    def prompt(self, state):
        client = FakeClient('{"opponent_type": "nit", "my_situation": "ahead", "fold": 1, "call": 1}')
        FLOP_SERVICE.decide(state, 'm', ['fold', 'call'], client)

        return client.calls[0][1][1]['content']

    def test_the_questionnaire_says_in_which_zone_of_its_own_range_the_hand_falls(self):
        # adaptation 0: el bot no se adapta al rival, así que las zonas son las del perfil sin tocar
        state = {**FACING_BET, 'adaptation': 0, 'range': self.OWN, 'rivals': [{**FACING_BET['rivals'][0], 'range': self.RIVAL}]}

        text = self.prompt(state)

        self.assertIn('Range you arrive with at this street', text)
        self.assertIn('your profile splits it: top 6.0% raise, next 24.0% call, bottom 70.0% fold', text)
        self.assertIn('Your hand is in the top', text)
        self.assertIn("the opponent's likely range", text)
        self.assertIn('Decide with ALL the results above', text)

    def test_without_ranges_the_questionnaire_has_no_range_section(self):
        text = self.prompt(FACING_BET)

        self.assertNotIn('Range you arrive with', text)
        self.assertNotIn('Decide with ALL the results above', text)


if __name__ == '__main__':
    unittest.main()
