"""Cuestionario de ejemplo del river (herramienta de desarrollo): python -m tools.smoke_river"""

import time

from tests.test_river import FACING_RIVER_BET
from trainer_mtt.ai.streets import default_registry

service = default_registry().service_for('river')
prepared = service.prepare_state(FACING_RIVER_BET)
spot = service.spot_for(prepared)
started = time.time()
messages, _ = spot.prompt(prepared, ['fold', 'call', 'bet_half', 'bet_pot', 'allin'])
print(spot.name, f'(cuestionario en {time.time() - started:.1f} s)\n')
print(messages[1]['content'])
