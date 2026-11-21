"""Decisión de un bot con un modelo de lenguaje local.

El bot es un PERFIL: el modelo recibe el estado de la mano y las estadísticas por situación del jugador y de los rivales, y responde con
qué probabilidad haría cada acción. No hay reglas debajo ni entrenamiento: el único cambio entre un bot y otro son los datos que se le
pasan. El sorteo de la acción lo hace Laravel con su semilla.

Contrato (ver App\\Services\\Ai\\DecisionContract): `state` -> distribución {acción: probabilidad} que suma 1.
"""

from trainer_mtt.ai.answer import ActionAnswer
from trainer_mtt.ai.ollama import OllamaClient
from trainer_mtt.ai.prompt import GenericPrompt


class ActionDecider:
    def __init__(self, client=None):
        self.client = client or OllamaClient()

    def decide(self, state, model, legal_actions=None, seed=None, focus='', messages=None, form=None):
        """Distribución de acciones del perfil para este estado, según el modelo.

        Sin `messages` se usa el mensaje genérico (estado en JSON); un servicio de situación pasa su cuestionario ya armado.
        """
        legal = ActionAnswer.legal_or_all(legal_actions)
        text = self.client.chat(model, messages or GenericPrompt.messages(state, legal, focus), ActionAnswer.schema(legal, form), seed)

        return {'model': model, 'distribution': ActionAnswer.parse(text, legal)}
