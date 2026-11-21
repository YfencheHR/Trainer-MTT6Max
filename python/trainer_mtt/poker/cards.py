"""Cartas y baraja."""


class Card:
    """Una carta de póker: rango (A, K, ..., 2) y palo (s, h, d, c)."""

    RANKS = {'A': 14, 'K': 13, 'Q': 12, 'J': 11, 'T': 10, '9': 9, '8': 8, '7': 7, '6': 6, '5': 5, '4': 4, '3': 3, '2': 2}
    SUITS = {'s': 0, 'h': 1, 'd': 2, 'c': 3}
    SYMBOLS = {'♠': 's', '♥': 'h', '♦': 'd', '♣': 'c'}

    def __init__(self, rank, suit):
        if rank not in self.RANKS:
            raise ValueError(f"Invalid card rank: {rank}")
        if suit not in self.SUITS:
            raise ValueError(f"Invalid card suit: {suit}")
        self.rank = rank
        self.suit = suit
        self.rank_value = self.RANKS[rank]
        self.suit_value = self.SUITS[suit]

    @classmethod
    def from_string(cls, card_str):
        """'As' o 'A♠' (también 'Td', 'T♦') -> Card."""
        if not isinstance(card_str, str):
            raise ValueError(f"Invalid card: {card_str}")
        text = card_str.replace('️', '').strip()
        if len(text) != 2:
            raise ValueError(f"Invalid card: {card_str}")

        return cls(text[0].upper(), cls.SYMBOLS.get(text[1], text[1].lower()))

    def __str__(self):
        return f"{self.rank}{self.suit}"

    def __repr__(self):
        return str(self)


class Deck:
    """La baraja de 52 cartas y lo que queda de ella cuando ya se conocen algunas."""

    @staticmethod
    def full():
        return [Card(rank, suit) for rank in Card.RANKS for suit in Card.SUITS]

    @classmethod
    def without(cls, known):
        seen = {str(card) for card in known}

        return [card for card in cls.full() if str(card) not in seen]
