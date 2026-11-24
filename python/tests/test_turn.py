import unittest

from tests.test_ai_decision import FakeClient
from trainer_mtt.ai.streets import default_registry
from trainer_mtt.ai.streets.questionnaire import HandContext
from trainer_mtt.poker.board_story import BoardStory
from trainer_mtt.poker.cards import Card
from trainer_mtt.poker.hand_range import HandRange
from trainer_mtt.poker.hand_story import HandStory
from trainer_mtt.poker.preflop_hand import PreflopHand
from trainer_mtt.poker.range_story import RangeStory

SERVICE = default_registry().service_for('turn')
HAND = PreflopHand()
FLOP = {'f2bet': 45, 'raise_vs_bet': 12, 'cbet': 68, 'donk': 8, 'af': 2.1, 'cr': 7, 'sample_size': 600}
TURN = {'f2bet': 42, 'raise_vs_bet': 14, 'bet': 52, 'af': 2.0, 'cr': 6, 'f2raise': 35, 'sample_size': 500}
PROFILE = {'preflop': {'vpip': 30, 'pfr': 22}, 'postflop': {'flop': FLOP, 'turn': TURN, 'vb_ratio': 1.8}}
PREFLOP = [['preflop', 'BTN', 'raise', 2.5], ['preflop', 'SB', 'fold', 0], ['preflop', 'BB', 'call', 1.5]]
FLOP_BET_CALL = [['flop', 'BB', 'check', 0], ['flop', 'BTN', 'bet', 3.0], ['flop', 'BB', 'call', 3.0]]

# El BB (Q♥ J♥, proyecto de color) se enfrenta a la segunda apuesta del BTN
FACING_TURN_BET = {
    'street': 'turn', 'position': 'BB', 'in_position': False, 'hole_cards': ['Q♥', 'J♥'], 'board': ['K♣', '7♥', '2♦', '9♥'],
    'pot_bb': 12.0, 'to_call_bb': 6.0, 'stack_bb': 80.0, 'players_left': 2, 'adaptation': 1.0,
    'history': PREFLOP + FLOP_BET_CALL + [['turn', 'BB', 'check', 0], ['turn', 'BTN', 'bet', 6.0]],
    'profile': PROFILE, 'range': HAND.top_range(40).weights,
    'rivals': [{'position': 'BTN', 'stack_bb': 80.0, 'profile': PROFILE, 'range': HAND.top_range(38).weights}],
}


def cards(*texts):
    return [Card.from_string(text) for text in texts]


def spot_of(state):
    return SERVICE.spot_for(SERVICE.prepare_state(state)).name


class BoardStoryTest(unittest.TestCase):
    story = BoardStory()

    def test_it_describes_the_flop(self):
        dry = ' '.join(self.story.flop_lines(cards('Kc', '7h', '2d')))
        wet = ' '.join(self.story.flop_lines(cards('Jh', 'Th', '9c')))

        self.assertIn('king-high, rainbow', dry)
        self.assertIn('DRY', dry)
        self.assertIn('two-tone', wet)
        self.assertIn('WET', wet)

    def test_it_tells_what_the_turn_card_changed(self):
        flop = cards('Kc', '7h', '2d')

        self.assertIn('PAIRS the board', ' '.join(self.story.turn_lines(flop + cards('7s'))))
        self.assertIn('OVERCARD', ' '.join(self.story.turn_lines(cards('9c', '7h', '2d', 'As'))))
        self.assertIn('LOW card', ' '.join(self.story.turn_lines(cards('Kc', '9h', '7d', '3s'))))
        self.assertIn('THIRD card of one suit', ' '.join(self.story.turn_lines(cards('Kh', '7h', '2d', '9h'))))
        self.assertIn('FOUR cards of one suit', ' '.join(self.story.turn_lines(cards('Kh', '7h', '2h', '9h'))))
        self.assertIn('FOUR cards of a straight', ' '.join(self.story.turn_lines(cards('9c', '8h', '6d', '7s'))))
        self.assertEqual(self.story.turn_lines(flop), [])


