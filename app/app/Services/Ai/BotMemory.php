<?php

namespace App\Services\Ai;

/**
 * Lo que un bot con IA recuerda de sus rivales según la memoria que se configuró en el torneo.
 *
 *  - none:       el rival es su perfil configurado; lo visto en el torneo no cuenta (la situación estándar).
 *  - tournament: el perfil se corrige con lo observado de ese rival en ESTE torneo (manos ya terminadas), con un peso que crece con la muestra.
 *
 * El perfil sigue siendo el punto de partida: con pocas manos casi todo es perfil; con muchas, lo observado manda. Si el perfil no tiene un dato
 * (p. ej. el c-bet) y se ha observado, se usa lo observado. Nunca se inventa un dato que no existe en ninguno de los dos.
 */
class BotMemory
{
    public const NONE = 'none';
    public const TOURNAMENT = 'tournament';

    /** Muestra a partir de la cual lo observado pesa la mitad (n / (n + K)). */
    public const HALF_WEIGHT = 25;

    public static function mode(array $ctx): string
    {
        return ($ctx['bot_memory'] ?? self::NONE) === self::TOURNAMENT ? self::TOURNAMENT : self::NONE;
    }

    /**
     * @param  array  $profile   ['preflop' => [...], 'postflop' => ['flop' => [...], ...]] del rival, sin lo observado
     * @param  array  $observed  resumen de TournamentPlayerStat::summary()
     */
    public function apply(array $profile, array $observed, string $mode): array
    {
        if ($mode !== self::TOURNAMENT || ($observed['hands'] ?? 0) <= 0) {
            return $profile;
        }
        $preflop = (array) ($profile['preflop'] ?? []);
        $postflop = (array) ($profile['postflop'] ?? []);

        $preflop['vpip'] = $this->mix($preflop['vpip'] ?? null, $observed['vpip'] ?? null, $observed['hands'] ?? 0);
        $preflop['pfr'] = $this->mix($preflop['pfr'] ?? null, $observed['pfr'] ?? null, $observed['hands'] ?? 0);
        $preflop['three_bet'] = $this->mix($preflop['three_bet'] ?? null, $observed['three_bet'] ?? null, $observed['three_bet_opp'] ?? 0);
        $preflop['observed_hands'] = $observed['hands'];

        foreach (['flop', 'turn', 'river'] as $street) {
            $seen = $observed['streets'][$street] ?? [];
            $stats = (array) ($postflop[$street] ?? []);
            $mixed = [
                // Fold ante apuesta y agresión de cada calle; en el flop, el c-bet; en turn y river, «apuesta cuando le pasan»
                'f2bet' => $this->mix($stats['f2bet'] ?? null, $seen['f2bet'] ?? null, $seen['f2bet_opp'] ?? 0),
                'af' => $this->mix($stats['af'] ?? null, $observed['af'] ?? null, $observed['af_n'] ?? 0),
                $street === 'flop' ? 'cbet' : 'bet' => $street === 'flop'
                    ? $this->mix($stats['cbet'] ?? null, $observed['cbet'] ?? null, $observed['cbet_opp'] ?? 0)
                    : $this->mix($stats['bet'] ?? null, $seen['bet'] ?? null, $seen['bet_opp'] ?? 0),
            ];
            foreach (array_filter($mixed, fn ($v) => $v !== null) as $key => $value) {
                $stats[$key] = $value;
            }
            if ($stats) {
                $postflop[$street] = $stats;
            }
        }

        return ['preflop' => array_filter($preflop, fn ($v) => $v !== null), 'postflop' => $postflop];
    }

    /** Mezcla perfil y observado según la muestra; sin uno de los dos devuelve el otro; sin ninguno, null. */
    public function mix(float|int|null $profile, float|int|null $observed, int $sample): float|int|null
    {
        if ($observed === null || $sample <= 0) {
            return $profile;
        }
        if ($profile === null) {
            return (float) $observed;
        }
        $weight = $sample / ($sample + self::HALF_WEIGHT);

        return round($weight * $observed + (1 - $weight) * $profile, 1);
    }
}
