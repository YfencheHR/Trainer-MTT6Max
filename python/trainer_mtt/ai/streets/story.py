"""Historia entre calles: el rango con el que cada jugador llega a la calle actual, estrechado por lo que hizo en las anteriores.

El rango preflop (de las tablas del jugador) dice con qué llegó al flop; lo que hizo en el flop dice con qué sigue al turn, y lo que hizo en el
turn, con qué sigue al river. Esta pieza aplica las acciones de cada calle jugada (apostar, pasar, pagar, subir) a los dos rangos, el propio y el del
rival principal, con las estadísticas de esa calle de cada perfil. Todo es cálculo de herramientas (poker/range_story.py): el cuestionario solo
cuenta el resultado.
"""

from itertools import combinations

from trainer_mtt.ai.streets.questionnaire import StatsReader
from trainer_mtt.poker.cards import Deck
from trainer_mtt.poker.range_story import AGGRESSIVE, RangeStory

BOARD_SIZE = {'flop': 3, 'turn': 4}
ORDER = ('preflop', 'flop', 'turn', 'river')


class StreetsStory:
    def __init__(self, state, context, rival, hole, board):
        self.state, self.context, self.rival = state, context, rival
        self.hole, self.board = hole, board
        self.history = [h for h in state.get('history') or [] if h]
        current = ORDER.index(state.get('street'))
        # Calles anteriores a la actual que se jugaron y tienen su board completo
        self.streets = [s for s in ('flop', 'turn') if ORDER.index(s) < current and any(h[0] == s for h in self.history)]

    def applies(self):
        """Solo hay historia que contar si alguna calle anterior se jugó."""
        return bool(self.streets)

    def through(self, own_range, arrival):
        """(rango propio, rango del rival, líneas de texto) después de lo que cada uno hizo en las calles anteriores."""
        mine, rival_range, notes = own_range, arrival, []
        for street in self.streets:
            mine, rival_range, found = self._street(street, mine, rival_range)
            notes += found

        return mine, rival_range, notes

    def _initiative(self, street, position):
        """¿Traía esa posición la iniciativa a esa calle? La trae quien apostó o subió en la última calle anterior con agresión."""
        earlier = ORDER[:ORDER.index(street)]
        before = [h for h in self.history if h[0] in earlier and h[2] in AGGRESSIVE]

        return bool(before) and before[-1][1] == position

    def _street(self, street, own_range, arrival):
        board = self.board[:BOARD_SIZE[street]]
        history = [h for h in self.history if h[0] == street]
        story = RangeStory(board)
        everything = [(pair, 1.0) for pair in combinations(Deck.without(board), 2)]
        own_combos = own_range.combos(dead=board) if own_range else []
        rival_combos = (arrival.combos(dead=board + self.hole) if arrival else []) or \
            [(pair, 1.0) for pair in combinations(Deck.without(board + self.hole), 2)]
        notes = []

        rival_range = arrival
        if self.rival is not None:
            position = self.rival.get('position')
            kinds = RangeStory.kinds(history, position)
            if kinds:
                profile = self.rival.get('profile') or {}
                table = story.table(own_combos or everything)
                narrowed, steps = story.narrow(rival_combos, table, kinds, StatsReader.street_stats(profile, street),
                                               self._initiative(street, position), (profile.get('postflop') or {}).get('vb_ratio'))
                if steps:
                    rival_range = narrowed
                    notes.append(self._note(street, position, 'his', rival_combos, narrowed, steps))

        mine = own_range
        position = self.state.get('position')
        kinds = RangeStory.kinds(history, position)
        if own_range and kinds:
            profile = self.state.get('profile') or {}
            table = story.table(rival_combos)
            narrowed, steps = story.narrow(own_combos, table, kinds, StatsReader.street_stats(profile, street),
                                           self._initiative(street, position), (profile.get('postflop') or {}).get('vb_ratio'))
            if steps:
                mine = narrowed
                notes.append(self._note(street, 'you', 'your', own_combos, narrowed, steps))

        return mine, rival_range, notes

    @staticmethod
    def _note(street, who, whose, before, after, steps):
        share = lambda combos: round(100 * sum(w for _, w in combos) / 1326, 1)
        label = who.capitalize() if who == 'you' else who
        return (f"{label}: on the {street} {whose} line was {' then '.join(steps)}. "
                f"That narrows {whose} range from {share(before)}% to {share(after.combos())}% of all hands.")
