import unittest

from trainer_mtt.poker.cards import Card
from trainer_mtt.poker.range_advantage import RangeAdvantage

BOARD = [Card.from_string(c) for c in ('Ks', '7d', '2c')]


def combos(*pairs):
    return [((Card.from_string(a), Card.from_string(b)), 1.0) for a, b in pairs]


STRONG = combos(('Kh', 'Qd'), ('Kd', 'Jc'), ('7h', '7c'), ('Ah', 'Kc'))      # parejas altas y set
WEAK = combos(('Th', '9h'), ('8c', '6d'), ('Qh', 'Jh'), ('5c', '4d'))        # nada hecho


class RangeAdvantageTest(unittest.TestCase):
    def test_the_range_with_made_hands_has_more_equity_and_more_nuts(self):
        result = RangeAdvantage(BOARD).compare(STRONG, WEAK)

        self.assertGreater(result['mine']['equity'], result['rival']['equity'])
        self.assertGreater(result['mine']['nuts'], result['rival']['nuts'])

    def test_it_is_symmetric_when_the_ranges_are_swapped(self):
        a = RangeAdvantage(BOARD).compare(STRONG, WEAK)
        b = RangeAdvantage(BOARD).compare(WEAK, STRONG)

        self.assertEqual(a['mine'], b['rival'])
        self.assertEqual(a['rival'], b['mine'])

    def test_lines_say_who_is_stronger(self):
        text = ' '.join(RangeAdvantage(BOARD).lines(STRONG, WEAK))

        self.assertIn('RANGE ADVANTAGE', text)
        self.assertIn('YOUR RANGE IS STRONGER', text)
        self.assertIn('HIS RANGE IS STRONGER', ' '.join(RangeAdvantage(BOARD).lines(WEAK, STRONG)))

    def test_equal_ranges_have_no_advantage(self):
        text = ' '.join(RangeAdvantage(BOARD).lines(STRONG, STRONG))

        self.assertIn('Neither range is clearly better', text)

    def test_a_missing_range_gives_no_lines(self):
        self.assertEqual(RangeAdvantage(BOARD).lines([], STRONG), [])


class SamplingTest(unittest.TestCase):
    def test_a_big_range_is_reduced_to_a_sample_that_keeps_the_result(self):
        from trainer_mtt.poker.hand_range import HandRange
        from trainer_mtt.poker.preflop_hand import PreflopHand
        from trainer_mtt.poker.range_advantage import MAX_COMBOS

        wide = HandRange(PreflopHand().top_range(60).weights).combos(dead=BOARD)
        tight = HandRange(PreflopHand().top_range(15).weights).combos(dead=BOARD)

        self.assertGreater(len(wide), MAX_COMBOS)
        self.assertEqual(len(RangeAdvantage._sample(wide)), MAX_COMBOS)
        result = RangeAdvantage(BOARD).compare(tight, wide)
        self.assertGreater(result['mine']['equity'], result['rival']['equity'], 'el rango más fuerte sigue siéndolo con la muestra')


if __name__ == '__main__':
    unittest.main()
