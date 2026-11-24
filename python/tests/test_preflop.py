import unittest

from trainer_mtt.ai.streets import default_registry
from trainer_mtt.ai.streets.preflop.read import PreflopRead
from trainer_mtt.poker.hand_story import HandStory
from trainer_mtt.poker.preflop_hand import PreflopHand
from tests.test_ai_decision import FakeClient

HAND = PreflopHand()
SERVICE = default_registry().service_for('preflop')
LOOSE = {'preflop': {'vpip': 38, 'pfr': 30, 'three_bet': 10, 'hands': 400}}
TIGHT = {'preflop': {'vpip': 14, 'pfr': 11, 'three_bet': 2, 'hands': 400}}

FOLDS = [['preflop', 'UTG', 'fold', 0], ['preflop', 'HJ', 'fold', 0], ['preflop', 'CO', 'fold', 0]]
# El BB (K♠ J♦) se enfrenta a la apertura del botón
FACING_OPEN = {
    'street': 'preflop', 'position': 'BB', 'in_position': False, 'hole_cards': ['K♠', 'J♦'], 'board': [],
    'pot_bb': 4.0, 'to_call_bb': 1.5, 'stack_bb': 38.0, 'players_left': 2,
    'history': FOLDS + [['preflop', 'BTN', 'raise', 2.5], ['preflop', 'SB', 'fold', 0]],
    'profile': {'preflop': {'vpip': 26, 'pfr': 19, 'three_bet': 7}},
    'rivals': [{'position': 'BTN', 'stack_bb': 40.0, 'profile': LOOSE}],
    'table': {'spot': {'raise': 8.0, 'call': 24.0, 'fold': 68.0}, 'hand': {'raise': 5.0, 'call': 60.0, 'fold': 35.0}, 'hand_samples': 14},
    'adaptation': 1.0,
}


def spot_of(state):
    return SERVICE.spot_for(SERVICE.prepare_state(state)).name


class PreflopHandTest(unittest.TestCase):
    def test_it_names_the_hand_with_its_canonical_class(self):
        self.assertEqual(HAND.name(['A♠', 'K♠']), 'AKs')
        self.assertEqual(HAND.name(['K♦', 'A♠']), 'AKo')
        self.assertEqual(HAND.name(['7♥', '7♣']), '77')

    def test_it_ranks_the_hands_from_the_best_to_the_worst(self):
        self.assertLess(HAND.top_percent('AA'), 1)
        self.assertLess(HAND.top_percent('KK'), HAND.top_percent('AKs'))
        self.assertGreater(HAND.top_percent('72o'), 90)
        self.assertLess(HAND.top_percent('AKs'), HAND.top_percent('KJo'))

    def test_it_describes_the_kind_of_hand(self):
        self.assertEqual(HAND.kind('88'), 'pocket pair of eights')
        self.assertEqual(HAND.kind('KJo'), 'offsuit broadway (KJo)')
        self.assertEqual(HAND.kind('A5s'), 'suited ace with a weak kicker (A5s)')
        self.assertEqual(HAND.kind('87s'), 'suited connector (87s)')
        self.assertEqual(HAND.kind('T8o'), 'offsuit one-gapper (T8o)')

    def test_a_range_made_from_a_percentage_covers_that_percentage_of_the_hands(self):
        self.assertAlmostEqual(HAND.top_range(10).percent_of_hands(), 10, delta=0.3)
        self.assertIn('AA', HAND.top_range(3).weights)
        self.assertNotIn('72o', HAND.top_range(30).weights)

    def test_equity_against_a_range_is_computed_to_the_end(self):
        premium = HAND.equity_against(['A♠', 'A♦'], HAND.top_range(10))
        trash = HAND.equity_against(['7♠', '2♦'], HAND.top_range(10))

        self.assertGreater(premium, 68)
        self.assertLess(trash, 35)
        self.assertIsNone(HAND.equity_against(['A♠', 'A♦'], None))


