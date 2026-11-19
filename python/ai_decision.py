"""Decisión de un bot con un modelo de lenguaje local (Ollama).

El bot es un PERFIL: el modelo recibe el estado de la mano y las estadísticas por situación del jugador y de los
rivales, y responde con qué probabilidad haría cada acción. No hay reglas debajo ni entrenamiento: el único
cambio entre un bot y otro son los datos que se le pasan. El sorteo de la acción lo hace Laravel con su semilla.

Contrato (ver App\\Services\\Ai\\DecisionContract): `state` -> distribución {acción: probabilidad} que suma 1.
"""

import json
import os
import urllib.error
import urllib.request

ACTIONS = [
    'fold', 'check', 'call',
    'open_2bb', 'open_2_5bb', 'open_3bb', 'raise_x2_2', 'raise_x3', 'raise_x4',
    'bet_third', 'bet_half', 'bet_twothirds', 'bet_pot', 'bet_overbet',
    'allin',
]

ACTION_HELP = {
    'fold': 'give up the hand', 'check': 'pass without betting', 'call': 'match the current bet',
    'bet_third': 'bet or raise about 1/3 of the pot', 'bet_half': 'bet or raise about 1/2 of the pot',
    'bet_twothirds': 'bet or raise about 2/3 of the pot', 'bet_pot': 'bet or raise about the pot',
    'bet_overbet': 'bet or raise about 1.5x the pot', 'allin': 'put all the chips in',
    'open_2bb': 'open to 2 big blinds', 'open_2_5bb': 'open to 2.5 big blinds', 'open_3bb': 'open to 3 big blinds',
    'raise_x2_2': 'raise to 2.2x the current bet', 'raise_x3': 'raise to 3x the current bet', 'raise_x4': 'raise to 4x the current bet',
}

SYSTEM = (
    "You are the decision engine of a poker bot in a No-Limit Hold'em tournament. The bot is NOT a perfect player: "
    "it is a specific player whose personality is defined by its statistics per situation (its 'profile'). "
    "Decide what THIS player would do, not what is optimal. Follow its tendencies: if its profile says it folds a lot "
    "to bets on the flop, it folds often there; if it is aggressive, it bets and raises more. It may make mistakes, like a person. "
    "Use the hand, the board, the pot odds and what you know about each opponent (their profile and what they did this hand). "
    "Answer ONLY with a JSON object giving the probability (0 to 1) that the player takes each allowed action. The probabilities must sum to 1."
)


def legal_or_all(legal_actions):
    legal = [a for a in (legal_actions or ACTIONS) if a in ACTIONS]
    if not legal:
        raise ValueError('legal_actions has no valid action')
    return legal


def build_messages(state, legal_actions=None, focus=''):
    """Mensajes para el modelo: instrucciones fijas (+ el enfoque de la calle) + estado y perfil en JSON compacto + acciones permitidas."""
    legal = legal_or_all(legal_actions)
    allowed = '\n'.join(f'- {a}: {ACTION_HELP.get(a, a)}' for a in legal)
    user = (
        'All amounts are in big blinds (bb). Cards: rank + suit symbol.\n\n'
        f'SITUATION AND PROFILES (JSON):\n{json.dumps(state, ensure_ascii=False, separators=(",", ":"))}\n\n'
        f'ALLOWED ACTIONS:\n{allowed}\n\n'
        'Return the probability of each allowed action as JSON.'
    )
    system = SYSTEM + ('\n\n' + focus if focus else '')
    return [{'role': 'system', 'content': system}, {'role': 'user', 'content': user}]


def response_schema(legal_actions):
    """Esquema JSON que obliga al modelo a dar un número por cada acción permitida y nada más."""
    return {
        'type': 'object',
        'properties': {a: {'type': 'number', 'minimum': 0, 'maximum': 1} for a in legal_actions},
        'required': list(legal_actions),
        'additionalProperties': False,
    }


def parse_distribution(text, legal_actions):
    """Texto del modelo -> distribución normalizada sobre las acciones permitidas."""
    legal = legal_or_all(legal_actions)
    try:
        raw = json.loads(text)
    except (TypeError, ValueError) as error:
        raise ValueError(f'The model did not return JSON: {error}') from error
    if not isinstance(raw, dict):
        raise ValueError('The model answer is not a JSON object')

    values = {}
    for action in legal:
        value = raw.get(action, 0)
        if isinstance(value, bool) or not isinstance(value, (int, float)) or value != value or value < 0:
            value = 0.0
        values[action] = float(value)
    total = sum(values.values())
    if total <= 0:
        raise ValueError('The model gave no probability to any allowed action')
    return {a: round(v / total, 6) for a, v in values.items() if v > 0}


class OllamaClient:
    """Cliente mínimo de Ollama (sin dependencias). Ollama corre en el equipo para poder usar la tarjeta gráfica."""

    def __init__(self, url=None, timeout=None):
        self.url = (url or os.environ.get('OLLAMA_URL') or 'http://host.docker.internal:11434').rstrip('/')
        self.timeout = timeout or float(os.environ.get('OLLAMA_TIMEOUT', 300))

    def _request(self, path, payload=None):
        data = None if payload is None else json.dumps(payload).encode()
        req = urllib.request.Request(self.url + path, data=data, headers={'Content-Type': 'application/json'})
        try:
            with urllib.request.urlopen(req, timeout=self.timeout) as response:
                return json.loads(response.read())
        except (urllib.error.URLError, OSError) as error:
            raise ConnectionError(f'Ollama is not reachable at {self.url}: {error}') from error

    def models(self):
        return [m['name'] for m in self._request('/api/tags').get('models', [])]

    def chat(self, model, messages, schema, seed=None):
        options = {'temperature': 0}
        if seed is not None:
            options['seed'] = int(seed)
        body = self._request('/api/chat', {
            'model': model, 'messages': messages, 'format': schema, 'stream': False, 'think': False, 'options': options,
        })
        return body.get('message', {}).get('content', '')


def decide(state, model, legal_actions=None, client=None, seed=None, focus=''):
    """Distribución de acciones del perfil para este estado, según el modelo."""
    legal = legal_or_all(legal_actions)
    client = client or OllamaClient()
    text = client.chat(model, build_messages(state, legal, focus), response_schema(legal), seed)
    return {'model': model, 'distribution': parse_distribution(text, legal)}
