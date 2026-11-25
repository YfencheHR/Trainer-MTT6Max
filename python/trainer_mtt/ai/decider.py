"""Decisión de un bot con un modelo de lenguaje local.

El bot es un PERFIL: el modelo recibe el estado de la mano y las estadísticas por situación del jugador y de los rivales, y responde con
qué probabilidad haría cada acción. No hay reglas debajo ni entrenamiento: el único cambio entre un bot y otro son los datos que se le
pasan. El sorteo de la acción lo hace Laravel con su semilla.

Contrato (ver App\\Services\\Ai\\DecisionContract): `state` -> distribución {acción: probabilidad} que suma 1.

Razonamiento (experimento, `state['reasoning']`): nada (por defecto: una respuesta directa), 'brief' (el modelo escribe antes un análisis breve en un
campo `analysis` del propio JSON) o 'think' (modo de razonamiento del modelo, mucho más lento).
"""

from trainer_mtt.ai.answer import ActionAnswer
from trainer_mtt.ai.ollama import OllamaClient
from trainer_mtt.ai.prompt import GenericPrompt

BRIEF = (
    "Before the probabilities, write in the JSON field `analysis` a short analysis (2 to 4 sentences): what THIS player's profile tends to do here, "
    "how the hand compares with what the opponent represents, and what the price says. Then give the probabilities."
)


class ActionDecider:
    def __init__(self, client=None):
        self.client = client or OllamaClient()

    def decide(self, state, model, legal_actions=None, seed=None, focus='', messages=None, form=None):
        """Distribución de acciones del perfil para este estado, según el modelo.

        Sin `messages` se usa el mensaje genérico (estado en JSON); un servicio de situación pasa su cuestionario ya armado.
        """
        legal = ActionAnswer.legal_or_all(legal_actions)
        messages = messages or GenericPrompt.messages(state, legal, focus)
        reasoning = state.get('reasoning')
        if reasoning == 'brief':
            messages = [dict(m) for m in messages]
            messages[-1]['content'] = messages[-1]['content'] + '\n\n' + BRIEF
        schema = ActionAnswer.schema(legal, form, analysis=reasoning == 'brief')
        # Un cliente de pruebas sin soporte de `think` sigue funcionando: solo se pasa cuando se pide
        extra = {'think': True} if reasoning == 'think' else {}
        text = self.client.chat(model, messages, schema, seed, **extra)

        return {'model': model, 'distribution': ActionAnswer.parse(text, legal)}
