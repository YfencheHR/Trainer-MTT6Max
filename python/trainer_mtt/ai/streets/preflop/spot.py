"""Situación del preflop: cuestionario que se rellena con la historia de la mano, la mano exacta y el perfil del jugador.

Es el mismo molde que el flop (ver streets/spot.py): el modelo recibe el cuestionario ya calculado por herramientas y solo reparte probabilidades.
Lo que cambia es lo que se calcula: no hay board, así que la mano se mide por su fuerza entre las 169 y por su equity contra el rango del rival, y
la «historia» de la mano (qué ha contado cada jugador con sus acciones) pasa a ser la pieza central.

Del estado, además de lo habitual, se usa `table`: lo que el perfil del jugador hace en esta situación,
  {'spot': {'raise': %, 'call': %, 'fold': %}, 'hand': {…} o None, 'hand_samples': n}
con el reparto sobre TODAS sus manos y, si se conoce, el de esta mano exacta.
"""

from trainer_mtt.ai.actions import ACTION_HELP
from trainer_mtt.ai.streets.preflop.read import PreflopRead
from trainer_mtt.ai.streets.questionnaire import OPPONENT_TYPES, HandContext
from trainer_mtt.ai.streets.spot import BOUNDARY_POINTS, SYSTEM, Spot
from trainer_mtt.poker.hand_story import HandStory
from trainer_mtt.poker.preflop_hand import PreflopHand
from trainer_mtt.poker.range_scorer import RangeSplit

# Adaptación al rival (puntos del rango con adaptación y lectura completas, contra un rival en el extremo): sin calibrar
PREFLOP_SHIFT = 10

STACK_ZONES = ((10, 'PUSH/FOLD (10 bb or less): almost every raise is an all-in'), (20, 'SHORT (10 to 20 bb): all-in is a real option, small raises commit you'),
               (40, 'MEDIUM (20 to 40 bb)'), (10_000, 'DEEP (more than 40 bb): there is room to play after the flop'))


