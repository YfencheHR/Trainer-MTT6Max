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