class HandStoryTest(unittest.TestCase):
    def test_it_tells_who_did_what(self):
        story = HandStory(FACING_OPEN)

        self.assertEqual(story.opener(), 'BTN')
        self.assertEqual(story.raises(), 1)
        self.assertEqual(story.role_of('BTN'), 'opened')
        self.assertEqual(story.role_of('UTG'), 'folded')
        self.assertIsNone(story.role_of('BB'))
        self.assertEqual(story.line(), 'UTG folded · HJ folded · CO folded · BTN opened to 2.5bb · SB folded')

    def test_a_late_position_open_into_the_blinds_is_a_steal(self):
        facts = ' '.join(HandStory(FACING_OPEN).facts())

        self.assertIn('steal attempt', facts)
        self.assertIn('out of position against BTN', facts)

    def test_an_open_with_a_caller_makes_a_raise_a_squeeze(self):
        history = [['preflop', 'UTG', 'fold', 0], ['preflop', 'HJ', 'raise', 2.5], ['preflop', 'CO', 'call', 2.5]]
        story = HandStory({**FACING_OPEN, 'position': 'BTN', 'history': history, 'rivals': [{'position': 'HJ'}, {'position': 'CO'}, {'position': 'SB'}, {'position': 'BB'}]})

        self.assertEqual(story.callers(), ['CO'])
        self.assertIn('squeeze', ' '.join(story.facts()))
        self.assertEqual(story.still_to_act(), ['SB', 'BB'])

    def test_limpers_and_three_bets_are_told_apart(self):
        limped = HandStory({**FACING_OPEN, 'history': [['preflop', 'UTG', 'call', 1.0], ['preflop', 'HJ', 'call', 1.0]]})
        three_bet = HandStory({**FACING_OPEN, 'history': [['preflop', 'UTG', 'raise', 2.5], ['preflop', 'HJ', 'raise', 8.0]]})

        self.assertEqual(limped.limpers(), ['UTG', 'HJ'])
        self.assertIn('2 limper(s)', ' '.join(limped.facts()))
        self.assertEqual(three_bet.role_of('HJ'), '3-bet')
        self.assertEqual(three_bet.raises(), 2)
        self.assertIn('HJ 3-bet', ' '.join(three_bet.facts()))

    def test_the_range_each_line_represents_comes_from_the_tables_when_it_is_known(self):
        rivals = [{'position': 'BTN', 'stack_bb': 40.0, 'profile': LOOSE, 'range': {'AA': 1.0, 'KK': 1.0, 'AKs': 1.0}}]
        lines = HandStory({**FACING_OPEN, 'rivals': rivals}).what_the_lines_say()

        self.assertEqual(len(lines), 1)
        self.assertIn('BTN opened: with that line his range is about 1.', lines[0])


