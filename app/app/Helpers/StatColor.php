<?php

namespace App\Helpers;

class StatColor
{
    /**
     * Retorna el color hex basado en el valor de la estadística
     * Verde (Nit) -> Amarillo (Tight) -> Naranja (Balance) -> Rojo (Excesivo)
     */
    public static function getColor($stat, $value)
    {
        $value = (float) $value;

        return match ($stat) {
            'vpip' => self::getVpipColor($value),
            'pfr' => self::getPfrColor($value),
            'tilt' => self::getTiltColor($value),
            default => '#10b981'
        };
    }

    private static function getVpipColor($value)
    {
        // VPIP: Verde (Nit) -> Amarillo (Tight) -> Naranja (Balance) -> Rojo (Excesivo) -> Púrpura (Extremo)
        // Verde: 0-12% (Nit)
        // Amarillo: 12-20% (Tight Nit)
        // Naranja: 20-40% (Balance)
        // Rojo: 40-60% (Excesivo)
        // Púrpura: 60%+ (Extremo)

        if ($value <= 12) {
            return '#10b981'; // Verde - Nit
        } elseif ($value <= 20) {
            $progress = ($value - 12) / 8;
            return self::interpolateColor('#10b981', '#f59e0b', $progress);
        } elseif ($value <= 40) {
            $progress = ($value - 20) / 20;
            return self::interpolateColor('#f59e0b', '#ef4444', $progress);
        } elseif ($value <= 60) {
            $progress = ($value - 40) / 20;
            return self::interpolateColor('#ef4444', '#a855f7', $progress);
        } else {
            return '#a855f7'; // Púrpura - Extremo
        }
    }

    private static function getPfrColor($value)
    {
        // PFR: Verde (Nit) -> Amarillo (Tight) -> Naranja (Balance) -> Rojo (Excesivo)
        // Verde: 0-5% (Nit)
        // Amarillo: 5-10% (Tight Nit)
        // Naranja: 10-20% (Balance)
        // Rojo: 20%+ (Excesivo)

        if ($value <= 5) {
            return '#10b981'; // Verde - Nit
        } elseif ($value <= 10) {
            // Amarillo - Transición Verde->Amarillo->Naranja
            $progress = ($value - 5) / 5;
            return self::interpolateColor('#10b981', '#f59e0b', $progress);
        } elseif ($value <= 20) {
            // Naranja - Transición Amarillo->Naranja->Rojo
            $progress = ($value - 10) / 10;
            return self::interpolateColor('#f59e0b', '#ef4444', $progress);
        } else {
            return '#ef4444'; // Rojo - Excesivo
        }
    }

    private static function getTiltColor($value)
    {
        // TILT: Verde (Bajo) -> Amarillo (Moderado) -> Naranja (Notable) -> Rojo (Alto)
        // Verde: 0-10% (Bajo)
        // Amarillo: 10-30% (Moderado)
        // Naranja: 30-60% (Notable)
        // Rojo: 60%+ (Alto)

        if ($value <= 10) {
            return '#10b981'; // Verde - Bajo
        } elseif ($value <= 30) {
            // Amarillo - Transición Verde->Amarillo->Naranja
            $progress = ($value - 10) / 20;
            return self::interpolateColor('#10b981', '#f59e0b', $progress);
        } elseif ($value <= 60) {
            // Naranja - Transición Amarillo->Naranja->Rojo
            $progress = ($value - 30) / 30;
            return self::interpolateColor('#f59e0b', '#ef4444', $progress);
        } else {
            return '#ef4444'; // Rojo - Alto
        }
    }

    /**
     * Interpola entre dos colores hex
     */
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
