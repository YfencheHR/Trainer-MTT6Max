"""Preflop: alguien ha abierto y este jugador todavía no ha actuado (defender, resubir o foldear)."""

from trainer_mtt.ai.streets.preflop.spot import PreflopSpot
from trainer_mtt.poker.hand_story import HandStory


class FacingOpen(PreflopSpot):
    name = 'facing_open'
    title = 'FACING AN OPEN RAISE'
    story_of = {
        'raise': 'a 3-bet tells the table you have the best of his range (or a good bluff blocker): it takes the initiative and often wins the pot at once',
        'call': 'a call tells the table you have a hand that plays well after the flop but is not strong enough to 3-bet: you arrive capped, without the initiative',
        'fold': 'no story: you give up your blind and the pot',
    }
    guidance = (
        "- Your profile says how often THIS player 3-bets, calls and folds against an open across all its hands. The best hands re-raise, the next ones call and "
        "the rest fold: compare the rank of your hand (section 3) with those percentages and mix near the boundaries.\n"
        "- Compare your equity with the price (section 3): a hand with enough equity against his range is a call even when it is not a re-raise hand; "
        "a hand far below the price folds unless the profile says it defends with it.\n"
        "- Read the opener (section 2): a very loose opener has more weak hands, so defend and re-raise more; a very tight one has it, so continue less.\n"
        "- The story matters: a steal from the button into the blinds is wider than an early-position open; a call from out of position leaves you without the initiative."
    )

    def matches(self, state):
        story = HandStory(state)

        return story.raises() == 1 and not story.i_acted_before()

    def adapt(self, zones, read, k):
        return self.widen(zones, read.looseness(), k, f'his opening range is {read.level(read.looseness()).upper()} in width')
