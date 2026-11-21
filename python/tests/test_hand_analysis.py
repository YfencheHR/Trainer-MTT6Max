import unittest

from trainer_mtt.poker.hand_analyzer import HandAnalyzer
from trainer_mtt.poker.cards import Card


ANALYZER = HandAnalyzer()


class HandAnalysisTest(unittest.TestCase):
    def test_it_reads_cards_with_symbols_or_letters(self):
        self.assertEqual(str(Card.from_string('A♠')), 'As')
        self.assertEqual(str(Card.from_string('Td')), 'Td')

    def test_it_names_the_made_hand_against_the_board(self):
        top = ANALYZER.analyze(['A♠', 'K♦'], ['K♣', '7♥', '2♦'])['made']
        self.assertEqual(top['category'], 'pair')
        self.assertEqual(top['label'], 'top pair of kings, ace kicker')

        self.assertTrue(ANALYZER.analyze(['5♠', '5♦'], ['K♣', '9♥', '7♦'])['made']['label'].startswith('pocket pair below the board'))
        self.assertTrue(ANALYZER.analyze(['Q♠', 'Q♦'], ['K♣', '7♥', '2♦'])['made']['label'].startswith('pocket pair of queens between'))
        self.assertTrue(ANALYZER.analyze(['A♠', 'A♦'], ['K♣', '7♥', '2♦'])['made']['label'].startswith('overpair'))
        self.assertEqual(ANALYZER.analyze(['7♠', '7♦'], ['7♥', 'K♣', '2♦'])['made']['label'], 'set of sevens')

    def test_it_counts_every_hand_the_opponent_can_have(self):
        combos = ANALYZER.analyze(['A♠', 'K♦'], ['K♣', '7♥', '2♦'])['combos']

        self.assertEqual(combos['total'], 1081)  # 47 cartas que no se ven: 47·46/2
        self.assertEqual(combos['ahead'] + combos['tied'] + combos['behind'], 1081)

    def test_a_set_only_loses_to_a_higher_set(self):
        combos = ANALYZER.analyze(['7♠', '7♦'], ['7♥', 'K♣', '2♦'])['combos']

        self.assertEqual(combos['behind'], 3)   # KK: tres combinaciones de las tres K que quedan
        self.assertEqual(combos['beaten_by'], {'a better three of a kind': 3})
        self.assertEqual(combos['ahead'], 1078)

    def test_it_finds_a_nut_flush_draw_and_its_outs(self):
        draws = ANALYZER.analyze(['A♥', '5♥'], ['K♥', '9♥', '2♣'])['draws']

        self.assertTrue(draws['flush_draw'])
        self.assertTrue(draws['nut_flush_draw'])
        self.assertEqual(draws['flush_outs'], 9)
        self.assertIsNone(draws['straight_draw'])

    def test_it_tells_an_open_ended_draw_from_a_gutshot(self):
        open_ended = ANALYZER.analyze(['8♠', '7♦'], ['9♣', '6♥', '2♠'])['draws']
        gutshot = ANALYZER.analyze(['8♠', '7♦'], ['9♣', '5♥', '2♠'])['draws']

        self.assertEqual((open_ended['straight_draw'], open_ended['straight_outs']), ('open_ended', 8))
        self.assertEqual((gutshot['straight_draw'], gutshot['straight_outs']), ('gutshot', 4))

    def test_it_finds_the_backdoor_flush_draw(self):
        analysis = ANALYZER.analyze(['A♠', '5♠'], ['K♠', '9♦', '2♣'])

        self.assertTrue(analysis['backdoors']['flush'])
        self.assertFalse(analysis['draws']['flush_draw'])

    def test_it_rejects_impossible_hands(self):
        with self.assertRaises(ValueError):
            ANALYZER.analyze(['A♠', 'A♠'], ['K♣', '7♥', '2♦'])
        with self.assertRaises(ValueError):
            ANALYZER.analyze(['A♠', 'K♦'], ['K♣', '7♥'])

    def test_it_describes_the_analysis_as_text(self):
        lines = ANALYZER.describe(ANALYZER.analyze(['A♥', '5♥'], ['K♥', '9♥', '2♣']))

        self.assertIn('nut flush draw (9 outs)', ' '.join(lines))
        self.assertTrue(lines[0].startswith('Made hand:'))


if __name__ == '__main__':
    unittest.main()
