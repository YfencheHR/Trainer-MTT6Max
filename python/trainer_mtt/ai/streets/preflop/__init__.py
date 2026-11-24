"""Servicio del PREFLOP: elige la situación de la mano y deja que ese servicio arme el cuestionario y decida."""

from trainer_mtt.ai.streets.base import StreetService
from trainer_mtt.ai.streets.preflop.cold_reraise import ColdReraise
from trainer_mtt.ai.streets.preflop.facing_open import FacingOpen
from trainer_mtt.ai.streets.preflop.facing_reraise import FacingReraise
from trainer_mtt.ai.streets.preflop.open import Open


class PreflopService(StreetService):
    street = 'preflop'
    # El orden importa: «ya había actuado y me resuben» se mira antes que las situaciones en las que todavía no ha hablado
    spots = (FacingReraise(), ColdReraise(), FacingOpen(), Open())
