"""Validación de las peticiones de equity (compartida por varias rutas)."""

from math import isfinite


class EquityRequest:
    """La petición común de análisis y predicción preflop: mano del héroe, rangos de los rivales e iteraciones."""

    def __init__(self, hero_hand, opponent_ranges, iterations):
        self.hero_hand = hero_hand
        self.opponent_ranges = opponent_ranges
        self.iterations = iterations

    @classmethod
    def parse(cls, data):
        if not isinstance(data, dict):
            raise ValueError('A JSON object is required')

        hero_hand = data.get('hero_hand')
        opponent_ranges = data.get('opponent_ranges')
        villain_range = data.get('villain_range')
        iterations = data.get('iterations', 5000)

        if not isinstance(hero_hand, str) or not hero_hand:
            raise ValueError('hero_hand is required')
        if opponent_ranges is None:
            if not isinstance(villain_range, list) or not villain_range:
                raise ValueError('villain_range or opponent_ranges is required')
            opponent_ranges = [villain_range]
        if not isinstance(opponent_ranges, list) or not opponent_ranges:
            raise ValueError('opponent_ranges must be a non-empty list of ranges')
        if type(iterations) is not int or not 100 <= iterations <= 50000:
            raise ValueError('iterations must be an integer from 100 to 50000')

        return cls(hero_hand, opponent_ranges, iterations)


def table_parameters(data, percent_key, default_percent):
    """(profundidad de stack en bb, porcentaje de rango) de la petición de una tabla preflop."""
    if not isinstance(data, dict):
        raise ValueError('A JSON object is required')

    stack_depth = data.get('stack_depth_bb', 50)
    range_percent = data.get(percent_key, default_percent)
    if (isinstance(stack_depth, bool) or not isinstance(stack_depth, (int, float))
            or not isfinite(stack_depth) or stack_depth <= 0):
        raise ValueError('stack_depth_bb must be a positive number')
    if (isinstance(range_percent, bool) or not isinstance(range_percent, (int, float))
            or not isfinite(range_percent) or not 0 < range_percent <= 100):
        raise ValueError(f'{percent_key} must be greater than 0 and at most 100')

    return stack_depth, range_percent
