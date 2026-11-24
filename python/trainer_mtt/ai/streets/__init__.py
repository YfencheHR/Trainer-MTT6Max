"""Servicios de decisión por calle. Para que la IA decida una calle nueva, se registra aquí."""

from trainer_mtt.ai.streets.flop import FlopService
from trainer_mtt.ai.streets.preflop import PreflopService
from trainer_mtt.ai.streets.registry import StreetRegistry
from trainer_mtt.ai.streets.river import RiverService
from trainer_mtt.ai.streets.turn import TurnService


def default_registry():
    return StreetRegistry([PreflopService(), FlopService(), TurnService(), RiverService()])