class RangeStoryTest(unittest.TestCase):
    flop = cards('Kc', '7h', '2d')

    def setUp(self):
        self.story = RangeStory(self.flop)
        self.combos = HandRange(HAND.top_range(30).weights).combos(dead=self.flop)
        self.table = self.story.table(self.combos)
        self.total = sum(weight for _, weight in self.combos)

    def share(self, range_):
        return sum(weight for _, weight in range_.combos()) / self.total

    def test_it_reads_what_each_player_did_on_the_flop(self):
        history = [['flop', 'BB', 'check', 0], ['flop', 'BTN', 'bet', 3.0], ['flop', 'BB', 'raise', 9.0], ['flop', 'BTN', 'call', 6.0]]

        self.assertEqual(RangeStory.kinds(history, 'BB'), ['check', 'raise'])
        self.assertEqual(RangeStory.kinds(history, 'BTN'), ['bet', 'call'])

    def test_a_bet_keeps_only_the_share_of_the_range_that_bets(self):
        narrowed, steps = self.story.narrow(self.combos, self.table, ['bet'], {'cbet': 40}, True, 2.0)

        self.assertAlmostEqual(self.share(narrowed), 0.40, delta=0.01)
        self.assertIn('bets 40% of the time', steps[0])

    def test_a_check_keeps_what_would_not_have_bet_and_a_call_the_middle(self):
        checked, _ = self.story.narrow(self.combos, self.table, ['check'], {'cbet': 40}, True, 2.0)
        called, _ = self.story.narrow(self.combos, self.table, ['call'], {'f2bet': 40, 'raise_vs_bet': 25}, False)

        self.assertAlmostEqual(self.share(checked), 0.60, delta=0.01)
        self.assertAlmostEqual(self.share(called), 0.45, delta=0.01)   # 60 % continúa y el 25 % de ese 60 % sube: 15 % menos arriba

    def test_the_strongest_hands_are_in_the_bet_and_not_in_the_check(self):
        bet, _ = self.story.narrow(self.combos, self.table, ['bet'], {'cbet': 30}, True, 2.0)
        check, _ = self.story.narrow(self.combos, self.table, ['check'], {'cbet': 30}, True, 2.0)
        top_set = {tuple(map(str, cards_)) for cards_, _ in self.combos if {c.rank for c in cards_} == {'K'}}

        self.assertTrue(top_set & {tuple(map(str, cards_)) for cards_, _ in bet.combos()})
        self.assertFalse(top_set & {tuple(map(str, cards_)) for cards_, w in check.combos() if w > 0.5})

    def test_without_the_statistic_the_range_is_not_narrowed(self):
        narrowed, steps = self.story.narrow(self.combos, self.table, ['bet', 'check'], {}, True)

        self.assertEqual(steps, [])
        self.assertAlmostEqual(self.share(narrowed), 1.0, delta=1e-6)


class StoryOfTheHandTest(unittest.TestCase):
    def test_it_tells_the_preflop_in_a_sentence(self):
        text = HandStory(FACING_TURN_BET).preflop_summary()

        self.assertIn('BTN opened to 2.5bb', text)
        self.assertIn('single-raised pot', text)
        self.assertIn('BTN has the initiative', text)

    def test_it_tells_what_a_street_meant(self):
        bet_call = HandStory(FACING_TURN_BET).street_facts('flop')
        checked = HandStory({**FACING_TURN_BET, 'history': PREFLOP + [['flop', 'BB', 'check', 0], ['flop', 'BTN', 'check', 0]]}).street_facts('flop')
        check_raise = HandStory({**FACING_TURN_BET, 'history': PREFLOP + [['flop', 'BB', 'check', 0], ['flop', 'BTN', 'bet', 3.0], ['flop', 'BB', 'raise', 9.0]]}).street_facts('flop')

        self.assertIn('BTN bet, and BB called', bet_call[0])
        self.assertIn('BTN kept the initiative', bet_call[1])
        self.assertIn('checked through', checked[0])
        self.assertIn('BB raised (a check-raise', check_raise[0])
        self.assertEqual(HandStory(FACING_TURN_BET).street_facts('river'), [])


