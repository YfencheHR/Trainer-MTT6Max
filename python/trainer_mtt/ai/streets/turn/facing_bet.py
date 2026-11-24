"""Turn: el jugador se enfrenta a una apuesta (todavía no había apostado en la calle)."""

from trainer_mtt.ai.streets.flop.facing_bet import FacingBet as FlopFacingBet


class FacingBet(FlopFacingBet):
    rival_stats = ('bet', 'af', 'cr')
    guidance = (
        "- The turn is where the pot gets big and the draws have one card to come. Your profile says how often THIS player folds, calls and raises against a turn "
        "bet across all its hands: the weakest hands fold and the best continue. Compare the rank of your hand (section 3) with the fold percentage and mix "
        "near the boundaries. Never give exactly 0 or 1 unless it is total air or a monster.\n"
        "- Read the story (section 1): a bettor who bet the flop and bets again (a second barrel) keeps a narrow range unless he is a player who barrels "
        "with everything; a bettor who checked the flop and bets now (a delayed bet) often represents the card that just came; a card that completed a draw "
        "makes bluffs less likely and value more likely.\n"
        "- Mind the price and the river: a draw has one card left, so compare your equity (section 3) with the price of the call, and count what you win "
        "when you hit. A weak pair that was good on the flop is often only a bluff-catcher now.\n"
        "- Read the opponent (section 2): very aggressive players bluff more, so continue more; selective ones usually have it, so fold more.\n"
        "- Raise only with your strongest hands and best draws, as often overall as the raise percentage says."
    )
