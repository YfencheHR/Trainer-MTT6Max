<?php

namespace App\Services\Ai;

use App\Models\BotPreflopHandRange;
use App\Models\TournamentPlayer;
use App\Services\Bots\Brain\BotProfile;

/**
 * Rango con el que cada jugador llega al flop según lo que hizo preflop.
 *
 * Parte de las tablas preflop de su bot (por posición, tramo de stack y mano) y multiplica, por cada acción que hizo en la mano, la
 * frecuencia con la que ESA mano habría hecho esa acción: abrir, defender, resubir, responder a un 3-bet… Así, un bot que abrió desde el
 * CO y fue pagado llega con las manos con las que abre desde el CO, cada una con el peso con que las abre.
 *
 * Solo hay rango de los jugadores con bot y tabla; el resto (personas, perfiles sin tabla) no lo tienen y la IA los trata como desconocidos.
 */
class FlopRanges
{
    private const POSITIONS = ['UTG', 'HJ', 'CO', 'BTN', 'SB', 'BB'];

    /** Las manos con menos peso que esto no se envían (el rango son 169 manos como mucho). */
    private const MIN_WEIGHT = 0.005;

    /**
     * @param  array  $ctx  contexto de decisión del motor (HandPlayService::botContext)
     * @return array<int, array<string, float>>  [id del jugador => [mano => peso de 0 a 1]]
     */
    public function forHand(array $ctx): array
    {
        $players = $ctx['players'] ?? [];
        $botIds = TournamentPlayer::whereIn('id', array_column($players, 'id'))->pluck('bot_id', 'id');
        $bb = max(1, (int) ($ctx['big_blind'] ?? 100));

        $out = [];
        foreach ($players as $p) {
            // Un contexto que ya trae el bot de cada jugador (la réplica de manos reales) no pasa por la tabla de jugadores del torneo
            $botId = array_key_exists('bot_id', $p) ? $p['bot_id'] : ($botIds[$p['id']] ?? null);
            $line = $this->line($ctx['history'] ?? [], (int) $p['id']);
            if (! $botId || ! $line || ! in_array($p['position'] ?? null, self::POSITIONS, true)) {
                continue;
            }
            // El tramo de stack es el del inicio de la mano: lo que le queda más lo que ya ha puesto
            $bucket = BotProfile::stackBucketFor(($p['stack'] + ($p['total_in_hand'] ?? 0)) / $bb);
            $range = $this->range($botId, $p['position'], $bucket, $line);
            if ($range) {
                $out[(int) $p['id']] = $range;
            }
        }

        return $out;
    }

    /**
     * Las acciones que hizo el jugador preflop, cada una con las subidas que había antes y con su primera acción de la mano.
     *
     * @return list<array{action: string, raises_before: int, first: ?array}>
     */
    private function line(array $history, int $playerId): array
    {
        $raises = 0;
        $first = null;
        $steps = [];
        foreach ($history as $h) {
            if (($h['street'] ?? '') !== 'preflop') {
                continue;
            }
            $action = $h['action'] ?? '';
            if ((int) ($h['player_id'] ?? 0) === $playerId && $action !== 'fold') {
                $steps[] = ['action' => $action, 'raises_before' => $raises, 'first' => $first];
                $first ??= ['action' => $action, 'raises_before' => $raises];
            }
            if (in_array($action, ['raise', 'allin'], true)) {
                $raises++;
            }
        }

        return $steps;
    }

    /** @return array<string, float> */
    private function range(int $botId, string $position, string $bucket, array $line): array
    {
        $general = BotPreflopHandRange::where(['bot_id' => $botId, 'position' => $position, 'stack_bucket' => 'all'])->get()->keyBy('hand');
        if ($general->isEmpty()) {
            return [];
        }
        $byStack = BotPreflopHandRange::where(['bot_id' => $botId, 'position' => $position, 'stack_bucket' => $bucket])->get()->keyBy('hand');

        $range = [];
        foreach ($general as $hand => $row) {
            // Misma regla que las tablas: la del tramo de stack, salvo que la general se haya editado a mano
            $row = ! $row->is_manual && $byStack->has($hand) ? $byStack[$hand] : $row;
            $weight = 1.0;
            foreach ($line as $step) {
                $weight *= $this->frequency($row, $step) / 100;
            }
            if ($weight >= self::MIN_WEIGHT) {
                $range[$hand] = round($weight, 3);
            }
        }

        return $range;
    }

    /** Con qué frecuencia (en %) esta mano habría hecho esa acción en esa situación. */
    private function frequency(BotPreflopHandRange $row, array $step): float
    {
        $v = fn ($value) => (float) ($value ?? 0);
        $raise = in_array($step['action'], ['raise', 'allin'], true);
        $raises = $step['raises_before'];
        $first = $step['first'];

        if ($first === null) {
            if ($raises === 0) {
                return match (true) {
                    $raise => $v($row->attack_open),
                    $step['action'] === 'call' => $v($row->attack_limp),
                    default => max(0.0, 100 - $v($row->attack_open)), // check de la ciega grande: no subió
                };
            }
            if ($raises === 1) {
                return $raise ? $v($row->defense_three_bet) : $v($row->defense_call);
            }
            $cold = $raises >= 3 ? 'cold4' : 'cold3';

            return $raise ? $v($row->{$cold.'_raise'}) : $v($row->{$cold.'_call'});
        }

        // Ya había actuado antes y responde a una subida
        if ($first['action'] === 'call' && $first['raises_before'] === 0) { // limpeó
            return $raise
                ? ($step['action'] === 'allin' ? $v($row->limp_push) : $v($row->limp_raise))
                : max(0.0, 100 - $v($row->limp_raise) - $v($row->limp_push));
        }
        if ($first['raises_before'] === 0) { // abrió
            return $raise ? $v($row->open_reraise) : $v($row->open_call);
        }

        // Subió después de otra subida (3-bet o más) y le han vuelto a subir
        return $raise ? $v($row->cold4_raise) : $v($row->cold4_call);
    }
}