class PreflopSpot(Spot):
    story_of = {}          # qué cuenta cada acción a la mesa: {'raise': '…', 'call': '…', 'fold': '…'}
    raise_means = 'raise'  # cómo se llama aquí la zona de subir (3-bet, 4-bet…)

    def __init__(self, hand=None):
        super().__init__()
        self.hand = hand or PreflopHand()

    def target(self, state):
        spot = (state.get('table') or {}).get('spot') or {}

        return {f'{action}_pct': round(float(value), 1) for action, value in spot.items()
                if action in ('raise', 'call', 'check', 'fold') and value is not None}

    # ------------------------------------------------------------------ adaptación

    def adapt(self, zones, read, k):
        """Por defecto no se adapta; cada situación dice cómo se mueve su rango contra un rival suelto o selectivo."""
        return zones, []

    def widen(self, zones, score, k, reason):
        """Contra un rival suelto (score > 0) se defiende y se resube más; contra uno selectivo, menos."""
        points = round(PREFLOP_SHIFT * score * k, 1)
        if abs(points) < 1:
            return zones, []
        if points > 0:
            zones = self.move(self.move(zones, 'fold', 'call', points * 0.6), 'call', 'raise', points * 0.4)
            note = f'{reason}: continue and re-raise more, about {points} points of your range.'
        else:
            zones = self.move(self.move(zones, 'raise', 'call', -points * 0.4), 'call', 'fold', -points * 0.6)
            note = f'{reason}: continue less, about {-points} points of your range.'

        return zones, [note]

    # ------------------------------------------------------------------ cuestionario

    def questionnaire(self, state, legal):
        story = HandStory(state)
        context = HandContext(state)
        rival = context.main_rival()
        read = PreflopRead(rival)
        k = max(0.0, min(1.0, float(state.get('adaptation', 1.0)))) * read.confidence
        name = self.hand.name(state['hole_cards'])
        range_of_rival, range_note = self._rival_range(rival, story)

        lines = [f'QUESTIONNAIRE · PREFLOP · {self.title}', '', '1. THE STORY OF THE HAND SO FAR (computed from the actions)']
        lines += [f'   Actions: {story.line()}.'] + [f'   {fact}' for fact in story.facts()]
        lines += [f'   {text}' for text in story.what_the_lines_say()]

        lines += ['', '2. WHO IS THE OPPONENT']
        lines += self._opponent_lines(rival, read)

        lines += ['', f'3. YOUR HAND (exact, computed by a tool): {" ".join(state["hole_cards"])}']
        lines += [f'   {line}' for line in self.hand.describe(state['hole_cards'])]
        lines += self._equity_lines(state, range_of_rival, range_note)

        lines += ['', f'4. YOUR PROFILE IN THIS SPOT (you play as this player). Preflop style: {self._style(state.get("profile"))}']
        lines += self._profile_lines(state, name, read, k)

        lines += ['', '5. THE SITUATION'] + self._situation_lines(state, rival)
        lines += ['', '6. HOW TO DECIDE', self.guidance]
        lines.append(
            '- Decide with ALL the results above, they are computed for you: the story of the hand, what his line represents, your equity against it, the price, '
            'the read of this opponent and where your hand falls in what this player usually does. '
            f'When they disagree, or your hand is within about {BOUNDARY_POINTS} points of a boundary, mix the neighbouring actions. '
            'Because it is a person, never give exactly 0 or 1 unless it is a monster or total trash.'
        )
        lines += ['', 'ALLOWED ACTIONS:'] + [f'  - {a}: {ACTION_HELP.get(a, a)}' for a in legal]
        lines += ['', 'Return the JSON with the probability of each allowed action.']

        return '\n'.join(lines)

    def prompt(self, state, legal):
        return [{'role': 'system', 'content': SYSTEM}, {'role': 'user', 'content': self.questionnaire(state, legal)}], self.form()

    # ------------------------------------------------------------------ secciones

    @staticmethod
    def _style(profile):
        preflop = (profile or {}).get('preflop') or {}
        parts = [f'{name} {preflop[name]}%' for name in ('vpip', 'pfr', 'three_bet') if preflop.get(name) is not None]

        return ', '.join(parts) if parts else 'unknown'

    def _opponent_lines(self, rival, read):
        if rival is None:
            return ['   (no rivals left)']
        kind = read.type()
        lines = [f"   Main opponent: {rival.get('position')} · stack {rival.get('stack_bb')} bb · preflop {self._style(rival.get('profile'))}"]
        lines += read.lines()
        lines.append(f'   Type of player (computed from his statistics): {kind.upper()} - {OPPONENT_TYPES[kind]}.')

        return lines

    def _rival_range(self, rival, story):
        """(rango, texto de dónde sale) de lo que el rival principal representa: el de sus tablas si se conoce y, si no, el que suman sus estadísticas."""
        if rival is None:
            return None, None
        known = rival.get('range')
        if known:
            from trainer_mtt.poker.hand_range import HandRange

            return HandRange(known), 'from his preflop table for this line'
        stats = (rival.get('profile') or {}).get('preflop') or {}
        role = story.role_of(rival.get('position')) or ''
        percent = None
        if role.startswith(('opened', 'raised over')):
            percent = stats.get('pfr')
        elif role.startswith('3-bet') or role.startswith('squeezed'):
            percent = stats.get('three_bet')
        elif role.endswith('-bet') or '-bet' in role:
            percent = stats.get('four_bet') or (float(stats['three_bet']) / 2 if stats.get('three_bet') else None)
        if percent is None:
            return None, None

        return self.hand.top_range(percent), f'assumed: the strongest {percent}% of hands, from his statistics (his table is not known)'

    def _equity_lines(self, state, rival_range, note):
        if not rival_range:
            return ['   Equity against his range: unknown (nothing is known about what he plays).']
        equity = self.hand.equity_against(state['hole_cards'], rival_range)
        if equity is None:
            return []
        lines = [f"   Equity against his range ({rival_range.percent_of_hands()}% of hands, {note}): {equity}% if the hand is played to the end."]
        to_call, pot = state.get('to_call_bb') or 0, state.get('pot_bb') or 0
        if to_call:
            need = round(100 * to_call / (pot + to_call), 1)
            lines.append(f'   PRICE CHECK: calling needs {need}% equity ({"enough" if equity >= need else "not enough"} on raw equity; out of position or against '
                         'several players a hand realizes less than its raw equity).')

        return lines

    def _profile_lines(self, state, name, read, k):
        table = state.get('table') or {}
        target = self.target(state)
        lines = []
        if table.get('hand'):
            exact = ', '.join(f'{action} {round(float(value), 1)}%' for action, value in table['hand'].items() if value is not None)
            samples = table.get('hand_samples')
            lines.append(f"  => With exactly this hand ({name}) in this spot this player's table does: {exact}" + (f' ({samples} hands behind it).' if samples else '.'))
        if not target:
            lines.append('  There is no exact statistic for this spot: follow the general tendencies of the profile.')

            return lines
        shown = ', '.join(f"{key[:-4]} {value}%" for key, value in target.items())
        lines.append(f'  => Across ALL the hands this player has in this exact spot, it does: {shown}.')
        zones, notes = self.adapt(self.zones(state), read, k)
        lines += [f'  ADAPTATION to this opponent (your skill and the reliability of the read apply): {note}' for note in notes]
        zones = [zone for zone in zones if zone[1] > 0]
        if len(zones) >= 2:
            top = self.hand.top_percent(name) / 100
            zone, stronger, weaker = RangeSplit.locate(top, zones)
            labels = ['top' if i == 0 else ('bottom' if i == len(zones) - 1 else 'next') for i in range(len(zones))]
            parts = ', '.join(f'{where} {percent}% {action.upper()}' for where, (action, percent) in zip(labels, zones))
            away = ' and '.join(f'the {n.upper()} zone is {p} points away' for n, p in filter(None, (stronger, weaker)))
            lines.append(f'  Ordering ALL starting hands from strongest to weakest, this profile splits them: {parts}.')
            lines.append(f'  => Your hand is in the top {round(top * 100)}% of all hands, so it falls in the {zone.upper()} zone ({away}). It is one input, not an order.')
            lines += self._options_lines(zones)

        return lines

    def _options_lines(self, zones):
        """Qué cuenta cada opción a la mesa (la historia que se cuenta) y con cuántas manos la hace este perfil."""
        lines = ['  What each option tells the table (the story you tell):']
        for action, percent in zones:
            text = self.story_of.get(action)
            if text:
                lines.append(f'    - {action.upper()}: {text} (this profile does it with {percent}% of its hands here)')

        return lines

    def _situation_lines(self, state, rival):
        pot, to_call, stack = state.get('pot_bb') or 0, state.get('to_call_bb') or 0, state.get('stack_bb') or 0
        effective = min(stack, rival.get('stack_bb') or stack) if rival else stack
        zone = next(text for limit, text in STACK_ZONES if effective <= limit)
        lines = [
            f'  - pot {pot} bb · to call {to_call} bb · your stack {stack} bb · effective stack {round(effective, 1)} bb: {zone}',
            f"  - you are in {state.get('position')} · players left in the hand: {state.get('players_left', 1 + len(state.get('rivals') or []))}",
        ]
        if to_call:
            lines.append(f'  - pot odds: you need {round(100 * to_call / (pot + to_call), 1)}% equity to call')
        lines += ['  Actions so far:'] + [f'  {line}' for line in HandContext(state).history_lines()]

        return lines
