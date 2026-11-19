import json
import unittest
from unittest import mock

import ai_decision
from app import app

STATE = {
    'street': 'flop', 'position': 'BTN', 'hole_cards': ['A♠', 'K♦'], 'board': ['K♣', '7♥', '2♦'],
    'pot_bb': 6.0, 'to_call_bb': 3.0, 'stack_bb': 40.0,
    'profile': {'postflop': {'flop': {'fold_to_cbet': 55}}},
}


class FakeClient:
    def __init__(self, answer):
        self.answer = answer
        self.calls = []

    def chat(self, model, messages, schema, seed=None):
        self.calls.append((model, messages, schema, seed))
        return self.answer


class AiDecisionTest(unittest.TestCase):
    def test_prompt_carries_the_state_the_profile_and_only_the_allowed_actions(self):
        messages = ai_decision.build_messages(STATE, ['fold', 'call', 'bet_half'])
        user = messages[1]['content']

        self.assertIn('fold_to_cbet', user)
        self.assertIn('A♠', user)
        self.assertIn('- bet_half:', user)
        self.assertNotIn('- allin:', user)

    def test_schema_requires_exactly_the_allowed_actions(self):
        schema = ai_decision.response_schema(['fold', 'call'])

        self.assertEqual(set(schema['properties']), {'fold', 'call'})
        self.assertEqual(schema['required'], ['fold', 'call'])
        self.assertFalse(schema['additionalProperties'])

    def test_answer_is_normalised_and_drops_illegal_or_invalid_values(self):
        text = json.dumps({'fold': 2, 'call': 6, 'allin': 5, 'bet_half': -1})

        dist = ai_decision.parse_distribution(text, ['fold', 'call', 'bet_half'])

        self.assertEqual(dist, {'fold': 0.25, 'call': 0.75})
        self.assertAlmostEqual(sum(dist.values()), 1.0)

    def test_an_answer_without_any_usable_probability_is_an_error(self):
        with self.assertRaises(ValueError):
            ai_decision.parse_distribution('{"fold": 0, "call": 0}', ['fold', 'call'])
        with self.assertRaises(ValueError):
            ai_decision.parse_distribution('no es json', ['fold'])

    def test_decide_sends_the_seed_and_returns_the_distribution(self):
        client = FakeClient('{"fold": 0.4, "call": 0.6}')

        result = ai_decision.decide(STATE, 'qwen3:8b', ['fold', 'call'], client, seed=7)

        self.assertEqual(result, {'model': 'qwen3:8b', 'distribution': {'fold': 0.4, 'call': 0.6}})
        self.assertEqual(client.calls[0][0], 'qwen3:8b')
        self.assertEqual(client.calls[0][3], 7)


class AiEndpointsTest(unittest.TestCase):
    def setUp(self):
        self.client = app.test_client()

    def test_decide_rejects_a_request_without_state_or_model(self):
        self.assertEqual(self.client.post('/ai/decide', json={'state': {}}).status_code, 400)

    def test_decide_returns_the_distribution(self):
        with mock.patch.object(ai_decision.OllamaClient, 'chat', return_value='{"fold": 1, "call": 1}'):
            response = self.client.post('/ai/decide', json={'state': STATE, 'model': 'qwen3:8b', 'legal_actions': ['fold', 'call']})

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.get_json()['distribution'], {'fold': 0.5, 'call': 0.5})

    def test_it_reports_when_ollama_is_not_running(self):
        with mock.patch.object(ai_decision.OllamaClient, 'models', side_effect=ConnectionError('no responde')):
            response = self.client.get('/ai/health')

        self.assertEqual(response.status_code, 503)
        self.assertEqual(response.get_json()['status'], 'unavailable')


if __name__ == '__main__':
    unittest.main()
