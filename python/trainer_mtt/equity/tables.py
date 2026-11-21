"""Tablas heurísticas de ataque y defensa preflop (no son estrategias de equilibrio: cortan por un umbral de equity)."""


class PreflopTables:
    """Genera, con el simulador de equity, qué manos entran en un rango de ataque o de defensa."""

    def __init__(self, simulator):
        self.simulator = simulator
        self.names = simulator.hands.names

    def attack(self, stack_depth_bb, defense_range_percent=20, defense_range_strs=None,
               equity_threshold=0.5, iterations=1000):
        """
        Tabla de equity de ataque para un stack depth.

        Args:
            stack_depth_bb: int - profundidad en bb
            defense_range_percent: int - % usado para construir el rango rival (si defense_range_strs es None)
            defense_range_strs: list - rango específico, ej ['AA', 'KK', 'AKs']
            equity_threshold: float - threshold para incluir mano en rango
            iterations: int - iteraciones por simulación

        Returns:
            dict - {hand: {equity, ev, include, iterations}}
        """
        if defense_range_strs is None:
            defense_range = self.names[:max(1, int(169 * defense_range_percent / 100))]
        else:
            defense_range = defense_range_strs

        result = {}
        for hand in self.names:
            sim = self.simulator.simulate_equity(hand, defense_range, iterations=iterations)
            equity = sim['equity']
            result[hand] = {
                'equity': equity,
                'ev': equity * 2.0 - 1.0,
                'include': equity > equity_threshold,
                'iterations': sim['iterations'],
            }

        return result

    def defense(self, stack_depth_bb, attack_range_percent=15, attack_range_strs=None,
                equity_threshold=0.45, iterations=500):
        """
        Tabla de equity defensiva para un stack depth.

        Args:
            stack_depth_bb: int - profundidad en bb
            attack_range_percent: int - % usado para construir el rango rival (si attack_range_strs es None)
            attack_range_strs: list - rango específico de ataque
            equity_threshold: float - threshold para incluir mano en rango defensivo
            iterations: int - iteraciones por simulación
        """
        if attack_range_strs is None:
            attack_range = self.names[:max(1, int(169 * attack_range_percent / 100))]
        else:
            attack_range = attack_range_strs

        result = {}
        for hand in self.names:
            total_equity = 0
            for attack_hand in attack_range:
                total_equity += self.simulator.simulate_equity(hand, [attack_hand], iterations=iterations)['equity']
            avg_equity = total_equity / len(attack_range)
            result[hand] = {
                'equity': avg_equity,
                'ev': avg_equity * 2.0 - 1.0,
                'include': avg_equity > equity_threshold,
                'iterations': iterations,
            }

        return result
