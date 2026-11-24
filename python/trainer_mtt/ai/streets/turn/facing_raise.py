"""Turn: el jugador apostó y el rival le sube."""

from trainer_mtt.ai.streets.flop.facing_raise import FacingRaise as FlopFacingRaise


class FacingRaise(FlopFacingRaise):
    rival_stats = ('af', 'cr', 'bet')
    guidance = (
        "- You bet the turn and the opponent raised: the pot is big and a call usually leaves a river decision for all your chips. Your profile says how often "
        "THIS player folds to a raise of its own bet across all its hands: compare the rank of your hand (section 3) with it. Clearly below, fold; clearly "
        "above, continue (call, or re-raise only with a monster or a very strong draw); close to it, mix.\n"
        "- Read the story (section 1): a raise on the turn after the opponent called the flop is usually strong, because he had time to raise before; a "
        "raise from a very aggressive opponent or a maniac is more often a bluff or a semi-bluff.\n"
        "- Mind the price and the river: with a big raise you need more equity to call, and a draw has only one card left. "
        "Never give exactly 0 or 1 unless it is total air or a monster."
    )
