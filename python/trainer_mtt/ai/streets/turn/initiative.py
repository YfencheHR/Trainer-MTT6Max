"""Turn: el jugador trae la iniciativa (apostó el flop, o subió preflop y el flop se pasó) y nadie ha apostado todavía."""

from trainer_mtt.ai.streets.flop.initiative import Initiative as FlopInitiative


class Initiative(FlopInitiative):
    own_stats = ('bet', 'af', 'cr')
    guidance = (
        "- You have the initiative on the turn and nobody has bet yet. Your profile says how often THIS player bets here across all its hands (the rest it "
        "checks): compare your hand (section 3) with the bet percentage. Strong made hands and strong draws bet most of the time (a second barrel), medium "
        "hands are the ones that mostly check to control the pot, and air bets only as often as this player bluffs, with the best bluffs being hands with "
        "outs or the card that just came.\n"
        "- Read the story (section 1): if you bet the flop and were called, a second barrel needs a reason (value, a draw, or a card that helps your range); "
        "if the flop was checked, a bet now is a delayed bet and the opponent's range is capped.\n"
        "- Read the opponent (section 2): against one that folds a lot, bet more; against a calling station, bet for value and bluff little; against one that "
        "raises or check-raises a lot, check more with weak and medium hands.\n"
        "- The probabilities of all bet sizes together are the probability of betting; check gets the rest. Use bigger bets for strong hands against opponents "
        "that pay and for polarised bluffs. Never give exactly 0 or 1 unless it is total air or a monster."
    )
