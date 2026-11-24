import json
import unittest
from unittest import mock

from app import app
from trainer_mtt.ai.ollama import OllamaClient
from trainer_mtt.ai.streets import default_registry
from tests.test_ai_decision import FakeClient

FLOP = {'f2bet': 70, 'raise_vs_bet': 20, 'cbet': 55, 'donk': 6, 'f2raise': 30, 'af': 1.8, 'sample_size': 400}
FULL_PROFILE = {
    'preflop': {'vpip': 28, 'pfr': 20},
    'postflop': {'flop': FLOP, 'turn': {'f2bet': 40, 'bet': 50}, 'river': {'f2bet': 30}, 'vb_ratio': 1.4},
}
PREFLOP = [['preflop', 'BB', 'call', 2.5], ['preflop', 'BTN', 'raise', 2.5]]
# El jugador (BTN, en posición, con la iniciativa) se enfrenta a una apuesta del BB
FACING_BET = {
    'street': 'flop', 'position': 'BTN', 'in_position': True, 'hole_cards': ['A♠', 'K♦'], 'board': ['K♣', '7♥', '2♦'],
    'pot_bb': 6.0, 'to_call_bb': 3.0, 'stack_bb': 40.0, 'players_left': 2,
    'history': PREFLOP + [['flop', 'BB', 'bet', 3.0]], 'profile': FULL_PROFILE,
    'rivals': [{'position': 'BB', 'stack_bb': 40.0, 'profile': FULL_PROFILE}],
}
REGISTRY = default_registry()
FLOP_SERVICE = REGISTRY.service_for('flop')


