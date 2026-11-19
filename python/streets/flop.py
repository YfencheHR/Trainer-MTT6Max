"""Servicio del FLOP."""

from streets.base import StreetService


class FlopService(StreetService):
    street = 'flop'
    focus = (
        "STREET: FLOP (three board cards, first postflop betting round).\n"
        "Profile statistics for the flop (percentages unless noted):\n"
        "- f2bet: how often this player folds when facing a flop bet. raise_vs_bet: how often it raises a bet instead of calling.\n"
        "- cbet: how often it bets the flop when it was the preflop aggressor. donk: how often it bets into the aggressor when out of position.\n"
        "- cr: how often it check-raises. f2raise: how often it folds to a raise of its own bet.\n"
        "- af: aggression factor (bets and raises per call); above 2 is aggressive, below 1 passive.\n"
        "- ip / oop: the same tendencies split by being in position (acting last) or out of position, and by role: "
        "'agg.bet' = bets when it had the initiative; 'def.fold', 'def.call', 'def.raise' = what it does facing a bet.\n"
        "- sample_size / n: how many hands support the numbers; with few hands the numbers are less reliable.\n"
        "These are the player's REAL tendencies. Over many hands, the probabilities you give should reproduce them: "
        "if it folds to a flop bet 70% of the time, the fold probability facing a bet should be near 0.70 on average "
        "(lower with a very strong hand, higher with air). A tendency is a frequency across all its hands, not a rule for this one: "
        "the hand strength, the board texture, the bet size and the price still move the probabilities up or down around it.\n"
        "The 'history' lists the actions of this hand in order (position, action, amount in bb); use it to know who has the initiative.\n"
        "'in_position' tells whether this player acts last on the flop."
    )
