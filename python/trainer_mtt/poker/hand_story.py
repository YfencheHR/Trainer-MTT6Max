"""La historia de la mano preflop: qué ha contado cada jugador con sus acciones y en qué tipo de bote se está.

Una mano no son acciones sueltas: quien abre cuenta que tiene una mano para abrir, quien resube cuenta que tiene más. Esta herramienta lee el
historial y lo convierte en hechos (quién abrió, quién pagó, si es un robo de ciegas, un squeeze, un 3-bet…) y en el rango que cada línea
representa. Es un cálculo exacto: el modelo solo lo lee para decidir.
"""

from trainer_mtt.poker.hand_range import HandRange

POSTFLOP_ORDER = {'SB': 0, 'BB': 1, 'UTG': 2, 'MP': 3, 'HJ': 3, 'CO': 4, 'BTN': 5}
RAISES = ('raise', 'allin', 'bet')


class HandStory:
    def __init__(self, state):
        self.state = state
        self.me = state.get('position')
        self.actions = [h for h in state.get('history') or [] if h and h[0] == 'preflop']
        self.rivals = {r.get('position'): r for r in state.get('rivals') or []}
        self.steps = self._read()

    # ------------------------------------------------------------------ lectura del historial

    def _read(self):
        """[(posición, papel, importe)] de cada acción preflop: abrió, limpeó, pagó, hizo 3-bet…"""
        raises, callers, limpers, top = 0, 0, 0, 0.0
        steps = []
        for _, position, action, amount in (h[:4] for h in self.actions):
            amount = float(amount or 0)
            is_raise = action in ('raise', 'bet') or (action == 'allin' and amount > top)
            if action == 'fold':
                role = 'folded'
            elif is_raise:
                raises += 1
                if raises == 1:
                    role = 'raised over the limper(s)' if limpers else 'opened'
                elif raises == 2:
                    role = 'squeezed (raised after an open and a call)' if callers else '3-bet'
                else:
                    role = f'{raises + 1}-bet'
                if action == 'allin':
                    role += ' all-in'
                top = max(top, amount)
            elif action == 'call' or action == 'allin':
                if raises == 0:
                    role, limpers = 'limped', limpers + 1
                else:
                    role = 'called'
                    callers += 1 if raises == 1 else 0
            else:
                role = action
            steps.append((position, role, amount))

        return steps

    def raises(self):
        return sum(1 for _, role, _ in self.steps if role not in ('folded', 'limped', 'called', 'check', 'checked'))

    def limpers(self):
        return [p for p, role, _ in self.steps if role == 'limped']

    def callers(self):
        """Quién ha pagado una apertura (cold call), sin contar a quien ya había abierto."""
        return [p for p, role, _ in self.steps if role == 'called']

    def opener(self):
        return next((p for p, role, _ in self.steps if role.startswith('opened') or role.startswith('raised over')), None)

    def last_aggressor(self):
        aggressive = [p for p, role, _ in self.steps if role not in ('folded', 'limped', 'called', 'check', 'checked')]

        return aggressive[-1] if aggressive else None

    def acted(self):
        return {p for p, role, _ in self.steps if role != 'folded'}

    def folded(self):
        return [p for p, role, _ in self.steps if role == 'folded']

    def role_of(self, position):
        """Lo último que hizo esa posición (opened, called, 3-bet…) o None si todavía no ha actuado."""
        roles = [role for p, role, _ in self.steps if p == position]

        return roles[-1] if roles else None

    def i_acted_before(self):
        """¿Ha hecho este jugador algo más que foldear antes de que le toque ahora (limpear, abrir…)?"""
        return any(p == self.me and role != 'folded' for p, role, _ in self.steps)

    def still_to_act(self):
        """Rivales vivos que todavía no han hablado y lo harán después de este jugador."""
        return [position for position in self.rivals if position not in {p for p, _, _ in self.steps}]

    # ------------------------------------------------------------------ texto

    def line(self):
        """«UTG fold · HJ opened to 2.5bb · CO called · You (BTN) to act» en un solo renglón."""
        parts = []
        for position, role, amount in self.steps:
            text = f'{position} {role}'
            if role not in ('folded', 'limped', 'called', 'check', 'checked') and amount:
                text += f' to {round(amount, 1)}bb'
            parts.append(text)

        return ' · '.join(parts) or 'nobody has acted yet'

    def facts(self):
        """Qué tipo de bote es y qué cuenta cada línea, con las consecuencias que importan para decidir."""
        lines = []
        raises, opener, aggressor = self.raises(), self.opener(), self.last_aggressor()
        limpers, callers = self.limpers(), self.callers()
        if raises == 0 and not limpers:
            lines.append('Nobody has entered the pot: you can be the first to raise (or fold, or complete from the small blind).')
        elif raises == 0:
            lines.append(f"{len(limpers)} limper(s) ({', '.join(limpers)}): there is dead money, and an isolation raise plays heads-up against a weak range.")
        elif raises == 1 and not callers:
            if opener in ('CO', 'BTN') and self.me in ('SB', 'BB'):
                lines.append(f'{opener} opened from late position into the blinds: a steal attempt has a wider range than an early-position open.')
            elif opener == 'SB' and self.me == 'BB':
                lines.append('The small blind raised into you: blind against blind, both ranges are wide.')
            else:
                lines.append(f'{opener} opened and nobody has called: if you continue you are in a raised pot against his opening range.')
        elif raises == 1:
            lines.append(f"{opener} opened and {', '.join(callers)} called: a raise here is a squeeze, with dead money and a capped calling range.")
        elif raises == 2:
            lines.append(f'{aggressor} 3-bet: his range is narrower than an open, usually value and some bluffs.')
        else:
            lines.append(f'{aggressor} made a {raises + 1}-bet: his range is very strong (or an all-in bluff).')
        if aggressor and aggressor != self.me and self.me in POSTFLOP_ORDER and aggressor in POSTFLOP_ORDER:
            in_position = POSTFLOP_ORDER[self.me] > POSTFLOP_ORDER[aggressor]
            lines.append(f"If you continue you will be {'in position' if in_position else 'out of position'} against {aggressor} after the flop.")
        behind = self.still_to_act()
        if behind:
            lines.append(f"Still to act behind you: {', '.join(behind)} (every extra player is one more chance of facing a stronger hand).")

        return lines

    def what_the_lines_say(self):
        """Lo que cuenta cada rival vivo con lo que ha hecho: el rango que esa línea representa (si se conoce) y su perfil."""
        lines = []
        for position, role, _ in self.steps:
            rival = self.rivals.get(position)
            if rival is None or role in ('folded', 'check', 'checked'):
                continue
            weights = rival.get('range')
            share = HandRange(weights).percent_of_hands() if weights else None
            preflop = (rival.get('profile') or {}).get('preflop') or {}
            profile = ', '.join(f'{name} {preflop[name]}%' for name in ('vpip', 'pfr', 'three_bet') if preflop.get(name) is not None)
            says = f'{position} {role}'
            if share is not None:
                says += f': with that line his range is about {share}% of all hands'
            if profile:
                says += f' (profile: {profile})'
            if share is None and not profile:
                says += ': nothing is known about his range'
            lines.append(says)

        return lines

    # ------------------------------------------------------------------ calles anteriores (flop, turn)

    def street_steps(self, street):
        """[(posición, acción, importe)] de una calle postflop, en orden."""
        return [(h[1], h[2], float(h[3] or 0)) for h in self.state.get('history') or [] if h and h[0] == street]

    def street_line(self, street):
        """«BB bet 3bb · BTN call 3bb» de una calle."""
        return ' · '.join(f'{p} {a}' + (f' {round(m, 1)}bb' if m and a in ('bet', 'raise', 'allin', 'call') else '') for p, a, m in self.street_steps(street))

    def pot_type(self):
        """Qué tipo de bote es según el preflop: limpeado, subido, 3-bet…"""
        raises = self.raises()
        if raises == 0:
            return 'limped pot'

        return {1: 'single-raised pot', 2: '3-bet pot'}.get(raises, f'{raises + 1}-bet pot')

    def preflop_summary(self):
        """El preflop contado en una frase: la línea, el tipo de bote y quién tiene la iniciativa."""
        aggressor = self.last_aggressor()
        text = f'Preflop: {self.line()}. It is a {self.pot_type()}'
        if aggressor:
            text += f', and {"you" if aggressor == self.me else aggressor} {"have" if aggressor == self.me else "has"} the initiative (the last to raise)'

        return text + '.'

    def street_facts(self, street):
        """Qué contó cada jugador en una calle ya jugada (apostó, pagó, subió, pasó) y qué significa para la calle siguiente."""
        steps = self.street_steps(street)
        if not steps:
            return []
        aggressive = [i for i, (_, action, _) in enumerate(steps) if action in RAISES]
        if not aggressive:
            return [f'The {street} was checked through: nobody showed strength, so ranges stay wide and a bet now tells a new story (a delayed bet).']
        first = steps[aggressive[0]][0]
        later = steps[aggressive[0] + 1:]
        callers = [p for p, action, _ in later if action == 'call']
        folders = [p for p, action, _ in later if action == 'fold']
        raisers = [p for p, action, _ in later if action in RAISES]
        checked_first = [p for p, action, _ in steps[:aggressive[0]] if action == 'check']
        text = f'On the {street} {first} bet'
        if raisers:
            text += f' and {raisers[0]} raised'
            if raisers[0] in checked_first:
                text += ' (a check-raise: a strong line, or a bluff that wants to fold out the bettor)'
        if callers:
            text += f", and {', '.join(callers)} called (a hand that was not worth raising and not worth folding)"
        if folders:
            text += f", {', '.join(folders)} folded"
        facts = [text + '.']
        if first not in raisers and first not in folders:
            facts.append(f'{first} kept the initiative: a bet on the next street is a second barrel, a check gives up the initiative.')

        return facts

    def story_lines(self, streets_played):
        """La historia hasta ahora para una decisión postflop: el preflop y cada calle ya jugada, con sus hechos."""
        lines = [f'   You are {self.me}.', f'   {self.preflop_summary()}']
        for street in streets_played:
            steps = self.street_line(street)
            lines.append(f'   {street.capitalize()}: {steps or "(no actions)"}.')
            lines += [f'   {fact}' for fact in self.street_facts(street)]

        return lines
