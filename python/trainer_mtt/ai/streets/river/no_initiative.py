"""River: el jugador no trae la iniciativa y nadie ha apostado (actúa primero fuera de posición, o le han pasado en posición)."""

from trainer_mtt.ai.streets.flop.no_initiative import NoInitiative as FlopNoInitiative


class NoInitiative(FlopNoInitiative):
    guidance = (
        "- You do not have the initiative on the river and nobody has bet yet; a bet is for value or a bluff, a check is a showdown (or a check-call or "
        "check-raise if he bets). There is usually no exact statistic: use the general aggression of the profile as the tendency to bet, and the hand in "
        "section 3 to choose.\n"
        "- Read the story (section 1): if the opponent bet earlier streets and you called, his range is narrower; if he checked behind on the turn, his range is "
        "capped and a value bet from you gets paid by medium hands, while a bluff folds out weak ones.\n"
        "- Read the opponent (section 2): an aggressive player will bet if you check, so checking to call is natural with a hand that beats bluffs; against a "
        "passive opponent who checks behind, a thin value bet gets more.\n"
        "- The probabilities of all bet sizes together are the probability of betting; check gets the rest. Never give exactly 0 or 1 unless it is a monster."
    )
