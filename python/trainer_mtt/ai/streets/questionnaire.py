"""Piezas comunes del cuestionario que se le pasa al modelo: texto de estadísticas y lectura de los hechos de la mano."""

# Cómo se reconoce a cada tipo de oponente (para que el modelo clasifique con criterio propio, sin cortes numéricos fijos)
OPPONENT_TYPES = {
    'unknown': 'there are no statistics for this opponent',
    'calling_station': 'rarely folds and rarely raises; calls with almost anything',
    'passive': 'rarely bets or raises; mostly checks and calls',
    'nit': 'tight and cautious; folds a lot and only bets or raises with real strength',
    'tight_aggressive': 'selective but aggressive when it plays a hand',
    'loose_aggressive': 'plays many hands and bets and raises often',
    'maniac': 'bets and raises with a very wide range and hardly ever folds',
    'balanced': 'no clear tendency: plays close to an average player',
}


class StatsReader:
    """Lee un perfil y lo cuenta en texto."""

    STAT_TEXT = {
        'f2bet': 'folds to a bet on this street',
        'raise_vs_bet': 'of the hands that continue against a bet, raises',
        'cbet': 'bets with the preflop initiative (continuation bet)',
        'donk': 'leads into the preflop aggressor when out of position',
        'cr': 'check-raises',
        'f2raise': 'folds to a raise of its own bet',
        'af': 'aggression factor (bets and raises per call)',
        'bet': 'bets when checked to',
    }

    @staticmethod
    def street_stats(profile, street):
        """Estadísticas de esa calle del perfil ({} si no hay; PHP manda [] cuando está vacío)."""
        postflop = (profile or {}).get('postflop') or {}

        return postflop.get(street) or {}

    @classmethod
    def lines(cls, stats, keys, indent='  '):
        lines = []
        for key in keys:
            value = stats.get(key)
            if value is not None:
                lines.append(f"{indent}- {cls.STAT_TEXT.get(key, key)}: {value}{'' if key == 'af' else '%'}")
        sample = stats.get('sample_size') or stats.get('n')
        if lines and sample:
            lines.append(f'{indent}- hands behind these numbers: {sample}')

        return lines or [f'{indent}- no statistics (unknown opponent)']

    @staticmethod
    def preflop_style(profile):
        preflop = (profile or {}).get('preflop') or {}
        parts = [f'{name} {preflop[name]}%' for name in ('vpip', 'pfr') if preflop.get(name) is not None]

        return ', '.join(parts) if parts else 'unknown'


AGGRESSIVE = ('bet', 'raise', 'allin')


class HandContext:
    """Los hechos de la mano que salen del estado: qué ha pasado, quién tiene la iniciativa, quién es el rival que importa."""

    def __init__(self, state):
        self.state = state
        self.history = [h for h in state.get('history') or [] if h]

    def street_actions(self):
        return [h for h in self.history if h[0] == self.state.get('street')]

    def bet_this_street(self):
        """¿Ya apostó o subió este jugador en la calle actual?"""
        return any(h[1] == self.state.get('position') and h[2] in AGGRESSIVE for h in self.street_actions())

    def had_initiative(self):
        """¿Fue el último que subió preflop?"""
        raises = [h for h in self.history if h[0] == 'preflop' and h[2] in ('raise', 'allin')]

        return bool(raises) and raises[-1][1] == self.state.get('position')

    def preflop_aggressor(self):
        """Posición del último que subió preflop (None si nadie)."""
        raises = [h for h in self.history if h[0] == 'preflop' and h[2] in ('raise', 'allin')]

        return raises[-1][1] if raises else None

    def rival_had_initiative(self, rival):
        return rival is not None and rival.get('position') == self.preflop_aggressor()

    def main_rival(self):
        """El rival que más importa: el que apostó o subió en la calle; si no, el último que subió preflop; si no, el primero."""
        rivals = self.state.get('rivals') or []
        if not rivals:
            return None
        by_position = {r.get('position'): r for r in rivals}

        for h in reversed(self.street_actions()):
            if h[1] != self.state.get('position') and h[2] in AGGRESSIVE and h[1] in by_position:
                return by_position[h[1]]
        for h in reversed([h for h in self.history if h[0] == 'preflop' and h[2] in ('raise', 'allin')]):
            if h[1] in by_position:
                return by_position[h[1]]

        return rivals[0]

    def history_lines(self):
        """Historial por calle en una línea cada una: «flop: BB check · BTN bet 3bb»."""
        streets = {}
        for street, position, action, amount in (h[:4] for h in self.history):
            streets.setdefault(street, []).append(f'{position} {action}' + (f' {amount}bb' if amount else ''))

        return [f"  {street}: {' · '.join(actions)}" for street, actions in streets.items()] or ['  (no actions yet)']

    def situation_lines(self):
        state = self.state
        pot, to_call, stack = state.get('pot_bb') or 0, state.get('to_call_bb') or 0, state.get('stack_bb') or 0
        lines = [
            f"  - pot {pot} bb · to call {to_call} bb · your stack {stack} bb"
            + (f" · pot odds: you need {round(100 * to_call / (pot + to_call), 1)}% equity to call" if to_call else ''),
            f"  - you are {'in position (act last)' if state.get('in_position') else 'out of position (act first)'}"
            f" · players left in the hand: {state.get('players_left', 1 + len(state.get('rivals') or []))}",
        ]
        if to_call and pot > to_call:
            lines.append(f'  - the amount you face is about {round(100 * to_call / (pot - to_call))}% of the pot before it')
        if pot:
            lines.append(f'  - stack-to-pot ratio: {round(stack / pot, 1)}')
        players = state.get('players_left', 1 + len(state.get('rivals') or []))
        if players > 2:
            lines.append(f'  - multiway pot ({players} players): the hand ranking in section 2 is against ONE opponent; against several your hand is '
                         'really weaker and bluffs work less')

        return lines
