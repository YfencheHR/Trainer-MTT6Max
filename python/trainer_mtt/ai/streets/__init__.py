"""Servicios de decisión por calle. Para que la IA decida una calle nueva, se registra aquí."""

from trainer_mtt.ai.streets.flop import FlopService
from trainer_mtt.ai.streets.registry import StreetRegistry


def default_registry():
    return StreetRegistry([FlopService()])
