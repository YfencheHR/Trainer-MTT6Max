"""Cuestionario de ejemplo del turn (herramienta de desarrollo): python -m tools.smoke_turn"""

import time

from trainer_mtt.ai.streets import default_registry
from trainer_mtt.poker.preflop_hand import PreflopHand

HAND = PreflopHand()
FLOP = {'f2bet': 45, 'raise_vs_bet': 12, 'cbet': 68, 'donk': 8, 'af': 2.1, 'cr': 7, 'sample_size': 600}
TURN = {'f2bet': 42, 'raise_vs_bet': 14, 'bet': 52, 'af': 2.0, 'cr': 6, 'f2raise': 35, 'sample_size': 500}
PROFILE = {'preflop': {'vpip': 30, 'pfr': 22}, 'postflop': {'flop': FLOP, 'turn': TURN, 'vb_ratio': 1.8}}

state = {
    'street': 'turn', 'position': 'BB', 'in_position': False, 'hole_cards': ['Q♥', 'J♥'], 'board': ['K♣', '7♥', '2♦', '9♥'],
    'pot_bb': 12.0, 'to_call_bb': 6.0, 'stack_bb': 80.0, 'players_left': 2, 'adaptation': 1.0,
    'history': [
        ['preflop', 'BTN', 'raise', 2.5], ['preflop', 'SB', 'fold', 0], ['preflop', 'BB', 'call', 1.5],
        ['flop', 'BB', 'check', 0], ['flop', 'BTN', 'bet', 3.0], ['flop', 'BB', 'call', 3.0],
        ['turn', 'BB', 'check', 0], ['turn', 'BTN', 'bet', 6.0],
    ],
    'profile': PROFILE,
    'range': HAND.top_range(40).weights,
    'rivals': [{'position': 'BTN', 'stack_bb': 80.0, 'profile': PROFILE, 'range': HAND.top_range(38).weights}],
}

service = default_registry().service_for('turn')
prepared = service.prepare_state(state)
spot = service.spot_for(prepared)
started = time.time()
messages, _ = spot.prompt(prepared, ['fold', 'call', 'bet_half', 'bet_pot', 'allin'])
print(spot.name, f'(cuestionario en {time.time() - started:.1f} s)\n')
print(messages[1]['content'])
