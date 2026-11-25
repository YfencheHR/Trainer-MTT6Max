"""Compara el coste de una decisión con y sin razonamiento (herramienta de estudio): python -m tools.time_think MODELO"""
import json, sys, time, urllib.request
from tests.test_river import FACING_RIVER_BET
from trainer_mtt.ai.answer import ActionAnswer
from trainer_mtt.ai.ollama import OllamaClient
from trainer_mtt.ai.streets import default_registry

model = sys.argv[1]
service = default_registry().service_for('river')
state = service.prepare_state(FACING_RIVER_BET)
spot = service.spot_for(state)
legal = ['fold', 'call', 'bet_half', 'bet_pot', 'allin']
messages, form = spot.prompt(state, legal)
client = OllamaClient()
schema = ActionAnswer.schema(legal, form)
for think in (False, True):
    body = {'model': model, 'messages': messages, 'stream': False, 'think': think, 'options': {'temperature': 0, 'seed': 1}}
    if not think:
        body['format'] = schema
    t = time.time()
    out = client._request('/api/chat', body)
    msg = out.get('message', {})
    print(f"think={think}: {time.time() - t:.1f}s · tokens de salida {out.get('eval_count')} · razonamiento {len(msg.get('thinking') or '')} car. · respuesta: {(msg.get('content') or '')[:160]!r}")
