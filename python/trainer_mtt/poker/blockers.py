"""Blockers: qué le quitan nuestras cartas a su rango (el que llega a esta calle, antes de estrecharlo con su apuesta: ese ya excluye nuestras cartas).

Una carta en nuestra mano es una carta que él no puede tener. Si bloquea manos fuertes suyas (el as del color, la pareja alta del board), él tiene
menos valor del que parece: pagar con una mano que sirve de poco pesa más. Si bloquea sus faroles o proyectos, queda más valor: foldear pesa más. Es un
cálculo exacto sobre los combos de su rango (con y sin nuestras cartas), no una opinión del modelo.
"""

from trainer_mtt.poker.range_composition import FAMILY, RangeComposition

# A partir de cuánta diferencia (puntos de porcentaje en una familia) se considera que nuestras cartas importan
MEANINGFUL = 1.5


class Blockers:
    def __init__(self, board):
        self.board = list(board)
        self._composition = RangeComposition(self.board)

    def effect(self, combos, hole):
        """{familia: (% del rango sin nuestras cartas, % con ellas)} y cuántos combos del rango quedan bloqueados (%).

        `combos`: el rango del rival SIN descontar nuestras cartas (solo el board). Vacío si no hay rango."""
        dead = {str(card) for card in hole}
        total = sum(weight for _, weight in combos)
        if total <= 0:
            return None
        alive = [(cards, w) for cards, w in combos if str(cards[0]) not in dead and str(cards[1]) not in dead]
        before = RangeComposition.families(self._composition.summarize(combos))
        after = RangeComposition.families(self._composition.summarize(alive))
        removed = {f: self._removed(combos, alive, f) for f in ('value', 'draw', 'bluff')}

        return {'before': before, 'after': after, 'removed': removed, 'blocked': round(100 * (1 - sum(w for _, w in alive) / total), 1)}

    def _removed(self, combos, alive, family):
        """% de los combos de una familia que nuestras cartas le quitan."""
        weight = lambda cs: sum(w for hole, w in cs if FAMILY[self._composition.classify(hole)] == family)
        had = weight(combos)

        return round(100 * (had - weight(alive)) / had, 1) if had > 0 else 0.0

    def lines(self, combos, hole):
        """Líneas del cuestionario sobre los blockers; vacío si no cambian nada."""
        result = self.effect(combos, hole)
        if not result:
            return []
        removed, before, after = result['removed'], result['before'], result['after']
        lines = [
            f"   BLOCKERS (exact, from your cards {' '.join(str(c) for c in hole)}): they remove {removed['value']}% of his value combos, "
            f"{removed['draw']}% of his draws and {removed['bluff']}% of his pure bluffs."
        ]
        shift = round(after['value'] - before['value'], 1)
        if abs(shift) >= MEANINGFUL:
            if shift < 0:
                lines.append(f"   => Your cards BLOCK HIS VALUE: he has value {before['value']}% → {after['value']}% of his range, so he is bluffing "
                             'more often than the plain range suggests: a call (or a raise as a bluff) is worth more.')
            else:
                lines.append(f"   => Your cards BLOCK HIS BLUFFS: he has value {before['value']}% → {after['value']}% of his range, so he is "
                             'less likely to be bluffing: folding a weak hand is worth more.')
        else:
            lines.append('   => Your cards barely change what he can have: blockers do not move the decision.')

        return lines
