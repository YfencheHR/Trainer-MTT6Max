"""Ventaja de rango: quién llega MEJOR a este board, mirando los dos rangos completos (no solo la mano que tenemos).

- Ventaja de equity: la equity media de cada rango contra el del otro. Quien la tiene puede apostar más veces y con más manos.
- Ventaja de nuts: qué parte de cada rango son manos casi imbatibles (equity muy alta contra el otro rango). Quien las tiene puede apostar grande
  y subir; quien no, debe temer la subida y apuesta pequeño o pasa.

Es un cálculo exacto sobre los combos de ambos rangos (ya estrechados por lo que se hizo en las calles anteriores): el modelo solo lee el resultado.
"""

from trainer_mtt.poker.range_scorer import RangeScorer

NUTS_EQUITY = 0.85       # equity contra el otro rango a partir de la cual una mano cuenta como «nuts»
EVEN_EQUITY = 2.0        # diferencia de equity (puntos) por debajo de la cual no hay ventaja clara
EVEN_NUTS = 3.0          # diferencia de nuts (puntos) por debajo de la cual no hay ventaja clara


class RangeAdvantage:
    def __init__(self, board):
        self.board = list(board)
        self._scorer = RangeScorer(self.board)

    def compare(self, mine, rival):
        """{'mine': {'equity', 'nuts'}, 'rival': {...}} en % o None si falta un rango. Los combos son [((carta, carta), peso)]."""
        mine = [(cards, w) for cards, w in mine if w > 0]
        rival = [(cards, w) for cards, w in rival if w > 0]
        if not mine or not rival:
            return None

        return {
            'mine': self._summary(mine, self._scorer.table(rival)),
            'rival': self._summary(rival, self._scorer.table(mine)),
        }

    def _summary(self, combos, against):
        scored = self._scorer.scored(combos, against)
        total = sum(weight for _, weight in scored)
        if total <= 0:
            return {'equity': 0.0, 'nuts': 0.0}
        equity = sum(strength * weight for strength, weight in scored) / total
        nuts = sum(weight for strength, weight in scored if strength >= NUTS_EQUITY) / total

        return {'equity': round(100 * equity, 1), 'nuts': round(100 * nuts, 1)}

    def lines(self, mine, rival):
        """Líneas del cuestionario sobre la ventaja de rango; vacío si falta algún rango."""
        result = self.compare(mine, rival)
        if not result:
            return []
        me, him = result['mine'], result['rival']
        equity_gap = round(me['equity'] - him['equity'], 1)
        nuts_gap = round(me['nuts'] - him['nuts'], 1)
        lines = [
            f"   RANGE ADVANTAGE on this board (exact, both ranges as they stand): average equity of your range {me['equity']}% against his {him['equity']}%; "
            f"nut hands (equity ≥ {round(100 * NUTS_EQUITY)}%): {me['nuts']}% of your range against {him['nuts']}% of his."
        ]
        lines.append('   => ' + self._verdict(equity_gap, nuts_gap))

        return lines

    @staticmethod
    def _verdict(equity_gap, nuts_gap):
        if abs(equity_gap) < EVEN_EQUITY and abs(nuts_gap) < EVEN_NUTS:
            return 'Neither range is clearly better on this board: no pressure either way; play the hand on its own merits.'
        parts = []
        if equity_gap >= EVEN_EQUITY:
            parts.append('YOUR RANGE IS STRONGER on average, so you can bet more often and with more hands (the opponent has to fold or call weaker)')
        elif equity_gap <= -EVEN_EQUITY:
            parts.append('HIS RANGE IS STRONGER on average: expect pressure and do not over-bet your marginal hands')
        if nuts_gap >= EVEN_NUTS:
            parts.append('you have the NUT ADVANTAGE: large bets and raises are credible')
        elif nuts_gap <= -EVEN_NUTS:
            parts.append('he has the NUT ADVANTAGE: he can raise you with strong hands you do not have, so check your medium hands and be wary of big bets')

        text = '; '.join(parts)

        return text[0].upper() + text[1:] + '.'
