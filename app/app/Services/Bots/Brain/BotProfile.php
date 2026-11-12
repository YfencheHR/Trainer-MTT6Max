<?php

namespace App\Services\Bots\Brain;

use App\Models\Bot;
use App\Models\BotHudStat;
use App\Models\BotRange;
use Illuminate\Support\Collection;

/**
 * Perfil preflop de un bot, leído de SUS datos (para autoconfigurar la matriz preflop).
 *
 * Fuentes, de más a menos específica:
 *   1. bot_ranges     rangos explícitos por mano, posición, acción y profundidad de stack
 *   2. bot_hud_stats  estadísticas (RFI, 3-bet, fold a 3-bet, cold call, defensa de ciegas, squeeze…)
 *                     por posición / acción / rango de ciegas, con jerarquía:
 *                     (posición+acción+ciegas) → (posición+acción) → (posición) → general
 *   3. bots           VPIP y PFR generales + cifras documentadas del perfil (3-bet, fold a 3-bet…)
 *
 * Todo se expresa como PERCENTIL de manos (0-100) sobre el ranking de manos iniciales: "abre el 14 %"
 * significa abrir con las manos cuyo preflop_rank <= 14. Lo que falta se deriva de lo que hay, escalando
 * por posición (UTG más tight, BTN más amplio…).
 */
class BotProfile
{
    public const POSITIONS = ['UTG', 'HJ', 'CO', 'BTN', 'SB', 'BB'];

    /** En la BD la posición intermedia se llama MP. */
    private const DB_POSITION = ['HJ' => 'MP'];

    /** Cuánto se abre / se juega / se resube según la posición, respecto al valor general del bot. */
    private const OPEN_F = ['UTG' => 0.65, 'HJ' => 0.85, 'CO' => 1.15, 'BTN' => 1.6, 'SB' => 1.3, 'BB' => 1.0];
    private const VPIP_F = ['UTG' => 0.7, 'HJ' => 0.85, 'CO' => 1.05, 'BTN' => 1.35, 'SB' => 1.1, 'BB' => 1.5];
    private const THREE_F = ['UTG' => 0.7, 'HJ' => 0.8, 'CO' => 1.0, 'BTN' => 1.25, 'SB' => 1.3, 'BB' => 1.3];

    /** Cifras documentadas de cada perfil (docs/03-Bots.md) para lo que no esté en la BD. */
    public const DOCUMENTED = [
        'setminer' => ['three_bet' => 1, 'fold_to_3bet' => 85, 'four_bet' => 1, 'fold_to_4bet' => 90, 'af_preflop' => 0.8],
        'roca' => ['three_bet' => 2, 'fold_to_3bet' => 80, 'four_bet' => 2, 'fold_to_4bet' => 85, 'af_preflop' => 0.9],
        'nit' => ['three_bet' => 5, 'fold_to_3bet' => 65, 'four_bet' => 4, 'fold_to_4bet' => 70, 'af_preflop' => 1.5],
        'omc' => ['three_bet' => 1, 'fold_to_3bet' => 70, 'four_bet' => 1, 'fold_to_4bet' => 85, 'af_preflop' => 0.6],
        'tag' => ['three_bet' => 8, 'fold_to_3bet' => 35, 'four_bet' => 9, 'fold_to_4bet' => 55, 'af_preflop' => 2.2],
        'lag' => ['three_bet' => 12, 'fold_to_3bet' => 22, 'four_bet' => 12, 'fold_to_4bet' => 45, 'af_preflop' => 3.1],
        'calling_station' => ['three_bet' => 3, 'fold_to_3bet' => 25, 'four_bet' => 3, 'fold_to_4bet' => 35, 'af_preflop' => 0.8],
        'maniaco' => ['three_bet' => 18, 'fold_to_3bet' => 12, 'four_bet' => 15, 'fold_to_4bet' => 25, 'af_preflop' => 3.9],
        'ballena' => ['three_bet' => 2, 'fold_to_3bet' => 40, 'four_bet' => 2, 'fold_to_4bet' => 50, 'af_preflop' => 0.4],
    ];

