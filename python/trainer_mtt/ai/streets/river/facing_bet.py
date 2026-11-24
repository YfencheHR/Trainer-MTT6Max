"""River: el jugador se enfrenta a una apuesta (todavía no había apostado en la calle)."""

from trainer_mtt.ai.streets.flop.facing_bet import FacingBet as FlopFacingBet


class FacingBet(FlopFacingBet):
    rival_stats = ('bet', 'af', 'cr')
    guidance = (
        "- The river is the last street: there are no draws and no cards to come. Your hand (section 3) is either good enough to win at showdown against what he "
        "bets with or it is not, and the only price that matters is the call against the pot. Your profile says how often THIS player folds, calls and raises "
        "against a river bet across all its hands: the weakest hands fold and the best continue. Compare the rank of your hand with the fold percentage and mix "
        "near the boundaries. Never give exactly 0 or 1 unless it is total air or a monster.\n"
        "- Read the story (section 1): a bettor who bet flop and turn and bets again (a third barrel) has either a very strong hand or a bluff that did not "
        "improve, and the missed draws of the board are the natural bluffs; a bettor who checked the earlier streets and bets now (a delayed or probe bet) "
        "often has a hand that improved; a card that completed a flush or a straight makes both value and bluffs possible.\n"
        "- You are a BLUFF-CATCHER when you beat only his bluffs: then compare your call with the price (equity in section 3 against the call odds) and with how "
        "often he bluffs here (his composition above). A big bet needs you to beat bluffs more often than a small one.\n"
        "- Read the opponent (section 2): very aggressive players bluff the river more, so continue more; selective ones usually have it, so fold more.\n"
        "- Raise only with your strongest hands, as often overall as the raise percentage says, and with a few hands that block his value as a bluff."
    )
