<?php

namespace App\Config;

class EquilibriumAnalyzer
{
    private static $config = null;

    private static function loadConfig()
    {
        if (self::$config === null) {
            $configPath = __DIR__ . '/gto-equilibrium.json';
            self::$config = json_decode(file_get_contents($configPath), true);
        }
        return self::$config;
    }

    public static function getEquilibriumRange($stat)
    {
        $config = self::loadConfig();

        // Buscar en todas las secciones
        foreach (['preflop', 'flop', 'turn', 'river'] as $section) {
            if (isset($config[$section][$stat])) {
                return $config[$section][$stat];
            }
        }

        return null;
    }

    public static function getEquilibriumSegments($stat)
    {
        $range = self::getEquilibriumRange($stat);

        if (!$range) {
            return null;
        }

        $min = $range['min'];
        $max = $range['max'];
        $mid = ($min + $max) / 2;

        // Crear rangos basados en equilibrio: por debajo, equilibrio, por encima
        $quarters = ($max - $min) / 4;

        return [
            [
                "min" => 0,
                "max" => $min - $quarters,
                "color" => "#10b981",
                "label" => "Por debajo del equilibrio"
            ],
            [
                "min" => $min - $quarters,
                "max" => $min,
                "color" => "#84cc16",
                "label" => "Ligeramente bajo"
            ],
            [
                "min" => $min,
                "max" => $max,
                "color" => "#f59e0b",
                "label" => "Equilibrio"
            ],
            [
                "min" => $max,
                "max" => $max + $quarters,
                "color" => "#f97316",
                "label" => "Ligeramente alto"
            ],
            [
                "min" => $max + $quarters,
                "max" => 999,
                "color" => "#ef4444",
                "label" => "Por encima del equilibrio"
            ]
        ];
    }
}