    /**
     * Estadísticas POSTFLOP por defecto de cada perfil. Son SUPUESTOS razonados a partir de cómo describe la
     * documentación a cada jugador, hasta que la importación de PokerTracker llene bot_hud_stats con datos reales.
     * f2bet = % de fold ante una apuesta · af = agresión · cbet/donk/bet = % de apostar cuando le toca
     * raise_vs_bet = % de subida del total que continúa ante una apuesta (null = se deduce de af) · cr = check-raise.
     */
    public const DOCUMENTED_POSTFLOP = [
        'setminer' => ['flop' => ['f2bet' => 55, 'af' => 0.6, 'cbet' => 30, 'donk' => 4, 'cr' => 2], 'turn' => ['f2bet' => 60, 'af' => 0.6, 'bet' => 22, 'cr' => 2], 'river' => ['f2bet' => 62, 'af' => 0.6, 'bet' => 18], 'vb_ratio' => 4.0],
        'roca' => ['flop' => ['f2bet' => 48, 'af' => 0.8, 'cbet' => 40, 'donk' => 4, 'cr' => 3], 'turn' => ['f2bet' => 52, 'af' => 0.8, 'bet' => 30, 'cr' => 3], 'river' => ['f2bet' => 55, 'af' => 0.8, 'bet' => 25], 'vb_ratio' => 5.0],
        'nit' => ['flop' => ['f2bet' => 55, 'af' => 1.5, 'cbet' => 70, 'donk' => 6, 'cr' => 4], 'turn' => ['f2bet' => 55, 'af' => 1.5, 'bet' => 50, 'cr' => 4], 'river' => ['f2bet' => 52, 'af' => 1.3, 'bet' => 35], 'vb_ratio' => 3.0],
        'omc' => ['flop' => ['f2bet' => 42, 'af' => 0.6, 'cbet' => 25, 'donk' => 6, 'cr' => 2], 'turn' => ['f2bet' => 45, 'af' => 0.6, 'bet' => 20, 'cr' => 2], 'river' => ['f2bet' => 48, 'af' => 0.6, 'bet' => 18], 'vb_ratio' => 3.5],
        'tag' => ['flop' => ['f2bet' => 40, 'af' => 2.2, 'cbet' => 65, 'donk' => 8, 'cr' => 8], 'turn' => ['f2bet' => 38, 'af' => 2.2, 'bet' => 52, 'cr' => 7], 'river' => ['f2bet' => 38, 'af' => 2.0, 'bet' => 40], 'vb_ratio' => 2.0],
        'lag' => ['flop' => ['f2bet' => 30, 'af' => 3.1, 'cbet' => 75, 'donk' => 12, 'cr' => 12], 'turn' => ['f2bet' => 30, 'af' => 3.1, 'bet' => 62, 'cr' => 10], 'river' => ['f2bet' => 30, 'af' => 2.8, 'bet' => 50], 'vb_ratio' => 1.3],
        'calling_station' => ['flop' => ['f2bet' => 8, 'af' => 0.8, 'cbet' => 20, 'donk' => 10, 'cr' => 0, 'raise_vs_bet' => 0], 'turn' => ['f2bet' => 8, 'af' => 0.8, 'bet' => 15, 'cr' => 0, 'raise_vs_bet' => 0], 'river' => ['f2bet' => 12, 'af' => 0.8, 'bet' => 12, 'raise_vs_bet' => 10], 'vb_ratio' => 4.0],
        'maniaco' => ['flop' => ['f2bet' => 12, 'af' => 3.9, 'cbet' => 85, 'donk' => 30, 'cr' => 15], 'turn' => ['f2bet' => 12, 'af' => 3.9, 'bet' => 75, 'cr' => 15], 'river' => ['f2bet' => 12, 'af' => 3.6, 'bet' => 65], 'vb_ratio' => 0.8],
        'ballena' => ['flop' => ['f2bet' => 20, 'af' => 0.5, 'cbet' => 30, 'donk' => 12, 'cr' => 3], 'turn' => ['f2bet' => 20, 'af' => 0.5, 'bet' => 25, 'cr' => 3], 'river' => ['f2bet' => 22, 'af' => 0.5, 'bet' => 22], 'vb_ratio' => 1.5],
    ];

