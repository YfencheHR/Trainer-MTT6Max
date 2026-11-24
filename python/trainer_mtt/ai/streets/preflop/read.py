"""Lectura preflop del rival: cuánto juega, cuánto sube y resube, y cuánto fiarse de esa lectura.

Con sus estadísticas preflop (vpip, pfr, 3-bet, 4-bet) se saca el tipo de jugador y una «amplitud» de -1 (muy selectivo: cuando sube, tiene) a +1 (muy
suelto: sube con casi cualquier cosa). Los puntos de referencia son los de un rival medio; ninguno está calibrado todavía con datos.
"""

from trainer_mtt.ai.streets.opponent import clamp


class PreflopRead:
    CONFIDENCE_HANDS = 200
    MIN_CONFIDENCE = 0.5
    PFR = (15.0, 10.0)        # (media, amplitud): un rival que abre al 25 % es +1, al 5 % es -1
    THREE_BET = (6.0, 5.0)
    FOUR_BET = (2.5, 2.5)

    def __init__(self, rival):
        profile = (rival or {}).get('profile') or {}
        self.stats = profile.get('preflop') or {}
        sample = self.stats.get('hands') or self.stats.get('sample_size') or self.stats.get('n') or 0
        has_stats = self.stats.get('vpip') is not None or self.stats.get('pfr') is not None
        self.sample = sample
        self.confidence = 0.0 if not has_stats else clamp(sample / self.CONFIDENCE_HANDS if sample else 0.5, self.MIN_CONFIDENCE, 1.0)

    @staticmethod
    def _score(value, reference):
        if value is None:
            return None
        mean, spread = reference

        return clamp((float(value) - mean) / spread)

    def looseness(self):
        """De -1 (selectivo) a +1 (suelto) según lo que abre."""
        return round(self._score(self.stats.get('pfr'), self.PFR) or 0.0, 2)

    def three_bet_tendency(self):
        """De -1 (casi nunca hace 3-bet) a +1 (hace 3-bet con muchas manos)."""
        return round(self._score(self.stats.get('three_bet'), self.THREE_BET) or 0.0, 2)

    def type(self):
        """El tipo de jugador, calculado de sus estadísticas (lo decide una herramienta, no el modelo)."""
        if not self.confidence:
            return 'unknown'
        vpip, pfr = self.stats.get('vpip'), self.stats.get('pfr')
        if vpip is None or pfr is None:
            return 'balanced'
        vpip, pfr = float(vpip), float(pfr)
        if vpip >= 40 and pfr >= 25:
            return 'maniac'
        if vpip >= 35 and pfr < 12:
            return 'calling_station'
        if vpip >= 28 and pfr >= 18:
            return 'loose_aggressive'
        if vpip <= 15:
            return 'nit'
        if pfr >= 13 and pfr / max(vpip, 1) >= 0.6:
            return 'tight_aggressive'
        if pfr < 8:
            return 'passive'

        return 'balanced'

    @staticmethod
    def level(score):
        for limit, name in ((0.6, 'very high'), (0.25, 'high'), (-0.25, 'normal'), (-0.6, 'low')):
            if score >= limit:
                return name

        return 'very low'

    def lines(self):
        if not self.confidence:
            return ['   Read of this opponent: unknown (no statistics): do not adapt to him.']
        facts = [f"{name} {self.stats[name]}%" for name in ('vpip', 'pfr', 'three_bet', 'four_bet') if self.stats.get(name) is not None]
        text = f"   Read of this opponent: {self.level(self.looseness()).upper()} looseness when he raises ({'; '.join(facts)})."

        return [text, f'   Reliability of the read: {round(self.confidence * 100)}%' + (f' ({self.sample} hands).' if self.sample else '.')]