class PreflopSpotsTest(unittest.TestCase):
    def test_each_thing_that_can_happen_before_the_flop_goes_to_its_own_spot(self):
        nobody = {**FACING_OPEN, 'position': 'BTN', 'to_call_bb': 1.0, 'history': FOLDS}
        reopened = {**FACING_OPEN, 'position': 'CO', 'to_call_bb': 7.5, 'history': [['preflop', 'UTG', 'fold', 0], ['preflop', 'HJ', 'fold', 0],
                    ['preflop', 'CO', 'raise', 2.5], ['preflop', 'BTN', 'raise', 8.0]]}
        cold = {**FACING_OPEN, 'position': 'CO', 'to_call_bb': 8.0, 'history': [['preflop', 'UTG', 'raise', 2.5], ['preflop', 'HJ', 'raise', 8.0]]}

        self.assertEqual(spot_of(nobody), 'open')
        self.assertEqual(spot_of(FACING_OPEN), 'facing_open')
        self.assertEqual(spot_of(reopened), 'facing_reraise')
        self.assertEqual(spot_of(cold), 'cold_reraise')

    def test_the_big_blind_with_a_free_check_still_decides_in_the_open_spot(self):
        limped = {**FACING_OPEN, 'to_call_bb': 0.0, 'history': [['preflop', 'UTG', 'call', 1.0], ['preflop', 'SB', 'call', 1.0]]}

        self.assertEqual(spot_of(limped), 'open')

    def test_the_target_is_what_the_table_of_the_profile_does_in_the_spot(self):
        spot = SERVICE.spot_for(SERVICE.prepare_state(FACING_OPEN))

        self.assertEqual(spot.target(FACING_OPEN), {'raise_pct': 8.0, 'call_pct': 24.0, 'fold_pct': 68.0})
        self.assertEqual(spot.target({**FACING_OPEN, 'table': None}), {})

    def test_the_questionnaire_has_the_story_the_hand_the_price_and_the_profile(self):
        client = FakeClient('{"fold": 1, "call": 1}')

        result = SERVICE.decide(FACING_OPEN, 'm', ['fold', 'call'], client)

        text = client.calls[0][1][1]['content']
        self.assertEqual(result['spot'], 'facing_open')
        self.assertIn('QUESTIONNAIRE · PREFLOP · FACING AN OPEN RAISE', text)
        self.assertIn('BTN opened to 2.5bb', text)                                     # la historia
        self.assertIn('steal attempt', text)
        self.assertIn('Hand: offsuit broadway (KJo).', text)                           # la mano
        self.assertIn('top 11.8% of all starting hands', text)
        self.assertIn('assumed: the strongest 30% of hands', text)                   # el rango del rival, supuesto desde su PFR
        self.assertIn('PRICE CHECK: calling needs 27.3% equity', text)                 # el precio
        self.assertIn("With exactly this hand (KJo) in this spot this player's table does: raise 5.0%, call 60.0%, fold 35.0%", text)
        self.assertIn('it falls in the', text)                                         # la zona del rango
        self.assertIn('What each option tells the table', text)                        # la historia que cuenta cada acción
        self.assertIn('Type of player (computed from his statistics): LOOSE_AGGRESSIVE', text)
        self.assertEqual(list(client.calls[0][2]['properties']), ['fold', 'call'])     # el modelo solo reparte probabilidades

    def test_a_known_range_replaces_the_assumed_one(self):
        rivals = [{'position': 'BTN', 'stack_bb': 40.0, 'profile': LOOSE, 'range': {'AA': 1.0, 'KK': 1.0, 'QQ': 1.0}}]
        client = FakeClient('{"fold": 1, "call": 1}')

        SERVICE.decide({**FACING_OPEN, 'rivals': rivals}, 'm', ['fold', 'call'], client)

        text = client.calls[0][1][1]['content']
        self.assertIn('from his preflop table for this line', text)
        self.assertNotIn('assumed', text)

    def test_against_a_loose_opener_the_profile_defends_more_and_against_a_tight_one_less(self):
        spot = SERVICE.spot_for(SERVICE.prepare_state(FACING_OPEN))
        zones = spot.zones(FACING_OPEN)

        loose, notes = spot.adapt(zones, PreflopRead({'profile': LOOSE}), 1.0)
        tight, _ = spot.adapt(zones, PreflopRead({'profile': TIGHT}), 1.0)
        unknown, none = spot.adapt(zones, PreflopRead({'profile': {}}), 0.0)

        self.assertLess(dict(loose)['fold'], 68.0)
        self.assertGreater(dict(loose)['raise'], 8.0)
        self.assertGreater(dict(tight)['fold'], 68.0)
        self.assertEqual(unknown, zones)
        self.assertEqual(none, [])
        self.assertIn('continue and re-raise more', notes[0])

    def test_a_skilless_bot_does_not_adapt(self):
        client = FakeClient('{"fold": 1, "call": 1}')

        SERVICE.decide({**FACING_OPEN, 'adaptation': 0.0}, 'm', ['fold', 'call'], client)

        self.assertNotIn('ADAPTATION', client.calls[0][1][1]['content'])

    def test_the_effective_stack_tells_when_it_is_a_push_or_fold(self):
        client = FakeClient('{"fold": 1, "allin": 1}')

        SERVICE.decide({**FACING_OPEN, 'stack_bb': 8.0}, 'm', ['fold', 'allin'], client)

        self.assertIn('PUSH/FOLD (10 bb or less)', client.calls[0][1][1]['content'])


class PreflopReadTest(unittest.TestCase):
    def test_it_classifies_the_opponent_from_his_preflop_statistics(self):
        kinds = {
            'maniac': {'vpip': 55, 'pfr': 35}, 'calling_station': {'vpip': 45, 'pfr': 8}, 'loose_aggressive': {'vpip': 32, 'pfr': 24},
            'nit': {'vpip': 12, 'pfr': 9}, 'tight_aggressive': {'vpip': 20, 'pfr': 16}, 'passive': {'vpip': 22, 'pfr': 6},
        }
        for kind, stats in kinds.items():
            self.assertEqual(PreflopRead({'profile': {'preflop': stats}}).type(), kind)
        self.assertEqual(PreflopRead({'profile': {}}).type(), 'unknown')

    def test_looseness_and_three_bet_tendency_go_from_minus_one_to_one(self):
        loose, tight = PreflopRead({'profile': LOOSE}), PreflopRead({'profile': TIGHT})

        self.assertEqual(loose.looseness(), 1.0)
        self.assertEqual(tight.looseness(), -0.4)
        self.assertEqual(loose.three_bet_tendency(), 0.8)
        self.assertEqual(tight.three_bet_tendency(), -0.8)


if __name__ == '__main__':
    unittest.main()
