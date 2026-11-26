<?php

namespace App\Services\Replay;

use App\Models\Bot;
use App\Services\Bots\Brain\BotProfile;

/**
 * Convierte una decisión de una mano real (HandHistoryParser) en el contexto que recibe el cerebro de un bot (el mismo que arma
 * HandPlayService::botContext): lo que el bot podía saber en ese momento, con sus cartas.
 *
 * Los stacks de los demás jugadores se reconstruyen con lo que han puesto (las ciegas y los antes no están en las acciones: aproximado);
 * el del jugador que decide es exacto (stack_before). Los rivales que se reconocen como un Pool traen su perfil; el resto no trae estadísticas.
 */
class RealHandContext
{
    /** @var array<int, array> perfil de cada bot ya calculado */
    private array $profiles = [];

    /** @param array<string, Bot> $botByName  jugador real → bot de su perfil */
    public function __construct(private array $botByName) {}

    /**
     * @param  array  $hand  mano de HandHistoryParser::parse()
     * @param  int  $index  posición de la acción en $hand['actions']
     * @param  array|null  $holeCards  cartas con las que se pregunta al bot (si no se dan, las reales: solo las conocen el héroe y quien llega al showdown)
     * @return array|null contexto, o null si no se puede (jugador sin bot o sin cartas)
     */
    public function build(array $hand, int $index, ?array $holeCards = null): ?array
    {
        $a = $hand['actions'][$index] ?? null;
        if (! $a || ! isset($this->botByName[$a['player']])) {
            return null;
        }
        $players = array_values($hand['players']);
        $meIdx = array_search($a['player'], array_column($players, 'name'), true);
        $me = $players[$meIdx] ?? null;
        $holeCards ??= $me['hole_cards'] ?? null;
        if (! $me || ! $holeCards || ! $me['position']) {
            return null;
        }
        $bb = max(1, (int) $hand['big_blind']);
        $sb = (int) ($hand['small_blind'] ?? intdiv($bb, 2));

        // Estado de cada jugador justo antes de esta acción
        $state = [];
        foreach ($players as $k => $p) {
            $blind = $p['position'] === 'SB' ? $sb : ($p['position'] === 'BB' ? $bb : 0);
            $state[$p['name']] = ['id' => $k + 1, 'stack' => $p['stack'] - $blind, 'bet' => $a['street'] === 'preflop' ? $blind : 0, 'total' => $blind, 'folded' => false, 'all_in' => false];
        }
        $history = [];
        foreach (array_slice($hand['actions'], 0, $index) as $prev) {
            $s = &$state[$prev['player']];
            $s['stack'] -= $prev['amount'];
            $s['total'] += $prev['amount'];
            if ($prev['street'] === $a['street']) {
                $s['bet'] += $prev['amount'];
            }
            $s['folded'] = $s['folded'] || $prev['action'] === 'fold';
            $s['all_in'] = $s['all_in'] || ! empty($prev['all_in']);
            unset($s);
            $history[] = [
                'player_id' => $state[$prev['player']]['id'], 'street' => $prev['street'], 'action' => $prev['action'] === 'bet' ? 'raise' : $prev['action'],
                'amount' => $prev['amount'], 'pot_before' => $prev['pot_before'],
            ];
        }
        // `bet` = lo puesto en la calle actual: las ciegas (solo preflop, ya sumadas arriba) y lo apostado en ella; las calles anteriores no cuentan.
        // Los antes no figuran en las acciones, así que el stack de los demás jugadores es aproximado (el de quien decide es exacto: stack_before).

        $toCall = (int) $a['to_call'];
        $stack = (int) $a['stack_before'];
        $facing = $toCall > 0;
        $bot = $this->botByName[$a['player']];

        $table = [];
        foreach ($players as $p) {
            $s = $state[$p['name']];
            $rivalBot = $this->botByName[$p['name']] ?? null;
            $profile = $rivalBot ? $this->profile($rivalBot) : null;
            $table[] = [
                'id' => $s['id'], 'name' => $p['name'], 'position' => $p['position'], 'stack' => max(0, $p['name'] === $a['player'] ? $stack : $s['stack']),
                'bet' => $s['bet'], 'total_in_hand' => $s['total'], 'stats' => ['hands' => 0], 'preflop_stats' => $profile['preflop'] ?? null,
                'postflop_stats' => $profile['postflop'] ?? null, 'folded' => $s['folded'], 'all_in' => $s['all_in'], 'is_bot' => true,
                'bot_id' => $rivalBot?->id,   // sin esto la IA no tiene los rangos de nadie: los ids de aquí no son jugadores de un torneo
            ];
        }
        $own = $this->profile($bot);

        return [
            'replay' => true,   // los ids de jugador de este contexto no existen en la base de datos
            'hand_id' => $hand['hand_id'], 'hand_number' => 1, 'street' => $a['street'], 'big_blind' => $bb,
            'pot' => (int) $a['pot_before'], 'current_bet' => (int) $a['current_bet'], 'to_call' => $toCall,
            'required_equity' => $facing ? $toCall / max(1, $a['pot_before'] + $toCall) : 0.0,
            'board' => $a['board'] ?? [],
            'me' => [
                'id' => $state[$a['player']]['id'], 'bot_id' => $bot->id, 'position' => $me['position'], 'stack' => $stack,
                'bet' => (int) $a['committed_before'], 'total_in_hand' => $state[$a['player']]['total'], 'hole_cards' => $holeCards, 'flags' => [],
                'stats' => $own['stats'],
            ],
            'players' => $table,
            'history' => $history,
            'legal' => [
                'fold' => $facing, 'check' => ! $facing, 'call' => $facing ? min($toCall, $stack) : null,
                'raise' => ['min' => max($bb, $toCall * 2), 'max' => $stack], 'allin' => $stack,
            ],
        ];
    }

    /** @return array{preflop: array, postflop: array, stats: array} */
    private function profile(Bot $bot): array
    {
        if (isset($this->profiles[$bot->id])) {
            return $this->profiles[$bot->id];
        }
        $bot->loadMissing('hudStats');
        $general = $bot->hudStats->first(fn ($r) => $r->posicion === null && $r->accion === null && $r->rango_ciegas === null);
        $post = BotProfile::postflop($bot->templateTipo(), $general, ['vpip' => (float) ($bot->vpip ?? 20), 'pfr' => (float) ($bot->pfr ?? 15)], $bot->hudStats);
        $stats = ['vpip' => $bot->vpip ?? $general->vpip ?? 20, 'pfr' => $bot->pfr ?? $general->pfr ?? 15];
        foreach (['flop', 'turn', 'river'] as $street) {
            $stats[$street] = $post[$street];
            $stats[$street]['vb_ratio'] = $post['vb_ratio'];
        }
        $preflop = ['vpip' => $stats['vpip'], 'pfr' => $stats['pfr'], 'three_bet' => $general->three_bet ?? null, 'hands' => 0, 'source' => 'profile'];

        return $this->profiles[$bot->id] = ['preflop' => $preflop, 'postflop' => $post, 'stats' => $stats];
    }
}
