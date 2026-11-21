"""Mensaje genérico para servicios sin cuestionario propio: el estado en JSON compacto y las acciones permitidas."""

import json

from trainer_mtt.ai.actions import ACTION_HELP
from trainer_mtt.ai.answer import ActionAnswer


class GenericPrompt:
    SYSTEM = (
        "You are the decision engine of a poker bot in a No-Limit Hold'em tournament. The bot is NOT a perfect player: "
        "it is a specific player whose personality is defined by its statistics per situation (its 'profile'). "
        "Decide what THIS player would do, not what is optimal. Follow its tendencies: if its profile says it folds a lot "
        "to bets on the flop, it folds often there; if it is aggressive, it bets and raises more. It may make mistakes, like a person. "
        "Use the hand, the board, the pot odds and what you know about each opponent (their profile and what they did this hand). "
        "Answer ONLY with a JSON object giving the probability (0 to 1) that the player takes each allowed action. The probabilities must sum to 1."
    )

    @classmethod
    def messages(cls, state, legal_actions=None, focus=''):
        """Instrucciones fijas (+ el enfoque de la calle) + estado y perfil en JSON compacto + acciones permitidas."""
        legal = ActionAnswer.legal_or_all(legal_actions)
        allowed = '\n'.join(f'- {a}: {ACTION_HELP.get(a, a)}' for a in legal)
        user = (
            'All amounts are in big blinds (bb). Cards: rank + suit symbol.\n\n'
            f'SITUATION AND PROFILES (JSON):\n{json.dumps(state, ensure_ascii=False, separators=(",", ":"))}\n\n'
            f'ALLOWED ACTIONS:\n{allowed}\n\n'
            'Return the probability of each allowed action as JSON.'
        )
        system = cls.SYSTEM + ('\n\n' + focus if focus else '')

        return [{'role': 'system', 'content': system}, {'role': 'user', 'content': user}]
