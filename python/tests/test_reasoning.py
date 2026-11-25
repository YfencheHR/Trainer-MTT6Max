import json
import unittest

from trainer_mtt.ai.answer import ActionAnswer
from trainer_mtt.ai.decider import ActionDecider


class RecordingClient:
    def __init__(self, answer):
        self.answer = answer
        self.calls = []

    def chat(self, model, messages, schema, seed=None, think=False):
        self.calls.append({'messages': messages, 'schema': schema, 'think': think})
        return self.answer


LEGAL = ['fold', 'call']
MESSAGES = [{'role': 'system', 'content': 'S'}, {'role': 'user', 'content': 'Cuestionario'}]


class ReasoningModesTest(unittest.TestCase):
    def decide(self, state, answer='{"fold": 0.4, "call": 0.6}'):
        client = RecordingClient(answer)
        result = ActionDecider(client).decide(state, 'm', LEGAL, 1, messages=MESSAGES)

        return result, client.calls[0]

    def test_by_default_it_is_a_direct_answer(self):
        result, call = self.decide({'street': 'river'})

        self.assertEqual(result['distribution'], {'fold': 0.4, 'call': 0.6})
        self.assertNotIn('analysis', call['schema']['properties'])
        self.assertFalse(call['think'])
        self.assertEqual(call['messages'], MESSAGES)

    def test_brief_reasoning_asks_for_an_analysis_before_the_probabilities_and_ignores_it_when_reading(self):
        answer = json.dumps({'analysis': 'Tiende a pagar y el precio es bueno.', 'fold': 0.3, 'call': 0.7})

        result, call = self.decide({'street': 'river', 'reasoning': 'brief'}, answer)

        self.assertEqual(list(call['schema']['properties'])[0], 'analysis', 'el análisis va ANTES de las probabilidades')
        self.assertIn('analysis', call['schema']['required'])
        self.assertIn('short analysis', call['messages'][-1]['content'])
        self.assertTrue(call['messages'][-1]['content'].startswith('Cuestionario'))
        self.assertEqual(MESSAGES[-1]['content'], 'Cuestionario', 'no se modifica el cuestionario original')
        self.assertEqual(result['distribution'], {'fold': 0.3, 'call': 0.7})
        self.assertFalse(call['think'])

    def test_think_mode_turns_on_the_model_reasoning_without_changing_the_questionnaire(self):
        _, call = self.decide({'street': 'river', 'reasoning': 'think'})

        self.assertTrue(call['think'])
        self.assertEqual(call['messages'], MESSAGES)
        self.assertNotIn('analysis', call['schema']['properties'])

    def test_the_schema_helper_puts_the_analysis_first(self):
        schema = ActionAnswer.schema(LEGAL, {'plan': ['a', 'b']}, analysis=True)

        self.assertEqual(list(schema['properties']), ['analysis', 'plan', 'fold', 'call'])


if __name__ == '__main__':
    unittest.main()
