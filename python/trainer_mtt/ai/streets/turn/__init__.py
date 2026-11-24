"""Servicio del TURN: elige la situación de la mano y deja que ese servicio arme el cuestionario y decida.

Las situaciones son las mismas que las del flop (ante una apuesta, ante una subida, con iniciativa, sin ella); cambia lo que se cuenta: la historia
de lo que se hizo en el flop, lo que cambió la carta del turn y las estadísticas del turn. La iniciativa es la de quien apostó en la calle anterior.
"""

from trainer_mtt.ai.streets.base import StreetService
from trainer_mtt.ai.streets.turn.facing_bet import FacingBet
from trainer_mtt.ai.streets.turn.facing_raise import FacingRaise
from trainer_mtt.ai.streets.turn.initiative import Initiative
from trainer_mtt.ai.streets.turn.no_initiative import NoInitiative


class TurnService(StreetService):
    street = 'turn'
    # El orden importa: «apuesta subida» se mira antes que «ante una apuesta»
    spots = (FacingRaise(), FacingBet(), Initiative(), NoInitiative())

    def relevant_profile(self, profile):
        """Las estadísticas del turn y las del flop: lo que se hizo en el flop estrecha el rango con el que se llega al turn."""
        profile = profile or {}
        postflop = profile.get('postflop') or {}

        return {'preflop': profile.get('preflop') or {}, 'postflop': {k: v for k, v in postflop.items() if k in ('flop', 'turn', 'vb_ratio')}}
