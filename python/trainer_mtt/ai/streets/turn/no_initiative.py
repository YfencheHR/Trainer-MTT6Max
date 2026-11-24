"""Turn: el jugador no trae la iniciativa y nadie ha apostado (actúa primero fuera de posición, o le han pasado en posición)."""

from trainer_mtt.ai.streets.flop.no_initiative import NoInitiative as FlopNoInitiative


class NoInitiative(FlopNoInitiative):
    guidance = (
        "- You do not have the initiative on the turn and nobody has bet yet. There is usually no exact statistic: use the general aggression of the profile "
        "as the tendency to bet when checked to, and the hand in section 3 to choose between checking (to call or check-raise) and betting.\n"
        "- Read the story (section 1): if the opponent bet the flop and you called, his range is narrower now; if he checked behind the flop, his range is "
        "capped and a bet from you (a probe or a value bet) gets paid by medium hands and folds out weak ones.\n"
        "- Read the opponent (section 2): an aggressive player will bet if you check, so checking to call or check-raise is natural with strong hands and "
        "draws; against a passive opponent that checks behind, betting for value gets more.\n"
        "- The probabilities of all bet sizes together are the probability of betting; check gets the rest. Never give exactly 0 or 1 unless it is a monster."
    )