class StreetServicesTest(unittest.TestCase):
    def test_only_the_streets_with_a_service_are_covered(self):
        self.assertEqual(REGISTRY.available(), ['flop', 'preflop', 'river', 'turn'])
        self.assertIsNone(REGISTRY.service_for('showdown'))

    def test_the_flop_service_only_shows_the_model_the_flop_part_of_each_profile(self):
        prepared = FLOP_SERVICE.prepare_state(FACING_BET)

        for profile in (prepared['profile'], prepared['rivals'][0]['profile']):
            self.assertEqual(set(profile['postflop']), {'flop', 'vb_ratio'})
            self.assertEqual(profile['preflop'], {'vpip': 28, 'pfr': 20})

    def test_each_thing_that_can_happen_on_the_flop_goes_to_its_own_service(self):
        raised = {**FACING_BET, 'to_call_bb': 6.0, 'history': PREFLOP + [['flop', 'BB', 'check', 0], ['flop', 'BTN', 'bet', 3.0], ['flop', 'BB', 'raise', 9.0]]}
        initiative = {**FACING_BET, 'to_call_bb': 0.0, 'history': PREFLOP + [['flop', 'BB', 'check', 0]]}
        no_initiative = {**FACING_BET, 'position': 'BB', 'in_position': False, 'to_call_bb': 0.0, 'history': PREFLOP}

        self.assertEqual(FLOP_SERVICE.spot_for(FACING_BET).name, 'facing_bet')
        self.assertEqual(FLOP_SERVICE.spot_for(raised).name, 'facing_raise')
        self.assertEqual(FLOP_SERVICE.spot_for(initiative).name, 'initiative')
        self.assertEqual(FLOP_SERVICE.spot_for(no_initiative).name, 'no_initiative')

    def test_every_spot_takes_its_target_from_the_stat_that_belongs_to_it(self):
        prepared = FLOP_SERVICE.prepare_state(FACING_BET)
        by_name = {spot.name: spot for spot in FLOP_SERVICE.spots}

        self.assertEqual(by_name['facing_bet'].target(prepared), {'fold_pct': 70.0, 'call_pct': 24.0, 'raise_pct': 6.0})
        self.assertEqual(by_name['facing_raise'].target(prepared), {'fold_pct': 30.0, 'continue_pct': 70.0})
        self.assertEqual(by_name['initiative'].target(prepared), {'bet_pct': 55.0, 'check_pct': 45.0})
        self.assertEqual(by_name['no_initiative'].target({**prepared, 'in_position': False, 'history': PREFLOP}), {'bet_pct': 6.0, 'check_pct': 94.0})
        self.assertEqual(by_name['no_initiative'].target(prepared), {})  # en posición: no hay estadística directa

    def test_the_initiative_uses_the_in_or_out_of_position_split_when_the_profile_has_it(self):
        split = {**FULL_PROFILE, 'postflop': {'flop': {**FLOP, 'ip': {'agg': {'bet': 80.0}}, 'oop': {'agg': {'bet': 40.0}}}}}
        state = FLOP_SERVICE.prepare_state({**FACING_BET, 'profile': split, 'to_call_bb': 0.0, 'history': PREFLOP})
        initiative = {spot.name: spot for spot in FLOP_SERVICE.spots}['initiative']

        self.assertEqual(initiative.target(state), {'bet_pct': 80.0, 'check_pct': 20.0})
        self.assertEqual(initiative.target({**state, 'in_position': False}), {'bet_pct': 40.0, 'check_pct': 60.0})

    def test_the_initiative_uses_position_and_heads_up_or_multiway_when_the_profile_has_enough_hands(self):
        flop = {**FLOP, 'ip': {'agg': {'bet': 70.0, 'n': 100}, 'agg_hu': {'bet': 77.0, 'n': 80}, 'agg_multi': {'bet': 54.0, 'n': 20}},
                'oop': {'agg': {'bet': 52.0, 'n': 60}, 'agg_multi': {'bet': 46.0, 'n': 40}}}
        base = FLOP_SERVICE.prepare_state({**FACING_BET, 'profile': {**FULL_PROFILE, 'postflop': {'flop': flop}}, 'to_call_bb': 0.0, 'history': PREFLOP})
        initiative = {spot.name: spot for spot in FLOP_SERVICE.spots}['initiative']

        self.assertEqual(initiative.target({**base, 'players_left': 2})['bet_pct'], 77.0)                        # IP heads-up: celda de 80 manos
        self.assertEqual(initiative.target({**base, 'players_left': 3})['bet_pct'], 70.0)                        # IP multiway: solo 20 manos, baja a la posición
        self.assertEqual(initiative.target({**base, 'in_position': False, 'players_left': 3})['bet_pct'], 46.0)  # OOP multiway: celda de 40 manos
        self.assertEqual(initiative.target({**base, 'in_position': False, 'players_left': 2})['bet_pct'], 52.0)  # OOP heads-up: sin celda, la posición

    def test_an_all_in_counts_as_aggression_for_the_initiative_and_for_a_raised_bet(self):
        shoved_preflop = {**FACING_BET, 'to_call_bb': 0.0, 'history': [['preflop', 'BB', 'call', 2.5], ['preflop', 'BTN', 'allin', 25.0]]}
        shoved_flop = {**FACING_BET, 'to_call_bb': 6.0, 'history': PREFLOP + [['flop', 'BTN', 'allin', 8.0], ['flop', 'BB', 'raise', 20.0]]}

        self.assertEqual(FLOP_SERVICE.spot_for(shoved_preflop).name, 'initiative')
        self.assertEqual(FLOP_SERVICE.spot_for(shoved_flop).name, 'facing_raise')

    def test_the_situation_tells_the_size_of_the_bet_and_when_the_pot_is_multiway(self):
        client = FakeClient('{"opponent_type": "nit", "my_situation": "ahead", "fold": 1, "call": 1}')

        FLOP_SERVICE.decide({**FACING_BET, 'players_left': 3}, 'm', ['fold', 'call'], client)

        text = client.calls[0][1][1]['content']
        self.assertIn('about 100% of the pot before it', text)  # 3 bb sobre un bote de 6 bb que ya los incluye: 3 de 3
        self.assertIn('multiway pot (3 players)', text)

    def test_without_the_stat_there_is_no_target(self):
        bare = FLOP_SERVICE.prepare_state({**FACING_BET, 'profile': {}})

        self.assertEqual(FLOP_SERVICE.spot_for(bare).target(bare), {})

    def test_the_questionnaire_is_filled_with_the_opponent_the_hand_and_the_profile(self):
        client = FakeClient('{"opponent_type": "nit", "my_situation": "ahead", "fold": 1, "call": 1}')

        result = FLOP_SERVICE.decide(FACING_BET, 'm', ['fold', 'call'], client)

        text = client.calls[0][1][1]['content']
        self.assertEqual(result['spot'], 'facing_bet')
        self.assertIn('QUESTIONNAIRE · FLOP · FACING A BET', text)
        self.assertIn('Main opponent: BB', text)
        self.assertIn('continuation bet', text)                       # estadística del rival
        self.assertIn('Made hand: top pair of kings, ace kicker', text)  # herramienta de manos
        self.assertIn("Against the opponent's likely range", text)      # estrechado por su apuesta (donk 6 %)
        self.assertIn('That range is only the hands he would bet with: 6.0% of his range', text)
        self.assertIn('folds to a bet on this street: 70%', text)       # perfil propio
        self.assertIn('fold 70.0%, call 24.0%, raise 6.0%', text)
        self.assertIn('pot odds', text)
        self.assertNotIn('"turn"', text)
        self.assertEqual(list(client.calls[0][2]['properties']), ['fold', 'call'])   # el modelo solo reparte probabilidades: todo lo demás son cálculos de herramientas
        self.assertIn('Type of player (computed from his statistics)', text)
        self.assertIn('Where you stand (computed)', text)
        self.assertNotIn('Question "', text)

    def test_an_opponent_without_statistics_is_played_against_all_the_hands(self):
        client = FakeClient('{"opponent_type": "unknown", "my_situation": "ahead", "fold": 1, "call": 1}')
        unknown = {**FACING_BET, 'rivals': [{'position': 'BB', 'stack_bb': 40.0, 'profile': {}}]}

        FLOP_SERVICE.decide(unknown, 'm', ['fold', 'call'], client)

        text = client.calls[0][1][1]['content']
        self.assertIn('Against all 1081 possible opponent hands', text)
        self.assertIn('Read of this opponent: unknown', text)

    def test_a_service_refuses_a_state_from_another_street(self):
        with self.assertRaises(ValueError):
            FLOP_SERVICE.decide({**FACING_BET, 'street': 'turn'}, 'm', ['fold'], FakeClient('{}'))


class StreetEndpointsTest(unittest.TestCase):
    def setUp(self):
        self.client = app.test_client()

    def test_an_uncovered_street_answers_501(self):
        response = self.client.post('/ai/decide', json={'state': {**FACING_BET, 'street': 'showdown'}, 'model': 'm'})

        self.assertEqual(response.status_code, 501)
        self.assertEqual(response.get_json()['streets'], ['flop', 'preflop', 'river', 'turn'])

    def test_it_lists_the_covered_streets(self):
        self.assertEqual(self.client.get('/ai/streets').get_json(), {'streets': ['flop', 'preflop', 'river', 'turn']})

    def test_a_flop_state_is_decided(self):
        answer = json.dumps({'opponent_type': 'nit', 'my_situation': 'ahead', 'fold': 1, 'call': 3})
        with mock.patch.object(OllamaClient, 'chat', return_value=answer):
            response = self.client.post('/ai/decide', json={'state': FACING_BET, 'model': 'm', 'legal_actions': ['fold', 'call']})

        self.assertEqual(response.get_json()['distribution'], {'fold': 0.25, 'call': 0.75})


if __name__ == '__main__':
    unittest.main()
