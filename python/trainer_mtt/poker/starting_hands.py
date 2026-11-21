"""Las 169 manos iniciales canónicas (AA, AKs, AKo...) y sus combinaciones físicas."""

from itertools import combinations

from trainer_mtt.poker.cards import Card

RANK_ORDER = ['A', 'K', 'Q', 'J', 'T', '9', '8', '7', '6', '5', '4', '3', '2']


class StartingHands:
    """Nombres canónicos de las manos iniciales y su expansión a combos físicos (con caché)."""

    def __init__(self):
        self.names = self._names()
        self._combos = {}

    @staticmethod
    def _names():
        hands = [f"{rank}{rank}" for rank in RANK_ORDER]  # pares
        for i, high in enumerate(RANK_ORDER):
            for low in RANK_ORDER[i + 1:]:
                hands.append(f"{high}{low}s")  # suited
                hands.append(f"{high}{low}o")  # offsuit

        return hands

    def combinations(self, hand):
        """Todos los combos físicos de una mano canónica, p. ej. 'AKs' -> 4."""
        if hand in self._combos:
            return self._combos[hand]
        if hand not in self.names:
            raise ValueError(f"Invalid starting hand: {hand}")

        suits = list(Card.SUITS)
        if len(hand) == 2:
            combos = [(Card(hand[0], a), Card(hand[1], b)) for a, b in combinations(suits, 2)]
        elif hand[2] == 's':
            combos = [(Card(hand[0], suit), Card(hand[1], suit)) for suit in suits]
        else:
            combos = [(Card(hand[0], a), Card(hand[1], b)) for a in suits for b in suits if a != b]

        self._combos[hand] = combos

        return combos

    def expand(self, hand_range):
        """Todos los combos de un rango (lista de manos canónicas)."""
        combos = []
        for hand in hand_range:
            combos.extend(self.combinations(hand))
        if not combos:
            raise ValueError("A hand range must contain at least one valid hand")

        return combos
