"""El rango de un jugador después de lo que hizo en el flop: la historia de la mano entre calles.

Un jugador llega al flop con un rango (el que sale de su línea preflop) y cada cosa que hace en el flop lo estrecha:
  - apostar deja solo las manos que apostarían (sus mejores y algunos faroles);
  - pasar deja las que NO habrían apostado;
  - pagar una apuesta deja las del medio: ni las que habrían subido ni las que habrían foldeado;
  - subir deja las que subirían.
Las frecuencias salen de las estadísticas de su perfil en el flop (su c-bet, lo que foldea, lo que sube). Sin una estadística no se estrecha:
sin dato no se inventa. Es un cálculo exacto sobre las manos: el modelo solo lee el resultado.
"""

from trainer_mtt.poker.hand_range import ComboRange
from trainer_mtt.poker.range_narrowing import RangeNarrower
from trainer_mtt.poker.range_scorer import RangeScorer

AGGRESSIVE = ('bet', 'raise', 'allin')


class RangeStory:
    def __init__(self, flop):
        self.scorer = RangeScorer(flop)
        self.narrower = RangeNarrower(self.scorer)

    @staticmethod
    def kinds(street_history, position):
        """Lo que hizo esa posición en la calle, en orden: bet, check, call, raise o fold (la primera agresión es la apuesta)."""
        bet_open, out = False, []
        for _, who, action, _amount in (h[:4] for h in street_history):
            if action in AGGRESSIVE:
                kind = 'raise' if bet_open else 'bet'
                bet_open = True
            elif action in ('call', 'check', 'fold'):
                kind = action
            else:
                continue
            if who == position:
                out.append(kind)

        return out

    def table(self, combos):
        """Tabla de fuerza contra un rango, para ordenar las manos de quien se estrecha."""
        return self.scorer.table(combos)

    def narrow(self, combos, table, kinds, stats, initiative, vb_ratio=None):
        """(ComboRange, [lo que se aplicó]) tras las acciones `kinds` del jugador en el flop, según las estadísticas de su perfil."""
        current = list(combos)
        steps = []
        for kind in kinds:
            if kind == 'fold' or not current:
                break
            narrowed, text = self._step(current, table, kind, stats or {}, initiative, vb_ratio)
            if text:
                steps.append(text)
                current = narrowed

        return ComboRange(current), steps

    # ------------------------------------------------------------------ una acción

    def _step(self, current, table, kind, stats, initiative, vb_ratio):
        bet = stats.get('cbet' if initiative else 'donk')
        if bet is None:
            bet = stats.get('bet')   # turn y river: «apuesta cuando le pasan», con o sin iniciativa
        fold, share = stats.get('f2bet'), stats.get('raise_vs_bet')
        raised = None if fold is None or share is None else (100 - float(fold)) * float(share) / 100
        if kind == 'bet' and bet is not None:
            chosen = self.narrower.after_action(current, table, bet, vb_ratio)

            return (chosen.combos(), f'bet (bets {round(float(bet))}% of the time in this spot)') if chosen else (current, None)
        if kind == 'check' and bet is not None:
            chosen = self.narrower.after_action(current, table, bet, vb_ratio)

            return (self._without(current, chosen.combos()), f'check (bets {round(float(bet))}% of the time, so these are the hands that would not bet)') \
                if chosen else (current, None)
        if kind == 'raise' and raised is not None:
            chosen = self.narrower.after_action(current, table, raised, vb_ratio)

            return (chosen.combos(), f'raise (raises {round(raised, 1)}% of the time against a bet)') if chosen else (current, None)
        if kind == 'call' and fold is not None:
            middle = self._middle(current, table, raised or 0.0, 100 - float(fold))

            return (middle, f'call (folds {round(float(fold))}% and raises {round(raised or 0.0, 1)}%, so the middle of the range)') if middle else (current, None)

        return current, None

    @staticmethod
    def _without(combos, removed):
        """Los combos menos el peso que ya se llevó la otra lista."""
        gone = {tuple(map(str, cards)): weight for cards, weight in removed}
        out = []
        for cards, weight in combos:
            left = weight - gone.get(tuple(map(str, cards)), 0.0)
            if left > 1e-9:
                out.append((cards, left))

        return out

    def _middle(self, combos, table, top_pct, bottom_pct):
        """Las manos entre el `top_pct` % y el `bottom_pct` % del rango, ordenadas de más fuerte a más débil."""
        ranked = sorted(((self.scorer.score(cards, table), cards, weight) for cards, weight in combos), key=lambda item: -item[0])
        total = sum(weight for _, _, weight in ranked)
        low, high = total * top_pct / 100, total * bottom_pct / 100
        kept, start = [], 0.0
        for _, cards, weight in ranked:
            keep = min(start + weight, high) - max(start, low)
            if keep > 1e-9:
                kept.append((cards, keep))
            start += weight

        return kept
