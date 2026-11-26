<?php

namespace App\Services\Bots\Brain;

use Illuminate\Support\Facades\DB;

/**
 * Multiplicadores por textura del board, relativos a la media de la calle (tabla board_texture_stats, importada de PokerTracker).
 *
 * Cada clase se suaviza hacia la media de la calle con su muestra: (aciertos + K × media) / (n + K), y el multiplicador es ese valor entre la
 * media. Con poca muestra vale 1 (no cambia nada). Se acota para que un dato raro no dispare al bot.
 */
class BoardTextureStats
{
    /** Peso de la media de la calle en el suavizado (en «oportunidades»). */
    public const K = 200;

    /** Límites del multiplicador. */
    public const MIN = 0.5;

    public const MAX = 1.6;

    /** @var array<string, array<string, array>>|null calle → textura → fila */
    private static ?array $cache = null;

    /** Para comparar el juego con y sin textura (réplica de torneos). */
    public static bool $disabled = false;

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** @return array<string, array<string, array>> */
    private static function rows(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (DB::table('board_texture_stats')->get() as $r) {
                    self::$cache[$r->street][$r->texture] = (array) $r;
                }
            } catch (\Throwable) {
                // sin la tabla (base sin migrar) o sin datos: los bots juegan como antes
            }
        }

        return self::$cache;
    }

    /**
     * @param  array<int, string>  $board  cartas del board en el formato del motor
     * @return array{texture: string, open: float, fold: float, raise: float}|null null si no hay datos de esa calle o textura
     */
    public static function multipliers(string $street, array $board): ?array
    {
        if (self::$disabled) {
            return null;
        }
        $texture = BoardTexture::classify($board);
        $all = self::rows()[$street] ?? null;
        if (! $texture || ! $all || ! isset($all[$texture])) {
            return null;
        }
        $c = $all[$texture];
        $out = ['texture' => $texture];
        // clave → [columna de aciertos, columna de oportunidades]
        foreach (self::KINDS as $kind => [$hits, $opps]) {
            $allHits = array_sum(array_map(fn ($r) => (int) $r[$hits], $all));
            $allOpps = array_sum(array_map(fn ($r) => (int) $r[$opps], $all));
            $out[$kind] = self::ratio((int) $c[$hits], (int) $c[$opps], $allHits, $allOpps);
        }

        return $out;
    }

    /** Qué se mide: apostar sin apuesta delante (en general, como agresor con c-bet y sin iniciativa con donk) y fold / subida ante una apuesta (en general y ante c-bet). */
    private const KINDS = [
        'open' => ['opens', 'open_opps'], 'cbet' => ['cbets', 'cbet_opps'], 'donk' => ['donks', 'donk_opps'],
        'fold' => ['folds', 'facing'], 'raise' => ['raises', 'facing'],
        'cdef_fold' => ['cdef_folds', 'cdef_opps'], 'cdef_raise' => ['cdef_raises', 'cdef_opps'],
    ];

    private static function ratio(int $hits, int $n, int $allHits, int $allN): float
    {
        if ($allN <= 0 || $allHits <= 0) {
            return 1.0;
        }
        $base = $allHits / $allN;
        $smoothed = ($hits + self::K * $base) / ($n + self::K);

        return round(max(self::MIN, min(self::MAX, $smoothed / $base)), 3);
    }
}
