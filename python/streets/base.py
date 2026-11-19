"""Estándar de un servicio de calle.

Cada calle (preflop, flop, turn, river) es un servicio propio que decide con el mismo modelo pero con su propio enfoque:
qué parte del perfil importa en esa calle y cómo se le explica al modelo. Para añadir una calle:

  1. crear streets/<calle>.py con una clase que herede de StreetService (street, focus, relevant_profile),
  2. registrarla en streets/__init__.py,
  3. añadir la calle a `streets` en app/config/ai.php (Laravel) para que los bots la usen,
  4. un test en tests/ con un estado de esa calle.
"""

import ai_decision


class StreetService:
    street = ''
    # Qué significan las estadísticas de esta calle y qué se espera del modelo en ella (se añade a las instrucciones generales)
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

    def decide(self, state, model, legal_actions=None, client=None, seed=None):
        if state.get('street') != self.street:
            raise ValueError(f"This service decides the {self.street}, not '{state.get('street')}'")
        return ai_decision.decide(self.prepare_state(state), model, legal_actions, client, seed, focus=self.focus)
