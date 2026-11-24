"""Flop: el jugador apostó y el rival le sube."""

from trainer_mtt.ai.streets.questionnaire import HandContext, StatsReader
from trainer_mtt.ai.streets.spot import Spot


class FacingRaise(Spot):
    name = 'facing_raise'
    title = 'YOUR BET WAS RAISED'
    own_stats = ('f2raise', 'af', 'cr')
    rival_stats = ('af', 'cr', 'cbet')
    guidance = (
        "- You bet and the opponent raised. Your profile says how often THIS player folds to a raise of its own bet across all its hands: the weakest hands "
        "fold and the best continue. Compare the rank of your hand (section 3) with that fold percentage: clearly below it, fold; clearly above it, continue "
        "(call, or re-raise only with a monster or a very strong draw); close to it, mix.\n"
        "- A raise from a passive, tight or calling-station opponent is almost always real strength: fold more. A raise from an aggressive opponent or a maniac "
        "is often a bluff or a semi-bluff: continue more, especially with a draw.\n"
        "- Mind the price: with a big raise you need more equity to call. Never give exactly 0 or 1 unless it is total air or a monster."
    )

    def matches(self, state):
        return (state.get('to_call_bb') or 0) > 0 and HandContext(state).bet_this_street()

    def target(self, state):
        fold = StatsReader.street_stats(state.get('profile'), state['street']).get('f2raise')
        if fold is None:
            return {}
        fold = round(float(fold), 1)

        return {'fold_pct': fold, 'continue_pct': round(100 - fold, 1)}

    def shown_frequency(self, read, context, rival):
        return read.raise_frequency()

    def shown_verb(self):
        return 'raise'

    def adapt(self, zones, read, k):
        return self.adapt_response(zones, read, k, 'continue')
