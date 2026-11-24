"""Muestra el cuestionario exacto que recibiría el modelo para estados guardados por los comandos ai:evaluate-* (herramienta de desarrollo).

    docker compose cp app/storage/app/ai/eval-preflop-states.json python-service:/tmp/states.json
    docker compose exec -T python-service python -m tools.show_questionnaire /tmp/states.json [número ...]
"""

import json
import sys

from trainer_mtt.ai.actions import ACTIONS
from trainer_mtt.ai.streets import default_registry


def main(path, numbers):
    states = json.loads(open(path, encoding='utf-8').read())
    registry = default_registry()
    for number, item in states.items():
        if numbers and number not in numbers:
            continue
        state = item['state']
        service = registry.service_for(state['street'])
        prepared = service.prepare_state(state)
        spot = service.spot_for(prepared)
        legal = item.get('allowed') or ACTIONS
        messages, _ = spot.prompt(prepared, legal)
        print(f"#### Decisión {number} · {spot.name} · real: {item.get('real')}\n")
        print(messages[1]['content'])
        print()


if __name__ == '__main__':
    main(sys.argv[1], set(sys.argv[2:]))