class TurnSpotsTest(unittest.TestCase):
    def test_the_initiative_is_the_one_of_whoever_bet_the_previous_street(self):
        barrel = {**FACING_TURN_BET, 'position': 'BTN', 'in_position': True, 'to_call_bb': 0.0, 'history': PREFLOP + FLOP_BET_CALL + [['turn', 'BB', 'check', 0]]}
        delayed = {**barrel, 'history': PREFLOP + [['flop', 'BB', 'check', 0], ['flop', 'BTN', 'check', 0], ['turn', 'BB', 'check', 0]]}
        donk_flop = {**barrel, 'position': 'BB', 'in_position': False, 'history': PREFLOP + [['flop', 'BB', 'bet', 3.0], ['flop', 'BTN', 'call', 3.0]]}

        self.assertTrue(HandContext(barrel).had_initiative())
        self.assertTrue(HandContext(delayed).had_initiative(), 'con el flop pasado, la iniciativa sigue siendo del que subió preflop')
        self.assertTrue(HandContext(donk_flop).had_initiative(), 'quien apostó el flop sin la iniciativa preflop la gana')
        self.assertFalse(HandContext({**donk_flop, 'position': 'BTN', 'in_position': True}).had_initiative(), 'y el que subió preflop la pierde')
        self.assertEqual(HandContext({**donk_flop, 'to_call_bb': 0.0}).previous_aggressor(), 'BB')

    def test_each_thing_that_can_happen_on_the_turn_goes_to_its_own_spot(self):
        barrel = {**FACING_TURN_BET, 'position': 'BTN', 'in_position': True, 'to_call_bb': 0.0, 'history': PREFLOP + FLOP_BET_CALL + [['turn', 'BB', 'check', 0]]}
        raised = {**FACING_TURN_BET, 'position': 'BTN', 'in_position': True, 'to_call_bb': 12.0,
                  'history': PREFLOP + FLOP_BET_CALL + [['turn', 'BB', 'check', 0], ['turn', 'BTN', 'bet', 6.0], ['turn', 'BB', 'raise', 18.0]]}
        no_initiative = {**FACING_TURN_BET, 'to_call_bb': 0.0, 'history': PREFLOP + FLOP_BET_CALL}

        self.assertEqual(spot_of(FACING_TURN_BET), 'facing_bet')
        self.assertEqual(spot_of(barrel), 'initiative')
        self.assertEqual(spot_of(raised), 'facing_raise')
        self.assertEqual(spot_of(no_initiative), 'no_initiative')

    def test_the_target_comes_from_the_turn_statistics(self):
        spots = {spot.name: spot for spot in SERVICE.spots}
        prepared = SERVICE.prepare_state(FACING_TURN_BET)

        self.assertEqual(spots['facing_bet'].target(prepared), {'fold_pct': 42.0, 'call_pct': 49.9, 'raise_pct': 8.1})
        self.assertEqual(spots['initiative'].target({**prepared, 'in_position': True}), {'bet_pct': 52.0, 'check_pct': 48.0})
        self.assertEqual(spots['facing_raise'].target(prepared), {'fold_pct': 35.0, 'continue_pct': 65.0})

    def test_the_model_sees_the_turn_and_the_flop_statistics_but_not_the_river(self):
        profile = {'postflop': {'flop': FLOP, 'turn': TURN, 'river': {'f2bet': 30}, 'vb_ratio': 1.4}}
        prepared = SERVICE.prepare_state({**FACING_TURN_BET, 'profile': profile})

        self.assertEqual(set(prepared['profile']['postflop']), {'flop', 'turn', 'vb_ratio'})

    def test_the_questionnaire_tells_the_story_of_the_hand_through_the_flop(self):
        client = FakeClient('{"fold": 1, "call": 1}')

        result = SERVICE.decide(FACING_TURN_BET, 'm', ['fold', 'call'], client)

        text = client.calls[0][1][1]['content']
        self.assertEqual(result['spot'], 'facing_bet')
        self.assertIn('QUESTIONNAIRE · TURN · FACING A BET', text)
        self.assertIn('1. THE STORY OF THE HAND SO FAR', text)
        self.assertIn('Preflop: BTN opened to 2.5bb', text)
        self.assertIn('Flop: BB check · BTN bet 3.0bb · BB call 3.0bb', text)
        self.assertIn('BTN kept the initiative', text)
        self.assertIn('Turn card 9h', text)
        self.assertIn('BTN: on the flop his line was bet', text)       # el rango del rival, estrechado por su apuesta del flop
        self.assertIn('You: on the flop your line was check', text)
        self.assertIn(') then call (', text)
        self.assertIn('Flop texture: king-high, rainbow', text)
        self.assertIn('what you did on the earlier streets', text)
        self.assertIn('EQUITY by the river', text)
        self.assertIn('PRICE CHECK', text)
        self.assertIn('Compare the rank of your hand (section 3)', text)

    def test_a_player_without_flop_statistics_keeps_the_range_he_arrived_with(self):
        bare = {'preflop': PROFILE['preflop'], 'postflop': {'turn': TURN}}
        client = FakeClient('{"fold": 1, "call": 1}')

        SERVICE.decide({**FACING_TURN_BET, 'profile': bare, 'rivals': [{**FACING_TURN_BET['rivals'][0], 'profile': bare}]}, 'm', ['fold', 'call'], client)

        text = client.calls[0][1][1]['content']
        self.assertNotIn('on the flop his line was', text)
        self.assertNotIn('on the flop your line was', text)


if __name__ == '__main__':
    unittest.main()
