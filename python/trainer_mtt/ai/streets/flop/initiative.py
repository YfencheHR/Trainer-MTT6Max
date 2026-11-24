"""Flop: el jugador subió preflop (tiene la iniciativa) y nadie ha apostado todavía."""

from trainer_mtt.ai.streets.questionnaire import HandContext, StatsReader
from trainer_mtt.ai.streets.spot import Spot


MIN_CELL_HANDS = 30   # manos mínimas de una celda (posición × heads-up/multiway) para fiarse de ella


class Initiative(Spot):
    name = 'initiative'
    title = 'WITH THE PREFLOP INITIATIVE, NO BET YET'
    own_stats = ('cbet', 'af', 'cr')
    rival_stats = ('f2bet', 'raise_vs_bet', 'cr', 'af')
    guidance = (
        "- You raised preflop and nobody has bet yet. Your profile says how often THIS player bets here across all its hands (the rest it checks). It bets "
        "its best hands and some of its worst (bluffs and draws): compare your hand (section 3) with the bet percentage; strong made hands and strong draws "
        "bet most of the time, medium hands are the ones that mostly check, and air bets only as often as this player bluffs.\n"
        "- Read the opponent (section 2): against one that folds a lot to bets, bet more (more air); against a calling station, bet for value and bluff "
        "little; against one that raises or check-raises a lot, check more with weak and medium hands.\n"
        "- The probabilities of all bet sizes together are the probability of betting; check gets the rest. Use small bets for thin value, draws and dry "
        "boards and big bets for strong hands against opponents that pay. Never give exactly 0 or 1 unless it is total air or a monster."
    )

    def matches(self, state):
        return (state.get('to_call_bb') or 0) == 0 and HandContext(state).had_initiative()

    def target(self, state):
        stats = StatsReader.street_stats(state.get('profile'), state['street'])
        # Lo más concreto que tenga el perfil manda sobre la cifra general: posición (en o fuera de posición) y, dentro de ella, si el bote
        # es heads-up o multiway (el cbet cae mucho con más rivales). Una celda con pocas manos no se usa: se baja al nivel anterior.
        side = stats.get('ip' if state.get('in_position') else 'oop') or {}
        cell = side.get('agg_multi' if (state.get('players_left') or 2) > 2 else 'agg_hu') or {}
        if cell.get('bet') is not None and (cell.get('n') or 0) >= MIN_CELL_HANDS:
            bet = cell['bet']
        else:
            general = stats.get('cbet') if stats.get('cbet') is not None else stats.get('bet')   # cbet en el flop; «apuesta cuando le pasan» en el turn
            bet = (side.get('agg') or {}).get('bet', general)
        if bet is None:
            return {}
        bet = round(float(bet), 1)

        return {'bet_pct': bet, 'check_pct': round(100 - bet, 1)}

    def adapt(self, zones, read, k):
        return self.adapt_first_to_act(zones, read, k)
