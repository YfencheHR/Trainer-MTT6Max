"""Servicio del FLOP."""

from streets.base import StreetService


def _pct(value):
    return None if value is None else round(float(value), 1)


class FlopService(StreetService):
    street = 'flop'
    assess = True
    focus = (
        "STREET: FLOP (three board cards, first postflop betting round).\n"
        "Profile statistics for the flop (percentages unless noted):\n"
        "- f2bet: how often this player folds when facing a flop bet. raise_vs_bet: of the hands that continue, how often it raises instead of calling.\n"
        "- cbet: how often it bets the flop when it was the preflop aggressor. donk: how often it bets into the aggressor when out of position.\n"
        "- cr: how often it check-raises. f2raise: how often it folds to a raise of its own bet.\n"
        "- af: aggression factor (bets and raises per call); above 2 is aggressive, below 1 passive.\n"
        "- sample_size / n: how many hands support the numbers; with few hands the numbers are less reliable.\n"
        "'this_decision' (in the JSON) names the exact statistic that applies to THIS decision and its target percentages. "
        "That is how often this player takes each kind of action in this exact spot, across ALL the hands it has there. "
        "Your probabilities must follow it: a player that folds 75% in this spot must have a fold probability around 0.75 on average, "
        "and one that folds 25% around 0.25. Do not play the hand as a perfect player would: play it as THIS player does.\n"
        "IMPORTANT: the target is the AVERAGE over every hand this player can hold here, so you must NOT give the same numbers to every hand. "
        "First fill 'hand_strength' by judging how strong THIS hand is on THIS board (air, weak pair or draw, medium pair, strong pair, "
        "two pair or better, monster), then give the probabilities. The weakest hands carry the folds and the strongest hands carry the continues: "
        "with air or a very weak hand the fold probability goes clearly ABOVE the target; with a strong made hand or a strong draw it goes clearly BELOW it "
        "(near 0 for a monster); only with a medium hand does it stay close to the target. "
        "The more this player folds (high target), the more even medium hands fold; the less it folds, the more even air continues. "
        "The board, the bet size and the opponents shift it only a little.\n"
        "The 'history' lists the actions of this hand in order (position, action, amount in bb); 'in_position' tells whether this player acts last."
    )

    def prepare_state(self, state):
        prepared = super().prepare_state(state)
        prepared['this_decision'] = self.this_decision(prepared)
        return prepared

    def this_decision(self, state):
        """La estadística del perfil que corresponde a ESTA decisión, con los porcentajes objetivo ya calculados."""
        stats = ((state.get('profile') or {}).get('postflop') or {}).get(self.street) or {}
        side = stats.get('ip' if state.get('in_position') else 'oop') or {}
        defence = side.get('def') or {}
        history = state.get('history') or []
        flop_actions = [h for h in history if h and h[0] == self.street]
        my_position = state.get('position')
        i_bet_flop = any(h[1] == my_position and h[2] in ('bet', 'raise') for h in flop_actions)

        if (state.get('to_call_bb') or 0) > 0:
            if i_bet_flop:
                fold = _pct(stats.get('f2raise'))
                return {'spot': 'facing a raise of its own flop bet', 'stat': 'f2raise', 'fold_pct': fold} if fold is not None else {}
            fold = _pct(defence.get('fold', stats.get('f2bet')))
            if fold is None:
                return {}
            rest = 100 - fold
            raise_share = defence.get('raise')
            raise_share = stats.get('raise_vs_bet') if raise_share is None else raise_share
            result = {'spot': 'facing a flop bet', 'stat': 'f2bet', 'fold_pct': fold}
            if raise_share is not None:
                result['raise_pct'] = round(rest * float(raise_share) / 100, 1)
                result['call_pct'] = round(rest - result['raise_pct'], 1)
            return result

        # Sin apuesta que igualar: apostar (cbet / donk) o pasar
        initiative = self._had_initiative(history, my_position)
        key = 'cbet' if initiative else ('donk' if not state.get('in_position') else None)
        value = _pct(stats.get(key)) if key else None
        if value is None:
            return {}
        return {'spot': 'first to act with the preflop initiative' if initiative else 'out of position without the initiative',
                'stat': key, 'bet_pct': value, 'check_pct': round(100 - value, 1)}

    @staticmethod
    def _had_initiative(history, position):
        raises = [h for h in history if h and h[0] == 'preflop' and h[2] == 'raise']
        return bool(raises) and raises[-1][1] == position
