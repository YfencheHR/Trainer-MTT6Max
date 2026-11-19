import json
import unittest
from unittest import mock

import ai_decision
import streets
from app import app
from tests.test_ai_decision import FakeClient

FULL_PROFILE = {
    'preflop': {'vpip': 28, 'pfr': 20},
    'postflop': {
        'flop': {'f2bet': 70, 'cbet': 55}, 'turn': {'f2bet': 40, 'bet': 50}, 'river': {'f2bet': 30}, 'vb_ratio': 1.4,
    },
}
STATE = {
    'street': 'flop', 'position': 'BTN', 'in_position': True, 'hole_cards': ['A♠', 'K♦'], 'board': ['K♣', '7♥', '2♦'],
    'pot_bb': 6.0, 'to_call_bb': 3.0, 'stack_bb': 40.0, 'profile': FULL_PROFILE,
    'rivals': [{'position': 'BB', 'profile': FULL_PROFILE}],
}


class StreetServicesTest(unittest.TestCase):
    def test_only_the_flop_has_a_service_for_now(self):
        self.assertEqual(streets.available(), ['flop'])
        self.assertIsNone(streets.service_for('turn'))

    def test_the_flop_service_only_shows_the_model_the_flop_part_of_each_profile(self):
        prepared = streets.service_for('flop').prepare_state(STATE)

        for profile in (prepared['profile'], prepared['rivals'][0]['profile']):
            self.assertEqual(set(profile['postflop']), {'flop', 'vb_ratio'})
            self.assertEqual(profile['preflop'], {'vpip': 28, 'pfr': 20})

    def test_this_decision_names_the_stat_that_applies_with_its_target_percentages(self):
        service = streets.service_for('flop')
        profile = {'preflop': {}, 'postflop': {'flop': {'f2bet': 75, 'raise_vs_bet': 20, 'cbet': 60, 'donk': 8}}}
        facing = service.this_decision({'profile': profile, 'to_call_bb': 3, 'history': [], 'position': 'BTN'})
        self.assertEqual(facing, {'spot': 'facing a flop bet', 'stat': 'f2bet', 'fold_pct': 75.0, 'raise_pct': 5.0, 'call_pct': 20.0})

        history = [['preflop', 'BTN', 'raise', 2.5], ['preflop', 'BB', 'call', 2.5]]
        first = {'profile': profile, 'to_call_bb': 0, 'history': history, 'position': 'BTN', 'in_position': True}
        self.assertEqual(service.this_decision(first)['bet_pct'], 60.0)
        out = {**first, 'position': 'BB', 'in_position': False}
        self.assertEqual(service.this_decision(out)['stat'], 'donk')

    def test_the_flop_asks_for_the_hand_strength_first_and_the_answer_ignores_it(self):
        schema = ai_decision.response_schema(['fold', 'call'], assess=True)
        self.assertEqual(list(schema['properties'])[0], 'hand_strength')
        self.assertEqual(ai_decision.parse_distribution('{"hand_strength":"monster","fold":0,"call":2}', ['fold', 'call']), {'call': 1.0})

    def test_without_the_stat_there_is_no_target(self):
        self.assertEqual(streets.service_for('flop').this_decision({'profile': {}, 'to_call_bb': 3}), {})

    def test_the_flop_instructions_travel_with_the_prompt(self):
        client = FakeClient('{"fold": 1, "call": 1}')

        streets.service_for('flop').decide(STATE, 'm', ['fold', 'call'], client)

        system = client.calls[0][1][0]['content']
        self.assertIn('STREET: FLOP', system)
        self.assertIn('f2bet', system)
        self.assertNotIn('"turn"', client.calls[0][1][1]['content'])

    def test_a_service_refuses_a_state_from_another_street(self):
        with self.assertRaises(ValueError):
            streets.service_for('flop').decide({**STATE, 'street': 'turn'}, 'm', ['fold'], FakeClient('{}'))


class StreetEndpointsTest(unittest.TestCase):
    def setUp(self):
        self.client = app.test_client()

    def test_an_uncovered_street_answers_501(self):
        response = self.client.post('/ai/decide', json={'state': {**STATE, 'street': 'turn'}, 'model': 'm'})

        self.assertEqual(response.status_code, 501)
        self.assertEqual(response.get_json()['streets'], ['flop'])

    def test_it_lists_the_covered_streets(self):
        self.assertEqual(self.client.get('/ai/streets').get_json(), {'streets': ['flop']})

    def test_a_flop_state_is_decided(self):
        with mock.patch.object(ai_decision.OllamaClient, 'chat', return_value=json.dumps({'fold': 1, 'call': 3})):
            response = self.client.post('/ai/decide', json={'state': STATE, 'model': 'm', 'legal_actions': ['fold', 'call']})

        self.assertEqual(response.get_json()['distribution'], {'fold': 0.25, 'call': 0.75})


if __name__ == '__main__':
    unittest.main()
