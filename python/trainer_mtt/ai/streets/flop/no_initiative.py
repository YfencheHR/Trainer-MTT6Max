"""Flop: el jugador no tiene la iniciativa y nadie ha apostado (actúa primero fuera de posición, o le han pasado en posición)."""

from trainer_mtt.ai.streets.questionnaire import HandContext, StatsReader
from trainer_mtt.ai.streets.spot import Spot


class NoInitiative(Spot):
    name = 'no_initiative'
    title = 'WITHOUT THE INITIATIVE, NO BET YET'
    own_stats = ('donk', 'cr', 'af')
    rival_stats = ('cbet', 'af', 'f2bet', 'cr')
    guidance = (
        "- You did not raise preflop and nobody has bet yet. Out of position and first to act, this player leads (bets) only as often as its profile says "
        "(usually little) and checks the rest; when it leads it is with strong hands or a plan, not with everything. In position after a check there is no "
        "exact statistic: use its general aggression (af) as the tendency to bet when checked to.\n"
        "- Read the opponent (section 1): an aggressive preflop raiser will bet if you check, so checking to call or check-raise is natural with strong hands "
        "and draws; against a passive opponent that checks behind, betting for value or as a probe gets more value.\n"
        "- The probabilities of all bet sizes together are the probability of betting; check gets the rest. Never give exactly 0 or 1 unless it is a monster."
    )

    def matches(self, state):
        return (state.get('to_call_bb') or 0) == 0 and not HandContext(state).had_initiative()

    def target(self, state):
        if state.get('in_position') or HandContext(state).street_actions():
            return {}
        bet = StatsReader.street_stats(state.get('profile'), state['street']).get('donk')
        if bet is None:
            return {}
        bet = round(float(bet), 1)

        return {'bet_pct': bet, 'check_pct': round(100 - bet, 1)}

    def adapt(self, zones, read, k):
        return self.adapt_first_to_act(zones, read, k)
