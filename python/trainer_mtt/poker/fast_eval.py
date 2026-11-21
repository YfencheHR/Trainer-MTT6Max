"""Evaluador rápido de la mejor mano de 5, 6 o 7 cartas (sin probar las combinaciones de cinco una a una).

Se usa donde hay que evaluar decenas de miles de manos (equity por simulación, ordenar un rango sobre muchos tableros).
El ranking solo es comparable con otro del mismo evaluador: (categoría, desempates...), mayor gana.
Categorías: 8 escalera de color, 7 póker, 6 full, 5 color, 4 escalera, 3 trío, 2 doble pareja, 1 pareja, 0 carta alta.
"""


class FastEvaluator:
    @staticmethod
    def _straight_high(ranks):
        """Carta más alta de la mejor escalera que haya en estos rangos (0 si no hay); el as también vale 1."""
        have = set(ranks)
        if 14 in have:
            have.add(1)
        for high in range(14, 4, -1):
            if all((high - i) in have for i in range(5)):
                return high

        return 0

    @classmethod
    def rank_ints(cls, ranks, suits):
        """Ranking de la mejor mano con las cartas dadas como listas paralelas de valores de rango (2-14) y de palo (0-3)."""
        counts = {}
        by_suit = {}
        for rank, suit in zip(ranks, suits):
            counts[rank] = counts.get(rank, 0) + 1
            by_suit.setdefault(suit, []).append(rank)

        flush = next((sorted(suited, reverse=True) for suited in by_suit.values() if len(suited) >= 5), None)
        if flush:
            high = cls._straight_high(flush)
            if high:
                return (8, high)

        quads = sorted((r for r, n in counts.items() if n == 4), reverse=True)
        trips = sorted((r for r, n in counts.items() if n == 3), reverse=True)
        pairs = sorted((r for r, n in counts.items() if n == 2), reverse=True)
        ordered = sorted(ranks, reverse=True)

        if quads:
            return (7, quads[0], max(r for r in ordered if r != quads[0]))
        if trips and (len(trips) > 1 or pairs):
            return (6, trips[0], max(trips[1:] + pairs))
        if flush:
            return (5, *flush[:5])
        high = cls._straight_high(ranks)
        if high:
            return (4, high)
        if trips:
            return (3, trips[0], *[r for r in ordered if r != trips[0]][:2])
        if len(pairs) >= 2:
            first, second = pairs[0], pairs[1]
            return (2, first, second, max(r for r in ordered if r != first and r != second))
        if pairs:
            return (1, pairs[0], *[r for r in ordered if r != pairs[0]][:3])

        return (0, *ordered[:5])

    @classmethod
    def rank(cls, cards):
        return cls.rank_ints([card.rank_value for card in cards], [card.suit_value for card in cards])
