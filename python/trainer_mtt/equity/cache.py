"""Caché de resultados de equity."""

import hashlib


class EquityCache:
    """Guarda resultados de simulaciones ya hechas (con un máximo de entradas) y cuenta aciertos y fallos."""

    def __init__(self, max_size=1000):
        self.max_size = max_size
        self._items = {}
        self._hits = 0
        self._misses = 0

    @staticmethod
    def key(hero_hand, opponent_ranges):
        """Clave única de una simulación: la mano y los rangos de los rivales."""
        opponents = tuple(tuple(sorted(r)) for r in opponent_ranges)
        text = f"{hero_hand}|{'|'.join('-'.join(o) for o in opponents)}"

        return hashlib.md5(text.encode()).hexdigest()

    def get(self, key):
        """El resultado guardado (cuenta un acierto) o None."""
        if key in self._items:
            self._hits += 1
            return self._items[key]

        return None

    def store(self, key, result):
        """Guarda un resultado recién calculado (cuenta un fallo); si la caché está llena, no lo guarda."""
        self._misses += 1
        if len(self._items) < self.max_size:
            self._items[key] = result

    def stats(self):
        total = self._hits + self._misses
        hit_rate = (self._hits / total * 100) if total > 0 else 0

        return {
            'hits': self._hits,
            'misses': self._misses,
            'hit_rate': round(hit_rate, 2),
            'size': len(self._items),
            'max_size': self.max_size,
        }

    def clear(self):
        self._items.clear()
        self._hits = 0
        self._misses = 0
