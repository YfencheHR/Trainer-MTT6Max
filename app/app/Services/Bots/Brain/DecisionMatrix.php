<?php

namespace App\Services\Bots\Brain;

/**
 * Matriz de decisión: una TABLA DE PORCENTAJES por acción (suman 100).
 *
 *   fold 30 · call 50 · raise 20
 *
 * Parte con la acción por defecto al 100 % (fold). Las estadísticas del jugador y las reglas van MOVIENDO
 * puntos de unas acciones a otras ("sumando" casillas a call, subida…). Siempre se habla en porcentajes.
 *
 * Al decidir se sortea un único número de 0 a 100 (con dos decimales: 0,00 – 99,99) y la acción es la que
 * ocupa ese tramo de la tabla. Probabilidad de una acción = su porcentaje.
 *  - Una tabla con allin al 100 % no puede fallar: el sorteo siempre devuelve all-in.
 *  - Una tabla con fold al 20 % devolverá fold una de cada cinco veces, aunque la mano sea buena.
 */
class DecisionMatrix
{
    public const TOTAL = 100.0;

    /** @var array<string,float> acción → puntos porcentuales (en orden de inserción) */
    private array $pct;

    public function __construct(string $default = 'fold')
    {
        $this->pct = [$default => self::TOTAL];
    }

    /** Toda la tabla pasa a una sola acción (100 %). */
    public function set(string $action): void
    {
        $this->pct = [$action => self::TOTAL];
    }

    /**
     * Reparte según pesos (se normalizan a 100).
     * Con $only, solo se redistribuye lo que ahora tienen esas acciones; lo demás se conserva.
     *
     * @param  array<string,float>  $weights
     * @param  string[]|null  $only
     */
    public function mix(array $weights, ?array $only = null): void
    {
        $weights = array_filter($weights, fn ($w) => $w > 0);
        $sum = array_sum($weights);
        if ($sum <= 0) {
            return;
        }

        if ($only === null) {
            $this->pct = [];
            $held = self::TOTAL;
        } else {
            $held = 0.0;
            foreach ($only as $a) {
                $held += $this->pct[$a] ?? 0.0;
                unset($this->pct[$a]);
            }
        }
        if ($held <= 0) {
            return;
        }

        foreach ($weights as $action => $w) {
            $this->pct[$action] = ($this->pct[$action] ?? 0.0) + $held * $w / $sum;
        }
        $this->clean();
    }

    /**
     * Mezcla esta distribución con otro prior normalizado.
     *
     * @param  array<string, float|int>  $distribution
     */
    public function blend(array $distribution, float $weight): void
    {
        $weight = max(0.0, min(1.0, $weight));
        $distribution = array_filter($distribution, fn ($value) => is_numeric($value) && $value > 0);
        $incomingTotal = array_sum($distribution);
        $currentTotal = array_sum($this->pct);

        if ($weight <= 0 || $incomingTotal <= 0 || $currentTotal <= 0) {
            return;
        }

        $incoming = array_map(fn ($value) => (float) $value / $incomingTotal, $distribution);
        $current = array_map(fn ($value) => $value / $currentTotal, $this->pct);
        $actions = array_unique([...array_keys($current), ...array_keys($incoming)]);
        $this->pct = [];

        foreach ($actions as $action) {
            $value = (1 - $weight) * ($current[$action] ?? 0.0)
                + $weight * ($incoming[$action] ?? 0.0);
            if ($value > 1e-9) {
                $this->pct[$action] = $value * self::TOTAL;
            }
        }

        $this->clean();
    }

    /**
     * Mueve $points puntos a una acción, quitándolos de las demás (a partes proporcionales a lo que tienen)
     * o solo de las acciones de $from. No mueve más de lo que haya disponible. Devuelve lo movido.
     *
     * @param  string[]|null  $from
     */
    public function add(string $action, float $points, ?array $from = null): float
    {
        $points = max(0.0, $points);
        $sources = $from ?? array_keys($this->pct);
        $sources = array_values(array_filter($sources, fn ($a) => $a !== $action && ($this->pct[$a] ?? 0) > 0));
        $available = array_sum(array_map(fn ($a) => $this->pct[$a], $sources));
        $moved = min($points, $available);
        if ($moved <= 0) {
            return 0.0;
        }

        foreach ($sources as $a) {
            $this->pct[$a] -= $moved * $this->pct[$a] / $available;
        }
        $this->pct[$action] = ($this->pct[$action] ?? 0.0) + $moved;
        $this->clean();

        return $moved;
    }

    /** Toda la cuota que tienen las acciones de $from pasa a $action. */
    public function convert(array $from, string $action): void
    {
        $held = 0.0;
        foreach ($from as $a) {
            if ($a !== $action) {
                $held += $this->pct[$a] ?? 0.0;
                unset($this->pct[$a]);
            }
        }
        $this->pct[$action] = ($this->pct[$action] ?? 0.0) + $held;
        $this->clean();
    }

    /**
     * Imprecisión: $rate puntos salen de las acciones NO protegidas (en proporción) y se reparten entre
     * las acciones de error según sus pesos.
     *
     * @param  array<string,float>  $mistakes
     * @param  string[]  $protect
     */
    public function lapse(float $rate, array $mistakes, array $protect = []): void
    {
        $sources = array_values(array_diff(array_keys($this->pct), $protect));
        $available = array_sum(array_map(fn ($a) => $this->pct[$a], $sources));
        $moved = min(max(0.0, $rate), $available);
        $sum = array_sum($mistakes);
        if ($moved <= 0 || $sum <= 0) {
            return;
        }

        foreach ($sources as $a) {
            $this->pct[$a] -= $moved * $this->pct[$a] / $available;
        }
        foreach ($mistakes as $action => $w) {
            $this->pct[$action] = ($this->pct[$action] ?? 0.0) + $moved * $w / $sum;
        }
        $this->clean();
    }

    /** Acción que ocupa el tramo $roll (0 ≤ roll < 100). */
    public function pick(float $roll): string
    {
        $acc = 0.0;
        $last = array_key_last($this->pct);
        foreach ($this->pct as $action => $p) {
            $acc += $p;
            if ($roll < $acc) {
                return $action;
            }
        }

        return $last;
    }

    /** @return array<string,float> acción → % (de mayor a menor) */
    public function distribution(): array
    {
        $out = array_map(fn ($p) => round($p, 2), $this->pct);
        arsort($out);

        return $out;
    }

    public function points(string $action): float
    {
        return $this->pct[$action] ?? 0.0;
    }

    /** Quita acciones sin cuota y corrige el redondeo para que siempre sumen 100. */
    private function clean(): void
    {
        $this->pct = array_filter($this->pct, fn ($p) => $p > 1e-9);
        $sum = array_sum($this->pct);
        if ($sum > 0 && abs($sum - self::TOTAL) > 1e-9) {
            foreach ($this->pct as $a => $p) {
                $this->pct[$a] = $p * self::TOTAL / $sum;
            }
        }
    }
}
