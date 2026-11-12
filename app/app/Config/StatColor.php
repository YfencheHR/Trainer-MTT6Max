<?php

namespace App\Config;

class StatColor
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

    public static function getColor($stat, $value)
    {
        $config = self::loadConfig();
        $statConfig = $config[$stat] ?? $config['default'];
        $segments = $statConfig['segments'];

        foreach ($segments as $segment) {
            if ($value >= $segment['min'] && $value < $segment['max']) {
                return self::interpolateSegment($segment, $segments, $value);
            }
        }

        return end($segments)['color'];
    }

    public static function getSegments($stat)
    {
        $config = self::loadConfig();
        $statConfig = $config[$stat] ?? $config['default'];
        return $statConfig['segments'];
    }

    private static function interpolateSegment($segment, $allSegments, $value)
    {
        $progress = ($value - $segment['min']) / ($segment['max'] - $segment['min']);

        $currentIndex = array_search($segment, $allSegments);
        if ($currentIndex === false || $currentIndex >= count($allSegments) - 1) {
            return $segment['color'];
        }

        $nextSegment = $allSegments[$currentIndex + 1];

        return self::interpolateColor($segment['color'], $nextSegment['color'], $progress);
    }

    private static function interpolateColor($color1, $color2, $progress)
    {
        $progress = max(0, min(1, $progress));

        $r1 = hexdec(substr($color1, 1, 2));
        $g1 = hexdec(substr($color1, 3, 2));
        $b1 = hexdec(substr($color1, 5, 2));

        $r2 = hexdec(substr($color2, 1, 2));
        $g2 = hexdec(substr($color2, 3, 2));
        $b2 = hexdec(substr($color2, 5, 2));

        $r = (int) ($r1 + ($r2 - $r1) * $progress);
        $g = (int) ($g1 + ($g2 - $g1) * $progress);
        $b = (int) ($b1 + ($b2 - $b1) * $progress);

        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) .
                     str_pad(dechex($g), 2, '0', STR_PAD_LEFT) .
                     str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }
}
