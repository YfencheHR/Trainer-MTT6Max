"""Un rango de manos con peso: {'AKs': 0.9, 'QQ': 1.0} (peso = probabilidad de llegar a la mano con esa mano) y sus combos físicos."""

from trainer_mtt.poker.starting_hands import StartingHands

TOTAL_COMBOS = 1326


class HandRange:
    def __init__(self, weights=None, starting_hands=None):
        self.hands = starting_hands or StartingHands()
        self.weights = {}
        for hand, weight in (weights or {}).items():
            try:
                weight = float(weight)
            except (TypeError, ValueError):
                continue
            if weight > 0 and hand in self.hands.names:
                self.weights[hand] = min(1.0, weight)

    def __bool__(self):
        return bool(self.weights)

    def combos(self, dead=()):
        """[((carta, carta), peso)] de todos los combos del rango que no usan cartas ya vistas (`dead`)."""
        dead = {str(card) for card in dead}
        combos = []
        for hand, weight in self.weights.items():
            for first, second in self.hands.combinations(hand):
                if str(first) not in dead and str(second) not in dead:
                    combos.append(((first, second), weight))

        return combos

    def percent_of_hands(self):
        """Qué porcentaje de todas las manos posibles es este rango (con su peso)."""
        return round(100 * sum(len(self.hands.combinations(h)) * w for h, w in self.weights.items()) / TOTAL_COMBOS, 1)


class ComboRange:
    """Un rango ya expandido a combos con peso [((carta, carta), peso)] (p. ej. el que queda tras una apuesta). Misma interfaz que HandRange."""

    def __init__(self, combos):
        self._combos = list(combos)

    def __bool__(self):
        return bool(self._combos)

    def combos(self, dead=()):
        dead = {str(card) for card in dead}

        return [(cards, weight) for cards, weight in self._combos if str(cards[0]) not in dead and str(cards[1]) not in dead]

    def percent_of_hands(self):
        return round(100 * sum(weight for _, weight in self._combos) / TOTAL_COMBOS, 1)