    /** Tramos de profundidad de stack (en ciegas grandes) de la columna rango_ciegas. */
    public const BUCKETS = [
        '0-6' => [0, 6], '6-25' => [6, 25], '25-40' => [25, 40], '40-80' => [40, 80],
        '80-100' => [80, 100], '100-200' => [100, 200], '200+' => [200, 100000],
    ];

    /**
     * @return array{
     *   source: string,
     *   global: array,
     *   positions: array,
     *   buckets: array<string, array>,
     *   ranges: array
     * }
     */
    public static function forBot(Bot $bot): array
    {
        $rows = $bot->relationLoaded('hudStats') ? $bot->hudStats : $bot->hudStats()->get();
        $ranges = BotRange::where('bot_id', $bot->id)->get();

        $doc = self::DOCUMENTED[$bot->templateTipo()] ?? self::DOCUMENTED['tag'];
        $global = [
            'vpip' => (float) ($bot->vpip ?? 20),
            'pfr' => (float) ($bot->pfr ?? 15),
        ] + array_map('floatval', $doc);

        // Fila general (sin posición ni acción ni ciegas) del bot: manda sobre los escalares
        $general = $rows->first(fn($r) => $r->posicion === null && $r->accion === null && $r->rango_ciegas === null);
        if ($general) {
            foreach ([
                'vpip', 'pfr', 'three_bet', 'fold_to_3bet', 'four_bet', 'fold_to_4bet', 'af_preflop',
                'limp', 'limp_raise', 'limp_push', 'call_vs_3bet', 'reraise_vs_3bet',
            ] as $k) {
                if ($general->{$k} !== null) {
                    $global[$k] = (float) $general->{$k};
                }
            }
        }

        $stat = fn(?string $bucket) => self::lookup($rows, $bucket);
        $positions = [];
        foreach (self::POSITIONS as $pos) {
            $positions[$pos] = self::derive($stat(null), $global, $pos);
        }

        // Variantes por profundidad de stack (solo los tramos para los que hay datos)
        $buckets = [];
        foreach ($rows->pluck('rango_ciegas')->filter()->unique() as $bucket) {
            foreach (self::POSITIONS as $pos) {
                $buckets[$bucket][$pos] = self::derive($stat($bucket), $global, $pos);
            }
        }

        return [
            'source' => $rows->isNotEmpty() ? 'hud_stats' : 'bot_data',
            'global' => $global,
            'positions' => $positions,
            'buckets' => $buckets,
            'ranges' => self::rangesFrom($ranges),
            'postflop' => self::postflop($bot->templateTipo(), $general, $global, $rows),
        ];
    }

    /**
     * Estadísticas postflop por calle. Lo que haya en bot_hud_stats (fila general) manda; lo que falte sale de la tabla
     * documentada del perfil. Si no hay "raise_vs_bet" se deduce de la agresión: share = 12 × AF (máx. 50 %).
     */
    public static function postflop(string $tipo, ?BotHudStat $row, array $global, ?Collection $rows = null): array
    {
        $d = self::DOCUMENTED_POSTFLOP[$tipo] ?? self::DOCUMENTED_POSTFLOP['tag'];
        $v = fn(string $col) => $row && $row->{$col} !== null ? (float) $row->{$col} : null;

        $map = [
            'flop' => ['f2bet' => 'f2cbet', 'af' => 'af_flop', 'cbet' => 'cbet', 'donk' => 'donk', 'cr' => 'cr_flop', 'f2raise' => 'f2raise_flop'],
            'turn' => ['f2bet' => 'f2beturn', 'af' => 'af_turn', 'bet' => 'beturn', 'cr' => 'cr_turn', 'f2raise' => 'f2raise_turn'],
            'river' => ['f2bet' => 'f2betriver', 'af' => 'af_river', 'bet' => 'betriver', 'bluff' => 'bluff'],
        ];

        $out = [];
        foreach ($map as $street => $cols) {
            $s = $d[$street];
            foreach ($cols as $key => $col) {
                $s[$key] = $v($col) ?? ($s[$key] ?? null);
            }
            $s['sample_size'] = (int) ($row->sample_size ?? 0);
            $s['raise_vs_bet'] = $s['raise_vs_bet'] ?? round(min(50.0, 12 * ($s['af'] ?? 1.0)), 1);

            // Desglose en posición (ip) / fuera de posición (oop) según el papel (importado de PokerTracker):
            //   agg → bet: cuánto apuesta cuando tenía la iniciativa · def → fold / call / raise ante esa apuesta
            foreach (['ip', 'oop'] as $pos) {
                $agg = $rows?->first(fn(BotHudStat $r) => $r->posicion === null && $r->accion === "{$street}_agg_{$pos}" && $r->cbet !== null);
                $def = $rows?->first(fn(BotHudStat $r) => $r->posicion === null && $r->accion === "{$street}_def_{$pos}" && $r->f2cbet !== null);
                if ($agg) {
                    $s[$pos]['agg'] = ['bet' => (float) $agg->cbet, 'n' => (int) $agg->sample_size];
                }
                if ($def) {
                    $s[$pos]['def'] = [
                        'fold' => (float) $def->f2cbet,
                        'call' => (float) $def->call_vs_bet,
                        'raise' => (float) $def->raise_vs_bet,
                        'n' => (int) $def->sample_size,
                    ];
                }
            }
            $out[$street] = $s;
        }
        $out['vb_ratio'] = $v('vb_ratio') ?? $d['vb_ratio'];

        return $out;
    }

