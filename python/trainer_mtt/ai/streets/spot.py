"""Situación de una calle: un servicio independiente por cada cosa que puede estar pasando en la mano.

Cada situación (p. ej. «el flop ante una apuesta») tiene su propio cuestionario: qué estadísticas del perfil importan, qué se mira del rival y qué criterio
se le da al modelo. El cuestionario se rellena con los datos de la mano (perfil propio, rivales, análisis exacto de la mano) y el modelo contesta las
preguntas y da las probabilidades. Para añadir una situación: una clase nueva que herede de Spot y registrarla en el servicio de la calle.

Además de lo que el perfil hace en general, la situación se ADAPTA al rival concreto (ver opponent.py): contra un rival que sube y farolea mucho se
continúa más y se atrapa con las manos fuertes; contra uno selectivo se foldea más. Cuánto se adapta depende de la habilidad del bot (`adaptation`).
"""

import os
from itertools import combinations

from trainer_mtt.ai.actions import ACTION_HELP
from trainer_mtt.ai.streets.opponent import OpponentRead
from trainer_mtt.ai.streets.questionnaire import OPPONENT_TYPES, HandContext, StatsReader
from trainer_mtt.ai.streets.story import StreetsStory
from trainer_mtt.poker.blockers import Blockers
from trainer_mtt.poker.board_story import BoardStory
from trainer_mtt.poker.cards import Card, Deck
from trainer_mtt.poker.hand_analyzer import HandAnalyzer
from trainer_mtt.poker.hand_range import HandRange
from trainer_mtt.poker.hand_story import HandStory
from trainer_mtt.poker.range_composition import RangeComposition
from trainer_mtt.poker.range_advantage import RangeAdvantage
from trainer_mtt.poker.range_narrowing import RangeNarrower
from trainer_mtt.poker.range_scorer import RangeScorer, RangeSplit

# De la acción más fuerte a la más débil: así se reparte un rango ordenado por fuerza
ZONE_ORDER = {'raise': 0, 'bet': 0, 'call': 1, 'continue': 1, 'check': 2, 'fold': 3}
# Hasta qué distancia (en puntos del rango) del límite entre dos zonas se considera que la mano está «en la frontera»
BOUNDARY_POINTS = 8
# Adaptación al rival (puntos del rango con adaptación y lectura completas, contra un rival en el extremo)
CONTINUE_SHIFT = 15   # ante una apuesta o subida: continuar más contra quien farolea, foldear más contra quien es selectivo
TRAP_MAX = 25         # sin apuesta: las manos más fuertes pasan para atrapar a quien apuesta o sube contra un check
BET_CUT = 0.4         # sin apuesta: la apuesta se recorta (por abajo, los faroles) hasta este % contra quien sube
BLUFF_SHIFT = 15      # sin apuesta: más faroles contra quien foldea mucho, menos contra quien no foldea

SYSTEM = (
    "You are the decision engine of a poker bot in a No-Limit Hold'em tournament. The bot is NOT a perfect player: it is a specific player whose "
    "personality is its profile (statistics per situation). Decide what THIS player would do, not what is optimal; it may make mistakes, like a person. "
    "You receive a questionnaire already filled in: every number in it (equity, outs, ranges, pot odds, opponent read, zone of the range) was computed "
    "exactly by tools, so do not recompute anything. Your only job is to DECIDE with those results: give the probability (0 to 1) of each allowed action, "
    "summing to 1. Reply ONLY with that JSON."
)

SITUATIONS = {
    'far_ahead': 'you beat almost everything the opponent can have',
    'ahead': 'you beat more than you lose to',
    'marginal': 'about even; a showdown hand',
    'behind': 'you lose to more than you beat',
    'far_behind': 'you lose to almost everything and have no real draw',
    'drawing': 'behind now, but with a strong draw',
}


