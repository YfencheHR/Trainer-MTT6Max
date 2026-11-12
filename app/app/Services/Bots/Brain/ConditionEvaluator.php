<?php

namespace App\Services\Bots\Brain;

/**
 * Evalúa condiciones anidables sobre las características (features) de la situación.
 *
 * Formato (JSON):
 *   { "all": [ cond, cond… ] }          todas
 *   { "any": [ cond, cond… ] }          alguna
 *   { "not": cond }                     negación
 *   { "f": "perceived_rank", "op": "<=", "v": 10 }               hoja
 *   { "f": "equity", "op": ">=", "v": { "ref": "pot_odds" } }    comparar con otra característica
 *
 * Operadores: = != < <= > >= in not_in between (v = [min, max], ambos incluidos).
 * Si falta una característica (null), las comparaciones de orden son falsas.
 */
class ConditionEvaluator
{
    public const OPERATORS = ['=', '!=', '<', '<=', '>', '>=', 'in', 'not_in', 'between'];

    public function passes(?array $cond, array $features): bool
    {
        if ($cond === null || $cond === []) {
            return true; // sin condición: siempre se cumple
        }

        if (array_key_exists('all', $cond)) {
            foreach ($cond['all'] as $c) {
                if (!$this->passes($c, $features)) {
                    return false;
                }
            }

            return true;
        }
        if (array_key_exists('any', $cond)) {
            foreach ($cond['any'] as $c) {
                if ($this->passes($c, $features)) {
                    return true;
                }
            }

            return false;
        }
        if (array_key_exists('not', $cond)) {
            return !$this->passes($cond['not'], $features);
        }

        return $this->leaf($cond, $features);
    }

    private function leaf(array $c, array $features): bool
    {
        $left = $features[$c['f'] ?? ''] ?? null;
        $op = $c['op'] ?? '=';
        $right = $this->value($c['v'] ?? null, $features);

        switch ($op) {
            case '=':
                return $this->equal($left, $right);
            case '!=':
                return !$this->equal($left, $right);
            case 'in':
                return is_array($right) && $this->inList($left, $right);
            case 'not_in':
                return is_array($right) && !$this->inList($left, $right);
            case 'between':
                if (!is_array($right) || count($right) < 2 || $left === null) {
                    return false;
                }
                [$a, $b] = [$this->value($right[0], $features), $this->value($right[1], $features)];

                return is_numeric($left) && $left >= $a && $left <= $b;
        }

        if ($left === null || $right === null || !is_numeric($left) || !is_numeric($right)) {
            return false;
        }

        return match ($op) {
            '<' => $left < $right,
            '<=' => $left <= $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            default => false,
        };
    }

    /** Resuelve referencias {"ref": "feature"} y valores literales. */
    public function value(mixed $v, array $features): mixed
    {
        if (is_array($v) && array_key_exists('ref', $v)) {
            $x = $features[$v['ref']] ?? null;
            if (is_numeric($x) && isset($v['scale'])) {
                $x *= $v['scale'];
            }
            if (is_numeric($x) && isset($v['add'])) {
                $x += $v['add'];
            }
            if (is_numeric($x) && isset($v['mul'])) {
                $m = $features[$v['mul']] ?? null;
                $x = is_numeric($m) ? $x * $m : null; // multiplica por otra característica
            }
            if (is_numeric($x) && isset($v['min'])) {
                $x = min($x, $v['min']);
            }
            if (is_numeric($x) && isset($v['max'])) {
                $x = max($x, $v['max']);
            }

            return $x;
        }

        return $v;
    }

    private function equal(mixed $a, mixed $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return $a === $b;
    }

    private function inList(mixed $x, array $list): bool
    {
        foreach ($list as $item) {
            if ($this->equal($x, $item)) {
                return true;
            }
        }

        return false;
    }
}
