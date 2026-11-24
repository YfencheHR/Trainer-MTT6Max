"""Genera trainer_mtt/poker/data/preflop_order.json: las 169 manos iniciales ordenadas de la más fuerte a la más débil.

La fuerza es la equity (ganar al llegar al final) contra una mano al azar, por simulación con semilla fija. Se ejecuta una sola vez y el archivo
se guarda en el repositorio: el orden de las manos no cambia.

    docker compose exec -T python-service python -m tools.build_preflop_order [intentos]
"""

import json
import random
import sys
from pathlib import Path

from trainer_mtt.poker.cards import Card, Deck
from trainer_mtt.poker.fast_eval import FastEvaluator
from trainer_mtt.poker.starting_hands import StartingHands

OUT = Path(__file__).resolve().parent.parent / 'trainer_mtt' / 'poker' / 'data' / 'preflop_order.json'


def equity_against_random(hole, trials, rng):
    deck = Deck.without(hole)
    total = 0.0
    for _ in range(trials):
        cards = rng.sample(deck, 7)
        rival, board = cards[:2], cards[2:]
        ours = FastEvaluator.rank(list(hole) + board)
        theirs = FastEvaluator.rank(rival + board)
        total += 1.0 if ours > theirs else (0.5 if ours == theirs else 0.0)

    return total / trials


def main(trials):
    hands = StartingHands()
    rng = random.Random(2024)
    scored = []
    for name in hands.names:
        hole = hands.combinations(name)[0]
        scored.append((name, equity_against_random(hole, trials, rng)))
    scored.sort(key=lambda item: -item[1])
    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps({'trials': trials, 'hands': [{'hand': h, 'equity': round(e, 4)} for h, e in scored]}, indent=1) + '\n')
    print(f'{len(scored)} manos guardadas en {OUT}')
    print(', '.join(f'{h} {e:.3f}' for h, e in scored[:12]), '...', ', '.join(f'{h} {e:.3f}' for h, e in scored[-5:]))


if __name__ == '__main__':
    main(int(sys.argv[1]) if len(sys.argv) > 1 else 12000)
