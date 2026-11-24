import unittest

from tests.test_ai_decision import FakeClient
from trainer_mtt.ai.streets import default_registry
from trainer_mtt.ai.streets.questionnaire import HandContext
from trainer_mtt.poker.board_story import BoardStory
from trainer_mtt.poker.cards import Card
from trainer_mtt.poker.preflop_hand import PreflopHand

SERVICE = default_registry().service_for('river')
HAND = PreflopHand()
FLOP = {'f2bet': 45, 'raise_vs_bet': 12, 'cbet': 68, 'donk': 8, 'af': 2.1, 'cr': 7, 'sample_size': 600}
TURN = {'f2bet': 42, 'raise_vs_bet': 14, 'bet': 52, 'af': 2.0, 'cr': 6, 'f2raise': 35, 'sample_size': 500}
RIVER = {'f2bet': 38, 'raise_vs_bet': 10, 'bet': 40, 'af': 1.8, 'cr': 4, 'f2raise': 30, 'sample_size': 400}
PROFILE = {'preflop': {'vpip': 30, 'pfr': 22}, 'postflop': {'flop': FLOP, 'turn': TURN, 'river': RIVER, 'vb_ratio': 1.8}}
PREFLOP = [['preflop', 'BTN', 'raise', 2.5], ['preflop', 'SB', 'fold', 0], ['preflop', 'BB', 'call', 1.5]]
FLOP_LINE = [['flop', 'BB', 'check', 0], ['flop', 'BTN', 'bet', 3.0], ['flop', 'BB', 'call', 3.0]]
TURN_LINE = [['turn', 'BB', 'check', 0], ['turn', 'BTN', 'bet', 6.0], ['turn', 'BB', 'call', 6.0]]

# El BB (Q♥ J♥, el proyecto de color no llegó) se enfrenta a la tercera apuesta del BTN
FACING_RIVER_BET = {
    'street': 'river', 'position': 'BB', 'in_position': False, 'hole_cards': ['Q♥', 'J♥'], 'board': ['K♣', '7♥', '2♦', '9♥', '3♠'],
    'pot_bb': 24.0, 'to_call_bb': 14.0, 'stack_bb': 60.0, 'players_left': 2, 'adaptation': 1.0,
    'history': PREFLOP + FLOP_LINE + TURN_LINE + [['river', 'BB', 'check', 0], ['river', 'BTN', 'bet', 14.0]],
    'profile': PROFILE, 'range': HAND.top_range(40).weights,
    'rivals': [{'position': 'BTN', 'stack_bb': 60.0, 'profile': PROFILE, 'range': HAND.top_range(38).weights}],
}


def cards(*texts):
    return [Card.from_string(text) for text in texts]


def spot_of(state):
    return SERVICE.spot_for(SERVICE.prepare_state(state)).name


class RiverCardTest(unittest.TestCase):
    story = BoardStory()

    def test_it_tells_what_the_river_card_changed(self):
        board = cards('Kc', '7h', '2d', '9s')

        self.assertIn('PAIRS the board', ' '.join(self.story.river_lines(board + cards('7s'))))
        self.assertIn('OVERCARD', ' '.join(self.story.river_lines(board + cards('As'))))
        self.assertIn('a flush needs both hole cards', ' '.join(self.story.river_lines(cards('Kh', '7h', '2d', '9s', '3h'))))
        self.assertIn('FIVE cards of one suit', ' '.join(self.story.river_lines(cards('Kh', '7h', '2h', '9h', '3h'))))
        self.assertEqual(self.story.river_lines(board), [])

    def test_a_river_board_describes_the_turn_and_the_river(self):
        text = ' '.join(self.story.lines(cards('Kc', '7h', '2d', '9s', '3h')))

        self.assertIn('Flop texture', text)
        self.assertIn('Turn card 9s', text)
        self.assertIn('River card 3h', text)


class RiverSpotsTest(unittest.TestCase):
    def test_each_thing_that_can_happen_on_the_river_goes_to_its_own_spot(self):
        barrel = {**FACING_RIVER_BET, 'position': 'BTN', 'in_position': True, 'to_call_bb': 0.0,
                  'history': PREFLOP + FLOP_LINE + TURN_LINE[:1] + [['turn', 'BTN', 'bet', 6.0], ['turn', 'BB', 'call', 6.0], ['river', 'BB', 'check', 0]]}
        raised = {**barrel, 'to_call_bb': 28.0, 'history': barrel['history'] + [['river', 'BTN', 'bet', 14.0], ['river', 'BB', 'raise', 42.0]]}
        no_initiative = {**FACING_RIVER_BET, 'to_call_bb': 0.0, 'history': FACING_RIVER_BET['history'][:-2]}

        self.assertEqual(spot_of(FACING_RIVER_BET), 'facing_bet')
        self.assertEqual(spot_of(barrel), 'initiative')
        self.assertEqual(spot_of(raised), 'facing_raise')
        self.assertEqual(spot_of(no_initiative), 'no_initiative')
        self.assertTrue(HandContext(barrel).had_initiative(), 'quien apostó el turn trae la iniciativa al river')

    def test_the_target_comes_from_the_river_statistics(self):
        spots = {spot.name: spot for spot in SERVICE.spots}
        prepared = SERVICE.prepare_state(FACING_RIVER_BET)

        self.assertEqual(spots['facing_bet'].target(prepared), {'fold_pct': 38.0, 'call_pct': 55.8, 'raise_pct': 6.2})
        self.assertEqual(spots['initiative'].target({**prepared, 'in_position': True}), {'bet_pct': 40.0, 'check_pct': 60.0})
        self.assertEqual(spots['facing_raise'].target(prepared), {'fold_pct': 30.0, 'continue_pct': 70.0})

    def test_the_model_sees_all_the_postflop_statistics_that_narrow_the_range(self):
        prepared = SERVICE.prepare_state(FACING_RIVER_BET)

        self.assertEqual(set(prepared['profile']['postflop']), {'flop', 'turn', 'river', 'vb_ratio'})

    def test_the_questionnaire_tells_the_story_of_the_hand_through_the_turn(self):
        client = FakeClient('{"fold": 1, "call": 1}')

        result = SERVICE.decide(FACING_RIVER_BET, 'm', ['fold', 'call'], client)

        text = client.calls[0][1][1]['content']
        self.assertEqual(result['spot'], 'facing_bet')
        self.assertIn('QUESTIONNAIRE · RIVER · FACING A BET', text)
        self.assertIn('Flop: BB check · BTN bet 3.0bb · BB call 3.0bb', text)
        self.assertIn('Turn: BB check · BTN bet 6.0bb · BB call 6.0bb', text)
        self.assertIn('River card 3s', text)
        self.assertIn('BTN: on the flop his line was bet', text)
        self.assertIn('BTN: on the turn his line was bet', text)   # el turn estrecha lo que el flop dejó
        self.assertIn('You: on the turn your line was check', text)
        self.assertIn('what you did on the earlier streets', text)
        self.assertIn('PRICE CHECK', text)
        self.assertIn('The river is the last street', text)
        self.assertNotIn('draws have one card to come', text)
        self.assertIn('Draws: none (the river is dealt', text)       # el color que no llegó ya no es un proyecto
        self.assertIn('EQUITY at showdown', text)
        self.assertNotIn('flush draw (', text)
        self.assertNotIn('(semi-bluffs)', text.split('What he bets with:')[1].split('%')[1], 'sin cartas por venir no hay semifaroles: los proyectos fallados son faroles')


if __name__ == '__main__':
    unittest.main()
