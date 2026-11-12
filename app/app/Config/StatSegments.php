<?php

namespace App\Config;

class StatSegments
{
    private static $config = null;

    private static function loadConfig()
    {
        if (self::$config === null) {
            $configPath = __DIR__ . '/stat-colors.json';
            self::$config = json_decode(file_get_contents($configPath), true);
        }
        return self::$config;
    }

    public static function getSegments($stat)
    {
        // Primero intenta obtener segmentos dinámicos basados en equilibrio GTO
        $equilibriumSegments = EquilibriumAnalyzer::getEquilibriumSegments($stat);
        if ($equilibriumSegments !== null) {
            return $equilibriumSegments;
        }

        $config = self::loadConfig();

        // Si existe configuración exacta para este stat, úsala
        if (isset($config[$stat])) {
            return $config[$stat]['segments'];
        }

        // Si no existe, intenta buscar una configuración base
        // Por ejemplo: af_preflop -> af, cbet_ip -> cbet
        $baseStat = explode('_', $stat)[0];
        if (isset($config[$baseStat])) {
            return $config[$baseStat]['segments'];
        }

        // Si tampoco existe, usa la configuración por defecto
        return $config['default']['segments'];
    }
}
