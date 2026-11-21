"""El rango que le queda a un rival después de apostar o subir en esta calle.

Un jugador no apuesta con todo lo que tiene: con la frecuencia con la que realiza esa acción, apuesta sus mejores manos (valor) y
algunas manos débiles con proyectos o sin showdown (faroles). La proporción entre ambas la da su relación valor/farol. Esto vuelve
mucho más fuerte el rango de quien sube que el de quien llegó al flop, y es lo que hace foldear un top pair a quien se enfrenta a
un rival selectivo y no foldearlo contra un maníaco.
"""

from trainer_mtt.poker.hand_range import ComboRange

DEFAULT_VALUE_BLUFF_RATIO = 2.0


class RangeNarrower:
    def __init__(self, scorer):
        self.scorer = scorer

    def after_action(self, combos, table_vs_hero, frequency_pct, value_bluff_ratio=None):
        """ComboRange con las manos de `combos` que realizarían una acción que hacen `frequency_pct`% de las veces.

        `combos` = [((carta, carta), peso)] del rival; `table_vs_hero` = scorer.table(...) del rango del héroe: ordena las manos del rival
        por fuerza contra lo que tiene el héroe. None si no hay frecuencia: sin dato no se estrecha.
        """
        total = sum(weight for _, weight in combos)
        if frequency_pct is None or total <= 0:
            return None
        target = total * max(0.0, min(100.0, float(frequency_pct))) / 100
        ratio = float(value_bluff_ratio) if value_bluff_ratio else DEFAULT_VALUE_BLUFF_RATIO
        value_target = target * ratio / (1 + ratio)

        ranked = sorted(
            ((self.scorer.score(cards, table_vs_hero), self.scorer.draw_odds(cards), cards, weight) for cards, weight in combos),
            key=lambda item: -item[0],
        )
        chosen, rest = [], []
        taken = 0.0
        for strength, draw, cards, weight in ranked:
            take = min(weight, max(0.0, value_target - taken))
            if take > 0:
                chosen.append((cards, take))
                taken += take
            if weight - take > 1e-9:
                rest.append((draw, strength, cards, weight - take))

        # Faroles: primero las manos con más proyecto y, a igualdad, las más débiles (no tienen showdown)
        bluff_target = target - taken
        for draw, strength, cards, weight in sorted(rest, key=lambda item: (-item[0], item[1])):
            take = min(weight, bluff_target)
            if take <= 0:
                break
            chosen.append((cards, take))
            bluff_target -= take

        return ComboRange(chosen)
