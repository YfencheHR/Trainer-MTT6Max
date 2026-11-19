"""Mide si la IA sigue el perfil: media de probabilidades sobre varias manos para f2bet alto y bajo."""
import sys
import streets
from ai_decision import OllamaClient

HANDS = [['7♠', '2♦'], ['9♣', '4♦'], ['J♠', 'T♠'], ['A♦', '5♣'], ['Q♥', 'J♦'], ['8♠', '8♦'], ['K♠', 'Q♦'], ['A♠', 'K♦'], ['7♣', '7♦'], ['6♥', '5♥']]
model = sys.argv[1]
service = streets.service_for('flop')
client = OllamaClient()
for f2bet in (75, 25):
    profile = {'preflop': {'vpip': 25, 'pfr': 18}, 'postflop': {'flop': {'f2bet': f2bet, 'raise_vs_bet': 15, 'af': 1.5}, 'vb_ratio': 2}}
    totals = {}
    for hand in HANDS:
        state = {'street': 'flop', 'position': 'BTN', 'in_position': True, 'hole_cards': hand, 'board': ['K♣', '7♥', '2♦'],
                 'pot_bb': 6.0, 'to_call_bb': 3.0, 'stack_bb': 40.0, 'players_left': 2, 'raises_this_street': 1,
                 'history': [['preflop', 'BB', 'call', 2.5], ['flop', 'BB', 'bet', 3.0]],
                 'profile': profile, 'rivals': [{'position': 'BB', 'profile': {}}]}
        d = service.decide(state, model, ['fold', 'call', 'bet_twothirds', 'allin'], client, 1)['distribution']
        print(f2bet, hand, {k: round(v, 2) for k, v in d.items()}, flush=True)
        for k, v in d.items():
            totals[k] = totals.get(k, 0) + v / len(HANDS)
    print('MEDIA f2bet', f2bet, {k: round(v, 2) for k, v in totals.items()}, flush=True)
