<?php

namespace App\Services\GameTable;

/**
 * Reparte los asientos sobre el borde de la mesa ovalada.
 * El slot 0 (héroe) queda abajo en el centro y el resto sigue el sentido horario.
 */
class TablePositionService
{
    private const RADIUS_X = 44; // % del ancho
    private const RADIUS_Y = 50; // % del alto

    public function getPositionStyle(int $numPlayers, int $slot): string
    {
        $numPlayers = max(2, $numPlayers);
        $angle = deg2rad(90 + $slot * 360 / $numPlayers);

        $left = 50 + self::RADIUS_X * cos($angle);
        $top = 50 + self::RADIUS_Y * sin($angle);

        return sprintf(
            'position: absolute; left: %.2f%%; top: %.2f%%; width: clamp(118px, 18vw, 142px); transform: translate(-50%%, -50%%);',
            $left,
            $top
        );
    }

    /** Posición de la apuesta: entre el asiento y el centro de la mesa. */
    public function getBetStyle(int $numPlayers, int $slot): string
    {
        $numPlayers = max(2, $numPlayers);
        $angle = deg2rad(90 + $slot * 360 / $numPlayers);
        $radius = abs(cos($angle)) < 0.01 ? 0.48 : 0.58;

        return sprintf(
            'left: %.2f%%; top: %.2f%%;',
            50 + self::RADIUS_X * $radius * cos($angle),
            50 + self::RADIUS_Y * $radius * sin($angle)
        );
    }

    public function getVisibleSeats(int $numPlayers): array
    {
        return $numPlayers > 0 ? range(0, $numPlayers - 1) : [];
    }
}
