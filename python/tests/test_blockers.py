import unittest

from trainer_mtt.poker.blockers import Blockers
from trainer_mtt.poker.cards import Card


def cards(*texts):
    return [Card.from_string(t) for t in texts]


BOARD = cards('Kh', '9h', '4h', '2c', '7d')   # tres corazones en el board


class BlockersTest(unittest.TestCase):
    def combos(self):
        return [(tuple(cards(*pair)), 1.0) for pair in (("Ah", "5h"), ("Jh", "8h"), ("Qs", "3d"), ("Js", "6d"), ("Ts", "5d"))]

    def test_holding_the_ace_of_the_flush_suit_removes_value_combos(self):
        result = Blockers(BOARD).effect(self.combos(), cards('Ah', '3s'))

        self.assertGreater(result['removed']['value'], 0)
        self.assertLess(result['after']['value'], result['before']['value'])

    def test_cards_that_do_not_touch_his_range_remove_nothing(self):
        result = Blockers(BOARD).effect(self.combos(), cards('3c', '3d'))

        self.assertEqual(result['removed']['value'], 0.0)

    def test_lines_say_when_the_cards_block_his_value(self):
        text = ' '.join(Blockers(BOARD).lines(self.combos(), cards('Ah', '3s')))

        self.assertIn('BLOCKERS', text)
        self.assertIn('BLOCK HIS VALUE', text)

    def test_no_range_gives_no_lines(self):
        self.assertEqual(Blockers(BOARD).lines([], cards('Ah', '3s')), [])


if __name__ == '__main__':
    unittest.main()
