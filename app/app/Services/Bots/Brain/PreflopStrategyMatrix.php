<?php

namespace App\Services\Bots\Brain;

use App\Models\Bot;
use App\Services\Bots\RivalPreflopTables;

/**
 * Decisión preflop de los bots que juegan (los perfiles Pool, que salen de la importación): salen de las tablas por mano y
 * posición que se generan de sus estadísticas. Los perfiles base de los seeds no juegan (solo sirven de plantilla en la
 * importación), así que para cualquier otro bot, y fuera del preflop, devuelve null y deciden las reglas del cerebro.
 */
class PreflopStrategyMatrix
{
    private const POSITIONS = ['UTG', 'HJ', 'CO', 'BTN', 'SB', 'BB'];

    public function __construct(private RivalPreflopTables $poolTables)
    {
    }

    public function forDecision(array $ctx, array $features): ?array
    {
        if (($features['street'] ?? null) !== 'preflop') {
            return null;
        }

        // Sin el filtro de archivados: en torneos nuevos el bot es una copia congelada (archivada) del perfil
        $bot = isset($ctx['me']['bot_id']) ? Bot::withoutGlobalScope('active')->find($ctx['me']['bot_id']) : null;

        return $bot?->isPool() ? $this->forPoolDecision($bot, $features) : null;
    }

    /** Reparto de acciones (en %) que la tabla del bot da a `$features['hand_code']` en la situación descrita por `$features`. */
    public function forPoolDecision(Bot $bot, array $features): array
    {
        $position = $features['position'] ?? '';
        $handCode = $features['hand_code'] ?? '';
        // Tabla del tramo de stack del bot (stack efectivo en ciegas grandes); sin dato de stack, la tabla general
        $stackBb = $features['effective_stack_bb'] ?? $features['stack_bb'] ?? null;
        $bucket = is_numeric($stackBb) && (float) $stackBb > 0 ? BotProfile::stackBucketFor((float) $stackBb) : null;
        $table = in_array($position, self::POSITIONS, true) && $this->poolTables->hasHand($handCode)
            ? $this->poolTables->frequenciesFor($bot, $position, $handCode, $bucket)
            : null;
        $raises = (int) ($features['preflop_raises'] ?? 0);
        $limpedByBot = ! empty($features['i_limped_preflop']);

        if (! $table) {
            $distribution = ['fold' => 100.0];
            $scenario = 'pool_missing_hand';
        } elseif ($raises === 0 && ($iso = ((int) ($features['limpers'] ?? 0)) > 0 ? $this->poolTables->isoFrequencies($bot, $position, $handCode) : null)) {
            // Limpers delante y nadie ha subido: no es abrir la acción. Sube (aísla) según lo que el perfil hace ante limpers en esa posición.
            $distribution = [
                'open_2_5bb' => $iso['raise'],
                'call' => $iso['call'],
                'fold' => max(0.0, 100.0 - $iso['raise'] - $iso['call']),
            ];
            $scenario = 'pool_vs_limp';
        } elseif ($raises === 0) {
            $attack = $table['attack'];
            $distribution = [
                'open_2_5bb' => $attack['open'],
                'call' => $attack['limp'],
                'fold' => max(0.0, 100.0 - $attack['open'] - $attack['limp']),
            ];
            $scenario = 'pool_attack';
        } elseif ($raises >= 2 && ! empty($features['i_raised_preflop'])) {
            $response = $table['attack']['open_response'];
            $distribution = [
                'raise_x3' => $response['reraise'],
                'call' => $response['call'],
                'fold' => $response['fold'],
            ];
            $scenario = 'pool_open_response';
        } elseif ($limpedByBot) {
            $response = $table['attack']['limp_response'];
            // Lo que ni sube ni hace all-in se reparte entre igualar y foldear según lo que el perfil hace tras limpear (antes todo era fold)
            $rest = $this->poolTables->limpContinuation($bot, $position, $handCode, (float) $response['raise'], (float) $response['push']);
            $distribution = [
                'raise_x3' => $response['raise'],
                'allin' => $response['push'],
                'call' => $rest['call'],
                'fold' => $rest['fold'],
            ];
            $scenario = 'pool_limp_response';
        } elseif ($raises >= 2 && $position !== 'UTG') {
            // Alguien abrió y otro hizo 3-bet (o más) sin que este bot haya subido: solo continúa con lo más fuerte de su defensa.
            $response = $this->poolTables->coldVsReraise($bot, $position, $handCode, $raises, $bucket);
            $distribution = [
                'raise_x3' => $response['raise'],
                'call' => $response['call'],
                'fold' => $response['fold'],
            ];
            $scenario = 'pool_cold_vs_reraise';
        } elseif ($position === 'UTG') {
            $distribution = ['fold' => 100.0];
            $scenario = 'pool_no_utg_defense';
        } else {
            $defense = $table['defense'];
            $distribution = [
                'raise_x3' => $defense['three_bet'],
                'call' => $defense['call'],
                'fold' => $defense['fold'],
            ];
            $scenario = 'pool_defense';
        }

        // Con stack corto (hasta 20 bb) parte de las subidas son all-in, en la proporción que muestra el perfil en ese tramo
        $push = $this->poolTables->pushShares($bot, $bucket);
        foreach (['open_2_5bb' => $push['raise'], 'raise_x3' => $push['raise'], 'call' => $scenario === 'pool_attack' ? $push['limp'] : 0.0] as $action => $share) {
            if ($share > 0 && ($distribution[$action] ?? 0) > 0) {
                $distribution['allin'] = ($distribution['allin'] ?? 0.0) + $distribution[$action] * $share;
                $distribution[$action] *= 1.0 - $share;
            }
        }

        return [
            'source' => 'pool_tables',
            'scenario' => $scenario,
            'hand_code' => $handCode,
            'position' => $position,
            'stack_bucket' => $bucket,
            'actions' => [],
            'distribution' => $this->normalize($distribution) ?: ['fold' => 100.0],
            'prior_weight' => 1.0,
            'opponent_adjustment' => null,
        ];
    }

    private function normalize(array $weights): array
    {
        $weights = array_filter($weights, fn ($weight) => is_numeric($weight) && $weight > 0);
        $total = array_sum($weights);
        if ($total <= 0) {
            return [];
        }

        return array_map(fn ($weight) => (float) $weight * 100 / $total, $weights);
    }
}
