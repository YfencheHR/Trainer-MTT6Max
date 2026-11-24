"""Flop: el jugador se enfrenta a una apuesta (todavía no había apostado en la calle)."""

from trainer_mtt.ai.streets.questionnaire import HandContext, StatsReader
from trainer_mtt.ai.streets.spot import Spot


class FacingBet(Spot):
    name = 'facing_bet'
    title = 'FACING A BET'
    own_stats = ('f2bet', 'raise_vs_bet', 'cr', 'af')
    rival_stats = ('cbet', 'donk', 'af', 'cr')
    guidance = (
        "- Your profile says how often THIS player folds, calls and raises here across all its hands. The weakest hands are the ones that fold and the best "
        "ones continue: compare the rank of your hand (section 3) with the fold percentage. If your hand ranks clearly below it, folding gets most of the "
        "probability; clearly above it, continuing does; close to it, mix them. Never give exactly 0 or 1 (it is a person) unless it is total air or a monster.\n"
        "- Draws are worth more than their rank: many outs and a good price (pot odds) keep you in the hand.\n"
        "- Read the opponent (section 2) against your own profile: a bettor that bets very often or is very aggressive bluffs more, so continue a bit more; "
        "a bettor that rarely bets usually has a real hand, so fold a bit more; a calling station betting is strong value.\n"
        "- Raise only with your strongest hands and best draws, as often overall as the raise percentage says; choose the size among the allowed ones."
    )

    def matches(self, state):
        return (state.get('to_call_bb') or 0) > 0 and not HandContext(state).bet_this_street()

    def target(self, state):
        stats = StatsReader.street_stats(state.get('profile'), state['street'])
        defence = (stats.get('ip' if state.get('in_position') else 'oop') or {}).get('def') or {}
        if all(defence.get(k) is not None for k in ('fold', 'call', 'raise')):
            return {'fold_pct': defence['fold'], 'call_pct': defence['call'], 'raise_pct': defence['raise']}

        fold = defence.get('fold', stats.get('f2bet'))
        if fold is None:
            return {}
        fold = round(float(fold), 1)
        raise_share = defence.get('raise', stats.get('raise_vs_bet'))
        if raise_share is None:
            return {'fold_pct': fold, 'continue_pct': round(100 - fold, 1)}
        raised = round((100 - fold) * float(raise_share) / 100, 1)

        return {'fold_pct': fold, 'call_pct': round(100 - fold - raised, 1), 'raise_pct': raised}

    def shown_frequency(self, read, context, rival):
        return read.bet_frequency(context.rival_had_initiative(rival))

    def adapt(self, zones, read, k):
        return self.adapt_response(zones, read, k, 'call' if 'call' in dict(zones) else 'continue')
