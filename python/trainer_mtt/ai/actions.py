"""Vocabulario de acciones que el modelo puede repartirse (el contrato con Laravel: App\\Services\\Ai\\DecisionContract)."""

ACTIONS = [
    'fold', 'check', 'call',
    'open_2bb', 'open_2_5bb', 'open_3bb', 'raise_x2_2', 'raise_x3', 'raise_x4',
    'bet_third', 'bet_half', 'bet_twothirds', 'bet_pot', 'bet_overbet',
    'allin',
]

ACTION_HELP = {
    'fold': 'give up the hand', 'check': 'pass without betting', 'call': 'match the current bet',
    'bet_third': 'bet or raise about 1/3 of the pot', 'bet_half': 'bet or raise about 1/2 of the pot',
    'bet_twothirds': 'bet or raise about 2/3 of the pot', 'bet_pot': 'bet or raise about the pot',
    'bet_overbet': 'bet or raise about 1.5x the pot', 'allin': 'put all the chips in',
    'open_2bb': 'open to 2 big blinds', 'open_2_5bb': 'open to 2.5 big blinds', 'open_3bb': 'open to 3 big blinds',
    'raise_x2_2': 'raise to 2.2x the current bet', 'raise_x3': 'raise to 3x the current bet', 'raise_x4': 'raise to 4x the current bet',
}
