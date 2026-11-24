"""Preflop: este jugador ya había actuado (abrió o limpeó) y alguien le ha resubido."""

from trainer_mtt.ai.streets.preflop.spot import PreflopSpot
from trainer_mtt.poker.hand_story import HandStory


class FacingReraise(PreflopSpot):
    name = 'facing_reraise'
    title = 'YOU OPENED OR LIMPED AND SOMEONE RAISED'
    story_of = {
        'raise': 'a 4-bet (or shove) tells the table you have the top of your range: it either wins now or commits you with a strong hand',
        'call': 'a call keeps your hand in against a strong range and you will play the rest of the hand without the initiative',
        'fold': 'you let go of the chips you already invested: your open was a hand he beats or a hand that cannot stand a re-raise',
    }
    guidance = (
        "- You already put chips in with a hand you chose to play, and now someone re-raised. Your profile says how often THIS player 4-bets, calls and folds in "
        "this spot across all its hands; the top of the hands re-raises, the next ones call, the rest fold. Compare the rank of your hand (section 3) with them.\n"
        "- A 3-bet range is narrower than an open range: compare your equity against his range with the price (section 3), do not defend just because "
        "you opened. Hands you opened with that are weak against a 3-bet should mostly fold.\n"
        "- Read the 3-bettor (section 2): one who 3-bets very often has bluffs, so continue more; one who rarely 3-bets has it, so continue less.\n"
        "- With a short stack a re-raise commits you: all-in is the usual way to continue."
    )

    def matches(self, state):
        story = HandStory(state)

        return story.i_acted_before() and story.raises() >= 1 and (state.get('to_call_bb') or 0) > 0

    def adapt(self, zones, read, k):
        return self.widen(zones, read.three_bet_tendency(), k, f'his 3-bet frequency is {read.level(read.three_bet_tendency()).upper()}')
