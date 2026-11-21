"""Estándar de un servicio de calle.

Cada calle (preflop, flop, turn, river) es un servicio propio que decide con el mismo modelo. Dentro de la calle, cada cosa que puede
estar pasando en la mano (ante una apuesta, con la iniciativa…) es una SITUACIÓN independiente con su propio cuestionario (ver spot.py).
Para añadir una calle:

  1. crear streets/<calle>/ con una clase que herede de StreetService y una situación (Spot) por caso, cada una en su archivo,
  2. registrarla en streets/__init__.py,
  3. añadir la calle a `streets` en app/config/ai.php (Laravel) para que los bots la usen,
  4. un test en tests/ con un estado de esa calle.
"""

from trainer_mtt.ai.answer import ActionAnswer
from trainer_mtt.ai.decider import ActionDecider


class StreetService:
    street = ''
    # Situaciones de la calle, en orden: se usa la primera cuyo `matches` acepte el estado
    spots = ()
    # Solo para servicios sin situaciones: texto de enfoque que se añade a las instrucciones generales
    focus = ''

    def relevant_profile(self, profile):
        """Recorta el perfil a lo que importa en esta calle. Por defecto: todo el preflop y la parte de esta calle del postflop."""
        profile = profile or {}
        postflop = profile.get('postflop') or {}
        sliced = {k: v for k, v in postflop.items() if k == self.street or k == 'vb_ratio'}

        return {'preflop': profile.get('preflop') or {}, 'postflop': sliced}

    def prepare_state(self, state):
        """Estado que ve el modelo: el recibido, con el perfil propio y el de cada rival recortados a esta calle."""
        prepared = dict(state)
        prepared['profile'] = self.relevant_profile(state.get('profile'))
        prepared['rivals'] = [
            {**rival, 'profile': self.relevant_profile(rival.get('profile'))} for rival in state.get('rivals', [])
        ]

        return prepared

    def spot_for(self, state):
        return next((spot for spot in self.spots if spot.matches(state)), None)

    def decide(self, state, model, legal_actions=None, client=None, seed=None):
        if state.get('street') != self.street:
            raise ValueError(f"This service decides the {self.street}, not '{state.get('street')}'")
        prepared = self.prepare_state(state)
        decider = ActionDecider(client)
        if not self.spots:
            return decider.decide(prepared, model, legal_actions, seed, focus=self.focus)

        spot = self.spot_for(prepared)
        if spot is None:
            raise ValueError(f'No {self.street} situation covers this state')
        legal = ActionAnswer.legal_or_all(legal_actions)
        messages, form = spot.prompt(prepared, legal)

        return {**decider.decide(prepared, model, legal, seed, messages=messages, form=form), 'spot': spot.name}
