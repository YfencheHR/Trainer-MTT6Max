"""Servicio del RIVER: elige la situación de la mano y deja que ese servicio arme el cuestionario y decida.

Mismas cuatro situaciones que el flop y el turn; cambia lo que se cuenta: la historia de las dos calles anteriores (con el rango estrechado por lo que
se hizo en el flop y en el turn), lo que cambió la carta del river y las estadísticas del river. Ya no hay proyectos: la mano está hecha o no.
"""

from trainer_mtt.ai.streets.base import StreetService
from trainer_mtt.ai.streets.river.facing_bet import FacingBet
from trainer_mtt.ai.streets.river.facing_raise import FacingRaise
from trainer_mtt.ai.streets.river.initiative import Initiative
from trainer_mtt.ai.streets.river.no_initiative import NoInitiative


class RiverService(StreetService):
    street = 'river'
    # El orden importa: «apuesta subida» se mira antes que «ante una apuesta»
    spots = (FacingRaise(), FacingBet(), Initiative(), NoInitiative())

    def relevant_profile(self, profile):
        """Las estadísticas del river y las de flop y turn: lo que se hizo en ellas estrecha el rango con el que se llega al river."""
        profile = profile or {}
        postflop = profile.get('postflop') or {}

        return {'preflop': profile.get('preflop') or {}, 'postflop': {k: v for k, v in postflop.items() if k in ('flop', 'turn', 'river', 'vb_ratio')}}
