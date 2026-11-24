"""River: el jugador trae la iniciativa (apostó la calle anterior, o la traía preflop y se pasó) y nadie ha apostado todavía."""

from trainer_mtt.ai.streets.flop.initiative import Initiative as FlopInitiative


class Initiative(FlopInitiative):
    own_stats = ('bet', 'af', 'cr')
    guidance = (
        "- You have the initiative on the river and nobody has bet yet; this is the last bet of the hand, so a bet is either for VALUE or a BLUFF and a check "
        "is a showdown. Your profile says how often THIS player bets here across all its hands (the rest it checks): compare your hand (section 3) with the bet "
        "percentage. Strong hands bet for value, medium hands (a pair that can only beat a bluff) mostly check to get to showdown cheaply, and air bets only "
        "as often as this player bluffs; the best bluffs are hands that missed a draw (they cannot win at showdown) or that hold a card that blocks his strong hands.\n"
        "- Read the story (section 1): a bet that tells the same story as the earlier streets (a third barrel) needs value or a good bluff; if the earlier "
        "streets were checked, the opponent's range is capped and a bet gets paid by medium hands.\n"
        "- Read the opponent (section 2): against one that folds a lot, bluff more; against a calling station, bet only for value and give up with air; against "
        "one that raises a lot, check more with weak and medium hands.\n"
        "- The probabilities of all bet sizes together are the probability of betting; check gets the rest. Bigger bets for the strongest hands against opponents "
        "that pay and for polarised bluffs. Never give exactly 0 or 1 unless it is total air or a monster."
    )
