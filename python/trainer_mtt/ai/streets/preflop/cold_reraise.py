"""Preflop: alguien abrió, otro resubió (3-bet o más) y este jugador todavía no había actuado."""

from trainer_mtt.ai.streets.preflop.spot import PreflopSpot
from trainer_mtt.poker.hand_story import HandStory


class ColdReraise(PreflopSpot):
    name = 'cold_reraise'
    title = 'FACING A 3-BET OR MORE WITHOUT HAVING ACTED'
    story_of = {
        'raise': 'a 4-bet over an open and a 3-bet tells the table you have a premium hand: the two of them have to decide against a very strong range',
        'call': 'a call between an open and a 3-bet is only for very strong hands or hands that play well after the flop: it is rare and it looks strong',
        'fold': 'no story: two players are already fighting and you are not part of it',
    }
    guidance = (
        "- Two players already put money in and one of them re-raised: the ranges in play are strong. Your profile says how often THIS player continues here "
        "across all its hands, which is little: only the top of the hands continue, and the rest fold. Compare the rank of your hand (section 3) with them.\n"
        "- Your equity against the 3-bettor's range (section 3) is the real price: medium hands that call lose money and fold.\n"
        "- Read the 3-bettor (section 2): a very frequent 3-bettor has bluffs, so continue a little more; a very selective one has it, so continue less."
    )

    def matches(self, state):
        story = HandStory(state)

        return story.raises() >= 2 and not story.i_acted_before()

    def adapt(self, zones, read, k):
        return self.widen(zones, read.three_bet_tendency(), k, f'his 3-bet frequency is {read.level(read.three_bet_tendency()).upper()}')
