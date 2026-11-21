"""Lectura del rival: cuánto agrede, cuánto farolea, cuánto foldea y cuánto fiarse de esa lectura.

Las variables que mandan en la adaptación a un villano concreto son sus estadísticas de la calle:
  - af, raise_vs_bet y cr: cuánto sube y apuesta (agresión);
  - vb_ratio: apuestas de valor por cada farol (cuántos faroles mete);
  - f2bet: cuánto foldea ante una apuesta.
Con ellas se saca una agresión de -1 (pasivo y selectivo: cuando apuesta, tiene) a +1 (maníaco: apuesta y sube con casi todo) y una tendencia a
foldear de -1 (no foldea nunca) a +1 (foldea mucho). Los puntos de referencia son los de un rival medio.
"""

from trainer_mtt.ai.streets.questionnaire import StatsReader


def clamp(value, low=-1.0, high=1.0):
    return max(low, min(high, value))


class OpponentRead:
    CONFIDENCE_HANDS = 100     # con tantas manos de muestra se fía del todo de las estadísticas
    MIN_CONFIDENCE = 0.5       # un perfil sin muestra (plantilla) se fía a medias
    # Rival medio de cada variable y cuánto se aleja un rival «extremo»: (media, amplitud)
    AF = (1.5, 2.0)
    RAISE_VS_BET = (20.0, 25.0)
    CHECK_RAISE = (6.0, 10.0)
    VB_RATIO = (2.0, 1.5)      # valor por farol: a menos valor por farol, más faroleador
    FOLD_TO_BET = (40.0, 30.0)

    def __init__(self, rival, street):
        profile = (rival or {}).get('profile') or {}
        self.stats = StatsReader.street_stats(profile, street)
        self.vb_ratio = (profile.get('postflop') or {}).get('vb_ratio')
        sample = self.stats.get('sample_size') or self.stats.get('n') or 0
        has_stats = any(self.stats.get(key) is not None for key in ('af', 'f2bet', 'raise_vs_bet', 'cr', 'cbet'))
        self.sample = sample
        self.confidence = 0.0 if not has_stats else clamp(sample / self.CONFIDENCE_HANDS, self.MIN_CONFIDENCE, 1.0)

    @staticmethod
    def _score(value, reference, invert=False):
        if value is None:
            return None
        mean, spread = reference

        return clamp((mean - value if invert else value - mean) / spread)

    def aggression(self):
        """De -1 (pasivo y selectivo) a +1 (maníaco), mezclando todas las variables de agresión que tenga el perfil."""
        parts = [
            self._score(self.stats.get('af'), self.AF),
            self._score(self.stats.get('raise_vs_bet'), self.RAISE_VS_BET),
            self._score(self.stats.get('cr'), self.CHECK_RAISE),
            self._score(self.vb_ratio, self.VB_RATIO, invert=True),
        ]
        parts = [part for part in parts if part is not None]

        return round(sum(parts) / len(parts), 2) if parts else 0.0

    def fold_tendency(self):
        """De -1 (no foldea nunca) a +1 (foldea mucho ante una apuesta)."""
        return round(self._score(self.stats.get('f2bet'), self.FOLD_TO_BET) or 0.0, 2)

    def raise_frequency(self):
        """% de veces que sube cuando se enfrenta a una apuesta (None si no hay datos)."""
        raised, folded = self.stats.get('raise_vs_bet'), self.stats.get('f2bet')
        if raised is None or folded is None:
            return None

        return round((100 - float(folded)) * float(raised) / 100, 1)

    def bet_frequency(self, had_initiative):
        """% de veces que apuesta en el flop: su c-bet si tenía la iniciativa, su donk si no (None si no hay datos)."""
        value = self.stats.get('cbet' if had_initiative else 'donk')

        return None if value is None else float(value)

    @staticmethod
    def level(score):
        for limit, name in ((0.6, 'very high'), (0.25, 'high'), (-0.25, 'normal'), (-0.6, 'low')):
            if score >= limit:
                return name

        return 'very low'

    def lines(self):
        """La lectura del rival en texto para el cuestionario."""
        if not self.confidence:
            return ['   Read of this opponent: unknown (no statistics): do not adapt to him.']

        agg = self.aggression()
        facts = []
        if self.stats.get('af') is not None:
            facts.append(f"aggression factor {self.stats['af']}")
        if self.raise_frequency() is not None:
            facts.append(f'raises {self.raise_frequency()}% of the bets it faces')
        if self.stats.get('cr') is not None:
            facts.append(f"check-raises {self.stats['cr']}%")
        if self.vb_ratio is not None:
            facts.append(f'{self.vb_ratio} value bets per bluff')
        lines = [f"   Read of this opponent: aggression {self.level(agg).upper()} ({'; '.join(facts)})."]
        if self.stats.get('f2bet') is not None:
            lines.append(f"   Folds to bets {self.stats['f2bet']}% ({self.level(self.fold_tendency()).upper()} for an average opponent). "
                         f'Reliability of the read: {round(self.confidence * 100)}% ({self.sample} hands).')

        return lines

    def type(self):
        """El tipo de jugador que es, calculado de sus estadísticas (lo decide una herramienta, no el modelo)."""
        if not self.confidence:
            return 'unknown'
        agg, fold = self.aggression(), self.fold_tendency()
        if agg >= 0.6 and fold <= -0.3:
            return 'maniac'
        if fold <= -0.4 and agg < 0.25:
            return 'calling_station'   # casi no foldea nunca y no agrede
        if agg >= 0.25:
            return 'tight_aggressive' if fold >= 0.2 else 'loose_aggressive'
        if agg <= -0.25:
            return 'nit' if fold >= 0.2 else 'passive'

        return 'balanced'
