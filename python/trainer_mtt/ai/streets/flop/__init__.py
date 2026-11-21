"""Servicio del FLOP: elige la situación de la mano y deja que ese servicio arme el cuestionario y decida."""

from trainer_mtt.ai.streets.base import StreetService
from trainer_mtt.ai.streets.flop.facing_bet import FacingBet
from trainer_mtt.ai.streets.flop.facing_raise import FacingRaise
from trainer_mtt.ai.streets.flop.initiative import Initiative
from trainer_mtt.ai.streets.flop.no_initiative import NoInitiative


class FlopService(StreetService):
    street = 'flop'
    # El orden importa: «apuesta subida» se mira antes que «ante una apuesta»
    spots = (FacingRaise(), FacingBet(), Initiative(), NoInitiative())
