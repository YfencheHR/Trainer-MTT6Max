"""Preflop: nadie ha subido todavía (abrir, completar o pasar; con limpers delante, aislarlos)."""

from trainer_mtt.ai.streets.preflop.spot import PreflopSpot
from trainer_mtt.poker.hand_story import HandStory


class Open(PreflopSpot):
    name = 'open'
    title = 'NOBODY HAS RAISED YET'
    story_of = {
        'raise': 'you tell the table you have a hand worth playing for the pot; the blinds and the players behind must now decide against that range',
        'call': 'you tell the table you have a hand worth seeing a flop with but not worth raising: weak, speculative, and you enter without the initiative',
        'check': 'you take the free flop from the big blind: no story, no risk',
        'fold': 'no story: you give up the blinds you have already put in, if any',
    }
    guidance = (
        "- Your profile says how often THIS player raises, limps and folds here across all its hands. The strongest hands raise, the weakest fold: compare the "
        "rank of your hand (section 3) with those percentages. Clearly inside the raise zone, raise; clearly outside every zone, fold; close to a boundary, mix.\n"
        "- If you have an exact table for this hand (section 4), it is what this player really does with it: weigh it first.\n"
        "- Position matters through the profile: this spot already comes from your seat. Limpers in front give dead money, so raising to isolate them is good "
        "with the hands you raise anyway.\n"
        "- Choose the raise size among the allowed ones: bigger with limpers in the pot, smaller with short stacks."
    )

    def matches(self, state):
        story = HandStory(state)

        return story.raises() == 0 and not story.i_acted_before()
