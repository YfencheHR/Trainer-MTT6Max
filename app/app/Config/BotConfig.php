<?php

namespace App\Config;

class BotConfig
{
    private static $config = null;

    private static function loadConfig()
    {
        if (self::$config === null) {
            $configPath = __DIR__ . '/bot-types.json';
            self::$config = json_decode(file_get_contents($configPath), true);
        }
        return self::$config;
    }

    public static function getTipos()
    {
        $config = self::loadConfig();
        return $config['tipos'];
    }

    public static function getPerfiles()
    {
        $config = self::loadConfig();
        return $config['perfiles'];
    }

    public static function getNiveles()
    {
        $config = self::loadConfig();
        return $config['niveles'];
    }

    public static function getNivel($id)
    {
        $niveles = self::getNiveles();
        return collect($niveles)->firstWhere('id', $id);
    }

    public static function getStatLabels()
    {
        $config = self::loadConfig();
        return $config['stat_labels'];
    }

    public static function getStatLabel($stat)
    {
        $labels = self::getStatLabels();
        return $labels[$stat] ?? ucfirst(str_replace('_', ' ', $stat));
    }
}