class Spot:
    name = ''
    title = ''            # «FACING A BET»
    own_stats = ()        # estadísticas del perfil propio que importan aquí
    rival_stats = ()      # estadísticas de los rivales que importan aquí
    guidance = ''         # criterio para decidir en esta situación

    def __init__(self, analyzer=None):
        self.analyzer = analyzer or HandAnalyzer()

    def matches(self, state):
        raise NotImplementedError

    def target(self, state):
        """% que el perfil dice que hace en esta situación con TODAS sus manos: {'fold_pct':…, 'call_pct':…}. Vacío si no hay dato."""
        return {}

    def shown_frequency(self, read, context, rival):
        """% de veces que el rival hace la acción a la que nos enfrentamos (apostar, subir). None si no nos enfrentamos a una acción suya."""
        return None

    def shown_verb(self):
        return 'bet'

    def adapt(self, zones, read, k):
        """(zonas adaptadas al rival, notas): por defecto no se adapta."""
        return zones, []

    def form(self):
        """Preguntas que el modelo contesta antes de las probabilidades. Ninguna: todo cálculo (tipo de rival, cómo está la mano) lo hacen las herramientas."""
        return {}

    def zones(self, state):
        """[(acción, % del rango)] de la acción más fuerte a la más débil, a partir de lo que el perfil hace en esta situación."""
        zones = [(key[:-4], value) for key, value in self.target(state).items() if key.endswith('_pct') and value > 0]

        return sorted(zones, key=lambda zone: ZONE_ORDER.get(zone[0], 1))

    def prompt(self, state, legal):
        """(mensajes, formulario) que se mandan al modelo."""
        return [{'role': 'system', 'content': SYSTEM}, {'role': 'user', 'content': self.questionnaire(state, legal)}], self.form()

    def questionnaire(self, state, legal):
        street = state['street']
        context = HandContext(state)
        rival = context.main_rival()
        read = OpponentRead(rival, street)
        k = max(0.0, min(1.0, float(state.get('adaptation', 1.0)))) * read.confidence
        hole = [Card.from_string(card) for card in state['hole_cards']]
        board = [Card.from_string(card) for card in state['board']]
        own_range = HandRange(state.get('range'))
        arrival = HandRange((rival or {}).get('range'))
        story_lines, own_range, arrival = self._story(state, context, rival, own_range, arrival, hole, board)
        shown, frequency = self._shown_range(context, rival, read, arrival, own_range, hole, board)
        analysis = self.analyzer.analyze(state['hole_cards'], state['board'], shown or arrival)
        stats = StatsReader.street_stats(state.get('profile'), street)

        lines = [f'QUESTIONNAIRE · {street.upper()} · {self.title}', ''] + story_lines + ['']
        lines += ['2. WHO IS THE OPPONENT']
        lines += self._rivals_section(state, rival, read)
        kind = read.type()
        lines += [f'   Type of player (computed from his statistics): {kind.upper()} - {OPPONENT_TYPES[kind]}.']
        lines += ['', f'3. YOUR HAND (exact, computed by a tool): {" ".join(state["hole_cards"])} on board {" ".join(state["board"])}']
        lines += [f'   {line}' for line in self.analyzer.describe(analysis)]
        lines += self._price_check(state, analysis)
        if shown:
            lines.append(f'   (That range is only the hands he would {self.shown_verb()} with: {frequency}% of his range.)')
            lines += self._composition_lines(shown, hole, board)
            if 'blockers' not in os.environ.get('AI_TOOLS_OFF', ''):   # interruptor del experimento (medir cada herramienta por separado)
                lines += Blockers(board).lines(shown.combos(dead=board), hole)
        standing = self.analyzer.situation(analysis)
        lines += [f'   Where you stand (computed): {standing.upper()} - {SITUATIONS[standing]}.']
        lines += ['', f'4. YOUR PROFILE IN THIS SPOT (you play as this player). Preflop style: {StatsReader.preflop_style(state.get("profile"))}']
        lines += StatsReader.lines(stats, self.own_stats)
        lines += self._target_section(state)
        zones, notes = self.adapt(self.zones(state), read, k)
        lines += [f'  ADAPTATION to this opponent (your skill and the reliability of the read apply): {note}' for note in notes]
        range_lines = self._range_section(state, own_range, shown or arrival, hole, board, zones)
        lines += range_lines
        if own_range and arrival and street != 'preflop' and 'advantage' not in os.environ.get('AI_TOOLS_OFF', ''):
            lines += RangeAdvantage(board).lines(own_range.combos(dead=board), arrival.combos(dead=board))
        lines += ['', '5. THE SITUATION'] + context.situation_lines() + ['  Actions so far:'] + [f'  {line}' for line in context.history_lines()]
        lines += ['', '6. HOW TO DECIDE', self.guidance]
        if range_lines:
            lines.append(
                '- Decide with ALL the results above, they are computed for you: your equity against the price of the call, what he bets with (value, draws, '
                'pure bluffs), the read of this opponent and the zone of your range, which shows what this player usually does with a hand like this one. '
                f'When they disagree, or your hand is within about {BOUNDARY_POINTS} points of a boundary, mix the neighbouring actions. '
                'Because it is a person, never give exactly 0 or 1 unless it is total air or a monster.'
            )
        lines += ['', 'ALLOWED ACTIONS:'] + [f'  - {a}: {ACTION_HELP.get(a, a)}' for a in legal]
        lines += ['', 'Return the JSON with the probability of each allowed action.']

        return '\n'.join(lines)

    # ------------------------------------------------------------------ adaptación

    @staticmethod
    def move(zones, source, target, points):
        """Pasa `points` puntos del rango de una zona a otra, sin pasarse de lo que tiene la de origen."""
        amounts = dict(zones)
        if source not in amounts or target not in amounts:
            return zones
        moved = max(0.0, min(points, amounts[source]))
        amounts[source] -= moved
        amounts[target] += moved

        return [(name, round(amounts[name], 1)) for name, _ in zones]

    def adapt_response(self, zones, read, k, target):
        """Ante una apuesta o subida del rival: continuar más contra quien farolea, foldear más contra quien solo apuesta con mano."""
        agg = read.aggression()
        points = round(CONTINUE_SHIFT * agg * k, 1)
        if abs(points) < 1:
            return zones, []
        target = target if target in dict(zones) else None
        if target is None:
            return zones, []
        if points > 0:
            zones = self.move(zones, 'fold', target, points)
            note = f'his aggression is {read.level(agg).upper()}: more of his bets and raises are bluffs than for an average player, so continue {points} points more of your range.'
        else:
            zones = self.move(zones, target, 'fold', -points)
            note = f'his aggression is {read.level(agg).upper()}: he is selective, so when he bets or raises he usually has it: fold {-points} points more of your range.'

        return zones, [note]

    def adapt_first_to_act(self, zones, read, k):
        """Sin apuesta que igualar: contra quien sube o apuesta ante un check, menos faroles y las manos fuertes pasan para atrapar;
        contra quien foldea mucho, más faroles; contra quien no foldea, menos."""
        bet = dict(zones).get('bet')
        if bet is None:
            return zones, []
        agg = max(0.0, read.aggression())
        trap = round(TRAP_MAX * agg * k, 1)
        new_bet = bet * (1 - BET_CUT * agg * k) + BLUFF_SHIFT * read.fold_tendency() * k
        new_bet = round(max(0.0, min(100.0 - trap, new_bet)), 1)
        notes = []
        if trap >= 1:
            notes.append(f'he bets or shoves whenever he is checked to (aggression {read.level(agg).upper()}): the top {trap}% of your range CHECKS to trap him '
                         'and then calls or raises his bet.')
        if abs(new_bet - bet) >= 1:
            notes.append(f"bluff bets {'drop' if new_bet < bet else 'rise'} by {abs(round(new_bet - bet, 1))} points "
                         f"(he {'raises you a lot' if new_bet < bet else 'folds a lot'}): total bets {new_bet}%.")
        if not notes:
            return zones, []

        return [('trap', trap), ('bet', new_bet), ('check', round(100 - trap - new_bet, 1))], notes

    # ------------------------------------------------------------------ secciones

    def _shown_range(self, context, rival, read, arrival, own_range, hole, board):
        """El rango que le queda al rival tras la acción a la que nos enfrentamos (apuesta o subida): (rango, % de veces que la hace)."""
        frequency = self.shown_frequency(read, context, rival)
        if frequency is None:
            return None, None
        scorer = RangeScorer(board)
        rival_combos = arrival.combos(dead=board + hole) if arrival else []
        if not rival_combos:
            rival_combos = [(pair, 1.0) for pair in combinations(Deck.without(board + hole), 2)]
        hero_combos = own_range.combos(dead=board) if own_range else [(pair, 1.0) for pair in combinations(Deck.without(board), 2)]
        narrowed = RangeNarrower(scorer).after_action(rival_combos, scorer.table(hero_combos), frequency, read.vb_ratio)

        return (narrowed, frequency) if narrowed else (None, None)

    @staticmethod
    def _price_check(state, analysis):
        """Compara la equity real de la mano (con proyectos y overcards) con la que pide el precio de la apuesta."""
        equity, to_call, pot = analysis.get('equity'), state.get('to_call_bb') or 0, state.get('pot_bb') or 0
        if equity is None or not to_call:
            return []
        need = round(100 * to_call / (pot + to_call), 1)
        verdict = 'enough to call' if equity >= need else 'not enough to call on equity alone'

        extra = '' if analysis.get('final') else '; also count what you win when you hit'

        return [f'   PRICE CHECK: your equity is {equity}% and the call needs {need}% ({verdict}{extra}).']

    @staticmethod
    def _composition_lines(shown, hole, board):
        """De qué está hecho lo que apuesta el rival: valor, proyectos (semifaroles) y puro farol."""
        composition = RangeComposition(board)
        shares = composition.summarize(shown.combos(dead=board + hole))
        if not shares:
            return []
        families = composition.families(shares)

        return [
            f"   What he bets with: {families['value']}% value, {families['draw']}% draws (semi-bluffs) and {families['bluff']}% pure bluffs "
            f"({', '.join(f'{name} {percent}%' for name, percent in shares.items())}).",
            '   Weigh each family: against value, weak hands fold; against draws, a pair or any hand with real equity continues (you are ahead of or even with '
            'the draw); against a pure bluff, almost everything continues. Folding a hand with equity against a range full of draws and bluffs is a mistake.',
        ]

    def _rivals_section(self, state, rival, read):
        if rival is None:
            return ['   (no rivals left)']
        street_stats = StatsReader.street_stats(rival.get('profile'), state['street'])
        lines = [f"   Main opponent: {rival.get('position')} · stack {rival.get('stack_bb')} bb · preflop {StatsReader.preflop_style(rival.get('profile'))}"]
        lines += StatsReader.lines(street_stats, self.rival_stats, indent='     ')
        lines += read.lines()
        for other in (r for r in state.get('rivals') or [] if r is not rival):
            other_stats = StatsReader.street_stats(other.get('profile'), state['street'])
            summary = ', '.join(f'{k} {other_stats[k]}' for k in self.rival_stats if other_stats.get(k) is not None) or 'no statistics'
            lines.append(f"   Other rival: {other.get('position')} · stack {other.get('stack_bb')} bb · {summary}")

        return lines

    def _range_section(self, state, own, rival_range, hole, board, zones):
        """Dónde cae esta mano dentro de SU rango (el que llega al flop con su línea preflop), ordenado por fuerza en este board."""
        zones = [zone for zone in zones if zone[1] > 0]
        if not own or len(zones) < 2:
            return []
        scorer = RangeScorer(board)
        rival_combos = rival_range.combos(dead=board + hole) if rival_range else []
        if not rival_combos:
            rival_combos = [(pair, 1.0) for pair in combinations(Deck.without(board + hole), 2)]
        table = scorer.table(rival_combos)
        split = RangeSplit(scorer.scored(own.combos(dead=board), table))
        top = split.top_fraction(scorer.score(hole, table))
        zone, stronger, weaker = RangeSplit.locate(top, zones)

        def label(name):
            return 'trap (= CHECK)' if name == 'trap' else name

        labels = ['top' if i == 0 else ('bottom' if i == len(zones) - 1 else 'next') for i in range(len(zones))]
        parts = ', '.join(f'{where} {percent}% {label(name)}' for where, (name, percent) in zip(labels, zones))
        opponent = f'{rival_range.percent_of_hands()}% of hands' if rival_range else 'unknown (all hands)'
        away = ' and '.join(f'the {n.upper()} zone is {p} points away' for n, p in filter(None, (stronger, weaker)))
        action = 'check' if zone == 'trap' else zone
        meaning = (' (a TRAP: you check a strong hand so that he bets into you)' if zone == 'trap' else '')

        return [
            f'  Range you arrive with at this street, {self._origin(state)}: {own.percent_of_hands()}% of all hands. Opponent range: {opponent}.',
            f'  Ordering your range from strongest to weakest on THIS board, your profile splits it: {parts}.',
            f'  => Your hand is in the top {round(top * 100)}% of your range, so it falls in the {zone.upper()} zone ({away}).',
            f'  => With a hand in that position of its range this player usually does {action.upper()}{meaning}. It is one input, not an order: '
            'weigh it with the rest of the results.',
        ]

    def _target_section(self, state):
        target = self.target(state)
        if not target:
            return ['  There is no exact statistic for this spot: follow the general tendencies above.']
        shown = ', '.join(f"{key[:-4].replace('_', ' ')} {value}%" for key, value in target.items())

        return [f'  => Across ALL the hands this player has in this exact spot, it does: {shown}.']

    # ------------------------------------------------------------------ historia de la mano

    @staticmethod
    def _origin(state):
        """De dónde sale el rango con el que se llega a esta calle."""
        return 'from your preflop line' if state['street'] == 'flop' else 'from your preflop line and what you did on the earlier streets'

    def _story(self, state, context, rival, own_range, arrival, hole, board):
        """(líneas, rango propio, rango del rival): la historia de la mano hasta ahora y los rangos estrechados por lo que se hizo en el flop."""
        street = state['street']
        played = [s for s in ('flop', 'turn', 'river') if s != street and any(h and h[0] == s for h in state.get('history') or [])]
        lines = ['1. THE STORY OF THE HAND SO FAR (computed from the actions)'] + HandStory(state).story_lines(played)
        lines += [f'   {line}' for line in BoardStory().lines(board)]
        if street in ('turn', 'river'):
            flop = StreetsStory(state, context, rival, hole, board)
            if flop.applies():
                own_range, arrival, notes = flop.through(own_range, arrival)
                lines += [f'   {note}' for note in notes]

        return lines, own_range, arrival