    /** Perfil sin datos en la BD, a partir de los valores generales (para plantillas por tipo). */
    public static function fromScalars(string $tipo, float $vpip, float $pfr): array
    {
        $global = ['vpip' => $vpip, 'pfr' => $pfr] + array_map('floatval', self::DOCUMENTED[$tipo] ?? self::DOCUMENTED['tag']);
        $none = fn(string $pos, ?string $action, string $field) => null;
        $positions = [];
        foreach (self::POSITIONS as $pos) {
            $positions[$pos] = self::derive($none, $global, $pos);
        }

        return ['source' => 'bot_data', 'global' => $global, 'positions' => $positions, 'buckets' => [], 'ranges' => [], 'postflop' => self::postflop($tipo, null, $global)];
    }

    // ----------------------------------------------------------------- estadísticas

    /** Buscador con jerarquía: (pos+acción+ciegas) → (pos+acción) → (pos) → null. */
    private static function lookup(Collection $rows, ?string $bucket): \Closure
    {
        return function (string $pos, ?string $action, string $field) use ($rows, $bucket) {
            $dbPos = self::DB_POSITION[$pos] ?? $pos;
            $candidates = [
                [$dbPos, $action, $bucket],
                [$dbPos, $action, null],
                [$dbPos, null, $bucket],
                [$dbPos, null, null],
            ];
            foreach ($candidates as [$p, $a, $b]) {
                $row = $rows->first(fn(BotHudStat $r) => $r->posicion === $p && $r->accion === $a && $r->rango_ciegas === $b && $r->{$field} !== null);
                if ($row) {
                    return (float) $row->{$field};
                }
            }

            return null;
        };
    }

