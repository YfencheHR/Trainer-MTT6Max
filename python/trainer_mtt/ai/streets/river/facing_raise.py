"""River: el jugador apostó y el rival le sube."""

from trainer_mtt.ai.streets.flop.facing_raise import FacingRaise as FlopFacingRaise


class FacingRaise(FlopFacingRaise):
    rival_stats = ('af', 'cr', 'bet')
    guidance = (
        "- You bet the river and the opponent raised: there are no cards to come, so a call is a showdown for the price of the raise. Your profile says how "
        "often THIS player folds to a raise of its own bet across all its hands: compare the rank of your hand (section 3) with it. Clearly below, fold; clearly "
        "above, call (re-raise only with the nuts or close to it); close to it, mix.\n"
        "- Read the story (section 1): a river raise is the strongest line there is, and bluffs are rare unless he is a very aggressive opponent or a maniac; "
        "it is usually a hand that beats a single pair.\n"
        "- Mind the price: with a big raise you need a lot of equity to call. Never give exactly 0 or 1 unless it is total air or a monster."
    )
