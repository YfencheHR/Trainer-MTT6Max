"""La historia del board: cómo es el flop y qué cambió la carta del turn.

Una carta nueva no es neutra: puede completar un proyecto de color o escalera, emparejar el board (y volver fuerte lo que era farol o débil lo que
era valor) o ser una overcard que favorece a quien subió. Es un cálculo exacto sobre las cartas; el modelo solo lee el resultado.
"""

from collections import Counter

NAMES = {14: 'ace', 13: 'king', 12: 'queen', 11: 'jack', 10: 'ten', 9: 'nine', 8: 'eight', 7: 'seven', 6: 'six', 5: 'five', 4: 'four', 3: 'three', 2: 'two'}


class BoardStory:
    @staticmethod
    def suit_count(board):
        """Cuántas cartas tiene el palo que más se repite."""
        return max(Counter(card.suit for card in board).values()) if board else 0

    @staticmethod
    def straight_cards(board):
        """Cuántas cartas del board caben en una misma ventana de 5 rangos (el as vale también 1): 3 = hay proyectos, 4 = una carta completa la escalera."""
        ranks = {card.rank_value for card in board}
        if 14 in ranks:
            ranks.add(1)

        return max((len(ranks & set(range(low, low + 5))) for low in range(1, 11)), default=0)

    def flop_lines(self, flop):
        suit = self.suit_count(flop)
        suits = {3: 'monotone (three of one suit: a flush is already possible)', 2: 'two-tone (a flush draw is possible)', 1: 'rainbow (no flush draw)'}[suit]
        values = sorted((card.rank_value for card in flop), reverse=True)
        paired = len({card.rank_value for card in flop}) < 3
        connected = self.straight_cards(flop) >= 3
        wet = suit >= 2 or connected
        traits = [f'{NAMES[values[0]]}-high', suits, 'paired' if paired else 'unpaired', 'connected (straight draws)' if connected else 'not connected']

        return [f"Flop texture: {', '.join(traits)}. This is a {'WET' if wet else 'DRY'} board."]

    def turn_lines(self, board):
        """Qué cambió la carta del turn (cuando el board tiene cuatro cartas)."""
        return self._card_lines(board, 4, 'Turn')

    def river_lines(self, board):
        """Qué cambió la carta del river (cuando el board tiene cinco cartas): ya no quedan cartas, solo manos hechas."""
        return self._card_lines(board, 5, 'River')

    def _card_lines(self, board, size, label):
        if len(board) != size:
            return []
        earlier, card = board[:-1], board[-1]
        top = max(c.rank_value for c in earlier)
        bottom = min(c.rank_value for c in earlier)
        changes = []
        if any(c.rank_value == card.rank_value for c in earlier):
            changes.append(f'it PAIRS the board (a {NAMES[card.rank_value]} again): trips, full houses and the rank itself become real')
        if card.rank_value > top:
            changes.append(f'it is an OVERCARD to the board (a {NAMES[card.rank_value]}): it favours the player who raised preflop, who holds more high cards')
        elif card.rank_value < bottom:
            changes.append(f'it is a LOW card ({NAMES[card.rank_value]}): it changes little unless it completes a straight')
        else:
            changes.append(f'it is a MIDDLE card ({NAMES[card.rank_value]})')
        before, after = self.suit_count(earlier), self.suit_count(board)
        if after == 5:
            changes.append('it makes FIVE cards of one suit: the flush is on the board')
        elif after == 4 and before < 4:
            changes.append('it makes FOUR cards of one suit: anyone with a single card of that suit has a flush')
        elif after == 3 and before == 2:
            changes.append('it brings a THIRD card of one suit: ' + ('a flush needs both hole cards of that suit' if size == 5 else 'flush draws are now possible'))
        elif after == 2 and before == 1:
            changes.append('it puts two cards of one suit on the board (still no flush draw on the board itself)')
        straight_before, straight_after = self.straight_cards(earlier), self.straight_cards(board)
        if straight_after >= 4 > straight_before:
            changes.append('it puts FOUR cards of a straight on the board: a single card completes it' if size == 4 else 'it puts FOUR cards of a straight on the board')
        elif straight_after == 3 and straight_before < 3:
            changes.append('it creates straight draws' if size == 4 else 'it makes a straight possible for a hand holding the right two cards')

        return [f'{label} card {card}: ' + '; '.join(changes) + '.']

    def lines(self, board):
        """Textura del flop y, si hay turn o river, lo que cambió."""
        return self.flop_lines(board[:3]) + self.turn_lines(board[:4]) + self.river_lines(board) if len(board) >= 3 else []