    /**
     * Deriva, para una posición, los percentiles de cada acción. Lo que falte se calcula escalando
     * los valores generales del bot por posición.
     */
    private static function derive(\Closure $stat, array $g, string $pos): array
    {
        $open = $stat($pos, 'opening', 'rfi') ?? $stat($pos, 'opening', 'pfr') ?? $stat($pos, null, 'pfr')
            ?? min(100.0, $g['pfr'] * self::OPEN_F[$pos]);

        $vpipPos = $stat($pos, null, 'vpip') ?? min(100.0, $g['vpip'] * self::VPIP_F[$pos]);
        $limp = $stat($pos, 'opening', 'limp') ?? $stat($pos, null, 'limp')
            ?? ($pos === 'BB' ? 0.0 : max(0.0, $vpipPos - $open) * 0.8);
        $limpRaise = $stat($pos, 'opening', 'limp_raise') ?? $stat($pos, null, 'limp_raise');
        $limpPush = $stat($pos, 'opening', 'limp_push') ?? $stat($pos, null, 'limp_push');

        // Contra una subida
        $three = $stat($pos, 'vs_open', 'three_bet') ?? $stat($pos, null, 'three_bet') ?? $g['three_bet'] * self::THREE_F[$pos];
        $cold = $stat($pos, 'cold_call', 'cold_call') ?? $stat($pos, 'vs_open', 'cold_call') ?? $stat($pos, null, 'cold_call')
            ?? max(($g['vpip'] - $g['pfr']) * 0.7, $three * 0.8);

        // Las ciegas defienden más ancho
        $defense = $stat($pos, 'defense_bb', 'bb_defense') ?? $stat($pos, null, 'bb_defense') ?? ($pos === 'SB' ? $stat($pos, null, 'sb_defense') : null);
        if ($defense !== null && in_array($pos, ['BB', 'SB'], true)) {
            $call = max(0.0, $defense - $three);
        } elseif ($pos === 'BB') {
            $call = max($cold, $g['vpip'] * 0.9 - $three);
        } elseif ($pos === 'SB') {
            $call = max($cold * 0.8, $g['vpip'] * 0.5 - $three);
        } else {
            $call = $cold;
        }

        $squeeze = $stat($pos, 'squeeze', 'squeeze') ?? $three * 0.8;

        // Cuando ABRIÓ y le resubieron (3-bet): continúa con (100 − fold a 3-bet) % de su rango de apertura
        $f3 = $stat($pos, 'vs_3bet', 'fold_to_3bet') ?? $g['fold_to_3bet'];
        $four = $stat($pos, 'vs_3bet', 'four_bet') ?? $g['four_bet'];
        $reraise3 = $stat($pos, 'vs_3bet', 'reraise_vs_3bet') ?? $four;
        $call3 = $stat($pos, 'vs_3bet', 'call_vs_3bet') ?? max(0.0, 100.0 - $f3 - $reraise3);
        $cont3 = $open * (1 - $f3 / 100);
        $fourRank = min($cont3, $open * $four / 100);

        // Cuando resubió y le hicieron 4-bet
        $f4 = $stat($pos, 'vs_4bet', 'fold_to_4bet') ?? $g['fold_to_4bet'];
        $cont4 = $three * (1 - $f4 / 100);

        $r = fn(float $v) => round(max(0.0, min(100.0, $v)), 2);

        return [
            'open' => $r($open),
            'limp' => $r($limp),
            'vs_open' => ['three_bet' => $r($three), 'call' => $r($call)],
            'squeeze' => $r($squeeze),
            'vs_3bet' => [
                'continue' => $r($cont3),
                'four_bet' => $r($fourRank),
                'reraise' => $r($reraise3),
                'call' => $r($call3),
                'fold' => $r($f3),
            ],
            'limp_response' => [
                'raise' => $limpRaise === null ? null : $r($limpRaise),
                'push' => $limpPush === null ? null : $r($limpPush),
            ],
            'vs_4bet' => ['continue' => $r(max($cont4, 1.0))],
        ];
    }

    // ----------------------------------------------------------------- rangos explícitos

    /**
     * Convierte bot_ranges en: posición → acción → [bucket|'*'] → mano => [raise, call, limp] (frecuencias 0-1).
     */
    private static function rangesFrom(Collection $ranges): array
    {
        $out = [];
        foreach ($ranges as $r) {
            if (!$r->posicion || !$r->accion) {
                continue;
            }
            $pos = array_search($r->posicion, self::DB_POSITION, true) ?: $r->posicion;
            $bucket = $r->rango_ciegas ?: '*';
            $hands = [];
            foreach (['raise' => $r->rango_raise, 'call' => $r->rango_call, 'limp' => $r->rango_limp] as $kind => $map) {
                foreach ((array) $map as $hand => $freq) {
                    foreach (self::expand((string) $hand) as $code) {
                        $hands[$code][$kind] = ($hands[$code][$kind] ?? 0) + (float) $freq;
                    }
                }
            }
            if ($hands) {
                $out[$pos][$r->accion][$bucket] = $hands;
            }
        }

        return $out;
    }

    /** "AK" → AKs y AKo · "AKs" → AKs · "TT" → TT */
    private static function expand(string $hand): array
    {
        $hand = trim($hand);
        if (strlen($hand) === 2 && $hand[0] !== $hand[1]) {
            return [$hand . 's', $hand . 'o'];
        }

        return [$hand];
    }
}
