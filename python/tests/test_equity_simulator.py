import unittest

from app import app
from equity_simulator import Card, EquitySimulator, HandEvaluator


class EquitySimulatorTest(unittest.TestCase):
    def setUp(self):
        self.simulator = EquitySimulator(seed=17)

    def test_starting_hand_classes_expand_to_the_correct_physical_combos(self):
        self.assertEqual(len(self.simulator._hand_combinations("AA")), 6)
        self.assertEqual(len(self.simulator._hand_combinations("AKs")), 4)
        self.assertEqual(len(self.simulator._hand_combinations("AKo")), 12)

    def test_evaluator_orders_known_hand_categories_and_wheels(self):
        royal_flush = [Card.from_string(card) for card in ("As", "Ks", "Qs", "Js", "Ts")]
        wheel = [Card.from_string(card) for card in ("As", "2h", "3d", "4c", "5s")]
        pair = [Card.from_string(card) for card in ("Ah", "Ad", "Ks", "Qc", "2h")]

        self.assertGreater(HandEvaluator.evaluate_hand(royal_flush), HandEvaluator.evaluate_hand(wheel))
        self.assertGreater(HandEvaluator.evaluate_hand(wheel), HandEvaluator.evaluate_hand(pair))

    def test_showdown_splits_a_board_straight_equally(self):
        hole_cards = [
            [Card.from_string("As"), Card.from_string("Kd")],
            [Card.from_string("Qh"), Card.from_string("Jc")],
        ]
        board = [Card.from_string(card) for card in ("2s", "3h", "4d", "5c", "6s")]

        self.assertEqual(self.simulator.evaluate_showdown(hole_cards, board), [0.5, 0.5])

    def test_same_canonical_hand_ranges_use_distinct_physical_cards_and_split_ties(self):
        result = self.simulator.simulate_equity("AKs", ["AKs"], iterations=100)

        self.assertEqual(result["wins"] + result["losses"] + result["ties"], 100)
        self.assertLess(result["ties"], 100)
        expected_equity = (result["wins"] + result["ties"] / 2) / 100
        self.assertEqual(result["equity"], round(expected_equity, 4))

    def test_multiway_equity_returns_one_result_per_complete_deal(self):
        result = self.simulator.simulate_multiway_equity(
            "AKs",
            [["QQ", "AKs"], ["JJ", "TT"]],
            iterations=25,
        )

        self.assertEqual(result["opponents"], 2)
        self.assertEqual(result["wins"] + result["losses"] + result["ties"], 25)
        self.assertGreaterEqual(result["equity"], 0)
        self.assertLessEqual(result["equity"], 1)

    def test_impossible_overlapping_ranges_fail_instead_of_returning_fake_equity(self):
        with self.assertRaisesRegex(ValueError, "non-overlapping"):
            self.simulator.simulate_multiway_equity("AA", [["AA"], ["AA"]], iterations=1)

    def test_invalid_starting_hand_and_duplicate_showdown_cards_are_rejected(self):
        with self.assertRaisesRegex(ValueError, "Invalid starting hand"):
            self.simulator.simulate_equity("AKx", ["QQ"], iterations=1)

        duplicate_cards = [Card.from_string(card) for card in ("As", "As", "Kd", "Qc", "Jh")]
        with self.assertRaisesRegex(ValueError, "duplicate"):
            HandEvaluator.evaluate_hand(duplicate_cards)

    def test_equity_api_accepts_multiple_opponent_ranges_and_keeps_heads_up_response(self):
        client = app.test_client()
        multiway = client.post(
            "/api/equity/simulate",
            json={
                "hero_hand": "AKs",
                "opponent_ranges": [["QQ", "AKs"], ["JJ", "TT"]],
                "iterations": 100,
            },
        )
        heads_up = client.post(
            "/api/equity/simulate",
            json={"hero_hand": "AKs", "villain_range": ["QQ"], "iterations": 100},
        )

        self.assertEqual(multiway.status_code, 200)
        self.assertEqual(multiway.json["opponent_range_sizes"], [2, 2])
        self.assertEqual(multiway.json["result"]["opponents"], 2)
        self.assertEqual(heads_up.status_code, 200)
        self.assertEqual(heads_up.json["villain_range_size"], 1)


if __name__ == "__main__":
    unittest.main()
