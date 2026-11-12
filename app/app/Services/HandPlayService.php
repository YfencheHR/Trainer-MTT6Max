<?php

namespace App\Services;

use App\Models\TournamentHand;
use Illuminate\Support\Facades\DB;

/**
 * Motor jugable de una mano en mesa 6-max (versión simplificada: una sola ronda
 * de apuestas con fold / call / check / all-in, showdown automático).
 *
 * Estado en BD:
 *  - players_in_hand: lista de jugadores (stack, apuesta, cartas, folded...)
 *  - actions: ['pot', 'turn', 'street', 'log', 'board_full', 'winner_id']
 */
class HandPlayService
{
    private const TABLE_SIZE = 6;
    private const RANKS = ['2', '3', '4', '5', '6', '7', '8', '9', 'T', 'J', 'Q', 'K', 'A'];
    private const SUITS = ['♠', '♥', '♦', '♣'];

    /** Estado público listo para la mesa (oculta cartas rivales hasta el final). */
    public function state(TournamentHand $hand): array
    {
        $this->ensureInitialized($hand);

        $players = $hand->players_in_hand;
        $meta = $hand->actions;
        $completed = $hand->status === 'completed';
        $showAll = (bool) data_get($hand->tournament->config, 'game_settings.show_all_cards', false);

        $n = count($players);
        $btn = (int) ($meta['btn'] ?? 0) % max(1, $n);
        $hideHud = (bool) data_get($hand->tournament->config, 'game_settings.no_hud_data', false);
        $rows = \App\Models\TournamentPlayer::with('bot.hudStats')
            ->whereIn('id', array_column($players, 'id'))->get()->keyBy('id');
        $stats = app(\App\Services\Stats\TournamentStatsService::class)->summaries(array_column($players, 'id'));

        $out = array_map(function ($p, $i) use ($meta, $completed, $showAll, $n, $btn, $hideHud, $rows, $stats) {
            $reveal = !$p['is_bot'] || $showAll || ($completed && !$p['folded']);
            $showBet = (bool) ($p['bet_visible'] ?? false)
                || ($meta['street'] === 'preflop' && (int) $p['bet'] > 0);

            return [
                'id' => $p['id'],
                'name' => $p['name'],
                'stack' => (int) $p['stack'],
                'bet' => (int) $p['bet'],
                'show_bet' => $showBet,
                'is_bot' => $p['is_bot'],
                'is_current' => !$p['is_bot'], // héroe
                'is_turn' => !$completed && $meta['turn'] === $p['id'],
                'is_folded' => $p['folded'],
                'is_all_in' => $p['all_in'],
                'is_eliminated' => false,
                'hole_cards' => $reveal ? $p['hole_cards'] : [],
                'has_rival_data' => !$p['is_bot'],
                'position' => $this->positionLabel($n, ($i - $btn + $n) % $n),
                'stats' => $stats[$p['id']],
                'hud' => $hideHud ? null : $this->hudFor($rows[$p['id']] ?? null, $stats[$p['id']]),
            ];
        }, $players, array_keys($players));

        return [
            'hand_id' => $hand->id,
            'players' => array_values($out),
            'board' => $hand->community_cards ?? [],
            'pot' => (int) $meta['pot'],
            'current_street' => $completed ? 'completed' : $meta['street'],
            'current_player_id' => $completed ? null : $meta['turn'],
            'is_all_in' => collect($players)->contains(fn($p) => $p['all_in']),
            'status' => $hand->status,
            'winner_id' => $meta['winner_id'] ?? null,
            'log' => $this->normalizeLog($meta['log']),
            'current_bet' => $this->maxBet($players),
            'min_raise_to' => $this->minRaiseTo($players, $meta),
            'hand_number' => (int) $hand->hand_number,
            'tournament' => $this->tournamentSummary($hand, $meta),
            'last_action' => $meta['last_action'] ?? null,
            'hero_actions' => $this->heroLegal($players, $meta, $completed),
            'big_blind' => (int) ($meta['bb'] ?? 100),
        ];
    }

    private function history(): \App\Services\History\HandHistoryRecorder
    {
        return app(\App\Services\History\HandHistoryRecorder::class);
    }

    private function log(array &$meta, string $text, string $type = 'info', array $extra = []): void
    {
        $meta['log'][] = ['time' => now()->format('H:i:s'), 'text' => $text, 'type' => $type] + $extra;
    }

    /** Fichas → bb legible (1,5bb). */
    private function bb(int $chips, array $meta): string
    {
        $bb = max(1, (int) ($meta['bb'] ?? 100));
        return rtrim(rtrim(number_format($chips / $bb, 1, ',', ''), '0'), ',') . 'bb';
    }

    /** Admite entradas antiguas (string) y limita el tamaño. */
    private function normalizeLog(array $log, int $limit = 60): array
    {
        return array_values(array_map(
            fn($e) => is_array($e) ? $e : ['time' => '', 'text' => (string) $e, 'type' => 'info'],
            array_slice($log, -$limit)
        ));
    }

    /**
     * Log de las manos ANTERIORES de la misma mesa (de más antigua a más reciente),
     * para no perder el historial al recargar. La mano en curso va en state()['log'].
     */
    public function logHistory(TournamentHand $hand, int $hands = 30): array
    {
        $q = TournamentHand::where('tournament_id', $hand->tournament_id)
            ->where('id', '<', $hand->id)
            ->where('status', 'completed');
        $q = $hand->table_id ? $q->where('table_id', $hand->table_id) : $q->whereNull('table_id');

        $out = [];
        foreach ($q->orderByDesc('id')->limit($hands)->get(['id', 'actions'])->reverse() as $h) {
            foreach ($this->normalizeLog($h->actions['log'] ?? [], 200) as $entry) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /** Datos del torneo para el panel lateral (stacks al inicio de la mano). */
    private function tournamentSummary(TournamentHand $hand, array $meta): array
    {
        $rows = $hand->tournament->players()->get(['id', 'current_stack', 'is_bot', 'is_eliminated']);
        $alive = $rows->where('current_stack', '>', 0);
        $stacks = $alive->pluck('current_stack')->sort()->reverse()->values();
        $hero = $rows->firstWhere('is_bot', false);
        $blinds = $hand->blind_level_config ?? [];
        $bb = (int) ($meta['bb'] ?? $blinds['big_blind'] ?? 200);

        // Mesas activas: jugadores sentados y stack medio
        $tables = [];
        $others = [];
        $eliminationHistory = [];
        if ($hand->table_id) {
            $seatedPlayers = \Illuminate\Support\Facades\DB::table('table_players')
                ->join('tables', 'tables.id', '=', 'table_players.table_id')
                ->join('tournament_players', 'tournament_players.id', '=', 'table_players.tournament_player_id')
                ->where('tables.tournament_id', $hand->tournament_id)
                ->where('tables.is_active', true)
                ->orderBy('tables.table_number')
                ->orderBy('table_players.seat_number')
                ->get([
                    'tables.id as table_id',
                    'tables.table_number',
                    'tournament_players.id as player_id',
                    'tournament_players.name as player_name',
                    'tournament_players.current_stack',
                ]);

            foreach ($seatedPlayers->groupBy('table_id') as $tableId => $playersAtTable) {
                $tableNumber = (int) $playersAtTable->first()->table_number;
                $isHero = (int) $tableId === (int) $hand->table_id;
                $tables[] = [
                    'id' => (int) $tableId,
                    'number' => $tableNumber,
                    'players' => $playersAtTable->count(),
                    'avg_stack' => (int) round($playersAtTable->avg('current_stack') ?? 0),
                    'is_hero' => $isHero,
                    'seated_players' => $playersAtTable->map(fn ($player) => [
                        'id' => (int) $player->player_id,
                        'name' => $player->player_name,
                        'stack' => (int) $player->current_stack,
                    ])->values()->all(),
                ];
                if (!$isHero) {
                    $others[] = ['id' => (int) $tableId, 'number' => $tableNumber];
                }
            }

        }

        $eliminationHistory = \App\Models\TournamentEvent::with('player:id,name')
            ->where('tournament_id', $hand->tournament_id)
            ->where('type', 'elimination')
            ->whereNotNull('tournament_player_id')
            ->orderByDesc('id')
            ->get(['id', 'round', 'tournament_player_id', 'payload'])
            ->map(fn ($event) => [
                'id' => (int) $event->id,
                'player_id' => (int) $event->tournament_player_id,
                'name' => $event->player?->name ?? '',
                'placement' => (int) ($event->payload['placement'] ?? 0),
                'table_number' => (int) ($event->payload['table'] ?? 0),
                'round' => (int) ($event->round ?? 0),
            ])
            ->sortByDesc('placement')
            ->values()
            ->all();

        return [
            'id' => (int) $hand->tournament_id,
            'level' => (int) $hand->level,
            'small_blind' => (int) ($blinds['small_blind'] ?? 100),
            'big_blind' => $bb,
            'players_alive' => $alive->count(),
            'players_total' => $rows->count(),
            'avg_stack' => (int) round($alive->avg('current_stack') ?? 0),
            'max_stack' => (int) ($stacks->first() ?? 0),
            'hero_stack' => (int) ($hero->current_stack ?? 0),
            'hero_rank' => $hero && $hero->current_stack > 0 ? $stacks->search($hero->current_stack) + 1 : null,
            'hero_eliminated' => (bool) ($hero->is_eliminated ?? false),
            'hand_number' => (int) $hand->hand_number,
            'tables' => $tables,
            'other_tables' => $others,
            'elimination_history' => $eliminationHistory,
        ];
    }
    /** Acciones que puede hacer el héroe (solo si es su turno), para pintar los botones. */
    private function heroLegal(array $players, array $meta, bool $completed): ?array
    {
        if ($completed || empty($meta['turn'])) {
            return null;
        }
        $p = $this->find($players, $meta['turn']);
        if ($p['is_bot']) {
            return null;
        }
        $l = $this->legalActions($players, $meta, $p);
        if ($l['raise'] !== null) {
            // La barra expresa lo que se añade ahora; el motor valida el objetivo acumulado.
            $l['raise']['min'] -= (int) $p['bet'];
            $l['raise']['max'] -= (int) $p['bet'];
        }
        $l['to_call'] = $l['call'] ?? 0;
        $l['pot'] = (int) $meta['pot'];
        return $l;
    }

    /** Etiqueta de posición según la distancia al botón (0 = BTN). */
    private function positionLabel(int $n, int $k): string
    {
        if ($n === 2) {
            return $k === 0 ? 'SB' : 'BB';
        }
        $map = [3 => ['BTN', 'SB', 'BB'], 4 => ['BTN', 'SB', 'BB', 'CO'],
            5 => ['BTN', 'SB', 'BB', 'UTG', 'CO'], 6 => ['BTN', 'SB', 'BB', 'UTG', 'HJ', 'CO']];
        return ($map[min($n, 6)] ?? $map[6])[$k] ?? '';
    }

    /**
     * Stats del HUD (VPIP / PFR / 3B) de un rival.
     * Con muestra suficiente (>= MIN_HANDS_FOR_HUD manos) se muestra lo OBSERVADO en el torneo;
     * antes de eso, el perfil configurado del bot. `source` indica cuál se está viendo.
     */
    private function hudFor(?\App\Models\TournamentPlayer $row, array $observed): ?array
    {
        $bot = $row?->bot;
        if (!$bot) {
            // Héroe: solo hay datos observados (sin perfil configurado)
            return [
                'vpip' => $observed['vpip'], 'vpip_color' => $observed['vpip'] !== null ? \App\Config\StatColor::getColor('vpip', $observed['vpip']) : null,
                'pfr' => $observed['pfr'], 'pfr_color' => $observed['pfr'] !== null ? \App\Config\StatColor::getColor('pfr', $observed['pfr']) : null,
                'three_bet' => $observed['three_bet'],
                'four_bet' => null,
                'limp' => null,
                'limp_raise' => null,
                'limp_push' => null,
                'cold_call' => null,
                'hands' => $observed['hands'],
                'source' => 'observed',
            ];
        }

        $useObserved = $observed['hands'] >= \App\Services\Stats\TournamentStatsService::MIN_HANDS_FOR_HUD;
        $stat = $bot->hudStats->first(fn($item) => $item->posicion === null && $item->accion === null && $item->rango_ciegas === null)
            ?? $bot->hudStats->first();

        if ($useObserved) {
            $vpip = $observed['vpip'];
            $pfr = $observed['pfr'];
            $three = $observed['three_bet'];
        } else {
            $vpip = $stat->vpip ?? $bot->vpip;
            $pfr = $stat->pfr ?? $bot->pfr;
            $three = $stat->three_bet ?? null;
        }

        return [
            'vpip' => $vpip, 'vpip_color' => $vpip !== null ? \App\Config\StatColor::getColor('vpip', $vpip) : null,
            'pfr' => $pfr, 'pfr_color' => $pfr !== null ? \App\Config\StatColor::getColor('pfr', $pfr) : null,
            'three_bet' => $three,
            'four_bet' => $stat->four_bet ?? null,
            'limp' => $stat->limp ?? null,
            'limp_raise' => $stat->limp_raise ?? null,
            'limp_push' => $stat->limp_push ?? null,
            'cold_call' => $stat->cold_call ?? null,
            'hands' => $observed['hands'],
            'source' => $useObserved ? 'observed' : 'profile',
        ];
    }

    public function heroAction(TournamentHand $hand, string $action, int $amount = 0): array
    {
        return $this->locked($hand, fn($h) => $this->doHeroAction($h, $action, $amount));
    }

    public function botAction(TournamentHand $hand): array
    {
        return $this->locked($hand, fn($h) => $this->doBotAction($h));
    }

    /**
     * Juega una mano completa de una mesa SIN héroe (mesas secundarias): los bots deciden
     * con el mismo motor y las mismas reglas, pero sin construir el estado de mesa en cada acción.
     */
    public function playQuiet(TournamentHand $hand): void
    {
        $this->ensureInitialized($hand);

        for ($guard = 0; $guard < 500; $guard++) {
            if ($hand->fresh()->status === 'completed') {
                return;
            }
            $this->locked($hand, fn($h) => $this->doBotAction($h, false));
        }

        throw new \RuntimeException('La mano simulada no terminó (mesa ' . $hand->table_id . ')');
    }

    private function locked(TournamentHand $hand, callable $fn): array
    {
        return DB::transaction(function () use ($hand, $fn) {
            $fresh = TournamentHand::whereKey($hand->id)->lockForUpdate()->firstOrFail();
            return $fn($fresh);
        });
    }

    private function doHeroAction(TournamentHand $hand, string $action, int $amount = 0): array
    {
        $this->ensureInitialized($hand);
        $this->assertInProgress($hand);

        $meta = $hand->actions;
        $current = $this->find($hand->players_in_hand, $meta['turn']);
        if ($current['is_bot']) {
            throw new \RuntimeException('No es tu turno');
        }

        return $this->apply($hand, $current['id'], $action, $amount);
    }

    private function doBotAction(TournamentHand $hand, bool $withState = true): array
    {
        $this->ensureInitialized($hand);
        $this->assertInProgress($hand);

        $meta = $hand->actions;
        $current = $this->find($hand->players_in_hand, $meta['turn']);
        if (!$current['is_bot']) {
            throw new \RuntimeException('No es turno de un bot');
        }

        // La mesa solo pregunta "¿qué hago?": el servidor decide con lo que ese bot puede saber
        $ctx = $this->botContext($hand, $current);
        $decision = app(\App\Services\Bots\BotDecisionService::class)->decide($ctx);

        $action = $decision['action'];
        $amount = (int) ($decision['amount'] ?? 0);
        if ($action === 'fold' && !$ctx['legal']['fold']) {
            $action = 'check';
            $amount = 0;
            $decision['reason'] = ($decision['reason'] ?? 'Fold') . ' (convertido a check: no hay apuesta que igualar)';
        }

        // Qué sabía el bot y por qué eligió esto: queda en el historial para analizar y afinar
        unset($ctx['history']); // ya está en hand_actions
        // Las stats no cambian durante la mano: están en hand_players.stats_before (no se repiten)
        $ctx['players'] = array_map(function ($p) {
            unset($p['stats']);
            unset($p['preflop_stats']);
            return $p;
        }, $ctx['players']);
        $record = [
            'reason' => $decision['reason'] ?? null,
            'chosen' => ['action' => $action, 'amount' => $amount],
            'brain' => $decision['trace'] ?? null, // semilla, percepción, reglas aplicadas, matriz, fila y columna
            'context' => $ctx,
        ];

        // Intenciones para el resto de la mano (p. ej. check con intención de subir)
        if (!empty($decision['flags'])) {
            $state = $hand->actions;
            $state['bot_flags'][$current['id']] = array_merge($state['bot_flags'][$current['id']] ?? [], $decision['flags']);
            $hand->actions = $state;
        }
        return $this->apply($hand, $current['id'], $action, $amount, $withState, $record);
    }

    /**
     * Información que un bot puede usar para decidir (nunca las cartas de los rivales).
     */
    private function botContext(TournamentHand $hand, array $me): array
    {
        $players = $hand->players_in_hand;
        $meta = $hand->actions;
        $n = count($players);
        $btn = (int) ($meta['btn'] ?? 0) % max(1, $n);
        $myIdx = $this->indexOf($players, $me['id']);

        $stats = app(\App\Services\Stats\TournamentStatsService::class)->summaries(array_column($players, 'id'));
        $playerRows = \App\Models\TournamentPlayer::with('bot.hudStats')
            ->whereIn('id', array_column($players, 'id'))
            ->get()
            ->keyBy('id');

        $table = [];
        foreach ($players as $i => $p) {
            $playerStats = $stats[$p['id']];
            $bot = $playerRows->get($p['id'])?->bot;
            $postflopStats = null;
            if ($bot) {
                $hudStats = $bot->hudStats;
                $general = $hudStats->first(fn($row) => $row->posicion === null && $row->accion === null && $row->rango_ciegas === null);
                $postflopStats = \App\Services\Bots\Brain\BotProfile::postflop(
                    $bot->templateTipo(),
                    $general,
                    ['vpip' => (float) ($bot->vpip ?? 20), 'pfr' => (float) ($bot->pfr ?? 15)],
                    $hudStats
                );
            }
            $table[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'position' => $this->positionLabel($n, ($i - $btn + $n) % $n),
                'stack' => $p['stack'],
                'bet' => $p['bet'],
                'total_in_hand' => $p['total'] ?? 0,
                'stats' => $playerStats, // lo observado de este jugador en el torneo
                'preflop_stats' => $this->hudFor($playerRows->get($p['id']), $playerStats),
                'postflop_stats' => $postflopStats,
                'folded' => $p['folded'],
                'all_in' => $p['all_in'],
                'is_bot' => $p['is_bot'],
            ];
        }

        return [
            'hand_id' => $hand->id,
            'hand_number' => $hand->hand_number,
            'street' => $meta['street'],
            'big_blind' => (int) ($meta['bb'] ?? 100),
            'pot' => (int) $meta['pot'],
            'current_bet' => $this->maxBet($players),
            'to_call' => max(0, $this->maxBet($players) - $me['bet']),
            // Equity mínima para que igualar sea rentable (pot odds). Para calcular su equity real un bot puede
            // usar App\Services\Ev\EquityCalculator (equityVsRandom / equityVsRanges) y EvCalculator.
            'required_equity' => app(\App\Services\Ev\EvCalculator::class)->requiredEquity(
                (int) $meta['pot'], min(max(0, $this->maxBet($players) - $me['bet']), $me['stack'])
            ),
            'board' => $hand->community_cards ?? [],
            'me' => [
                'id' => $me['id'],
                'bot_id' => \App\Models\TournamentPlayer::whereKey($me['id'])->value('bot_id'),
                'position' => $this->positionLabel($n, ($myIdx - $btn + $n) % $n),
                'stack' => $me['stack'],
                'bet' => $me['bet'],
                'total_in_hand' => $me['total'] ?? 0,
                'hole_cards' => $me['hole_cards'],
                'flags' => $meta['bot_flags'][$me['id']] ?? [], // intenciones (check-raise, float…) de esta mano
            ],
            'players' => $table,
            'history' => $meta['history'] ?? [],
            'legal' => $this->legalActions($players, $meta, $me),
        ];
    }

    // ---------------------------------------------------------------- core

    private function apply(TournamentHand $hand, int $playerId, string $action, int $amount = 0, bool $withState = true, ?array $decision = null): array
    {
        $players = $hand->players_in_hand;
        $meta = $hand->actions;
        $idx = $this->indexOf($players, $playerId);
        $p = &$players[$idx];

        $legal = $this->legalActions($players, $meta, $p);
        $betBefore = $p['bet'];
        $facing = $this->maxBet($players) > 0;

        // Situación en la que actúa (para el análisis posterior)
        $potBefore = (int) $meta['pot'];
        $stackBefore = (int) $p['stack'];
        $toCallBefore = max(0, $this->maxBet($players) - $p['bet']);
        $activeBefore = count(array_filter($players, fn($x) => !$x['folded']));

        if ($action === 'call' && $legal['check']) {
            $action = 'check'; // nada que igualar
        }

        switch ($action) {
            case 'fold':
                if (!$legal['fold']) {
                    throw new \RuntimeException('No puedes foldear cuando puedes pasar sin apostar');
                }
                $p['folded'] = true;
                $p['folded_street'] = $meta['street'];
                break;
            case 'check':
                if (!$legal['check']) {
                    throw new \RuntimeException('No puedes hacer check: hay una apuesta que igualar');
                }
                break;
            case 'call':
                if ($legal['call'] === null) {
                    throw new \RuntimeException('No hay nada que igualar');
                }
                $this->putChips($p, $meta, $legal['call']);
                break;
            case 'raise':
                if ($legal['raise'] === null) {
                    throw new \RuntimeException('No puedes subir: la acción no se ha reabierto');
                }
                $target = min((int) $amount, $legal['raise']['max']);
                if ($target < $legal['raise']['min']) {
                    throw new \RuntimeException('La subida mínima es ' . $legal['raise']['min']);
                }
                $this->raiseTo($players, $meta, $p, $target);
                break;
            case 'allin':
                if ($legal['allin'] === null) {
                    throw new \RuntimeException('No puedes subir: la acción no se ha reabierto');
                }
                $this->raiseTo($players, $meta, $p, $p['stack'] + $p['bet']);
                break;
            default:
                throw new \RuntimeException('Acción no soportada: ' . $action);
        }

        $p['bet_visible'] = ($p['bet_visible'] ?? false) || $p['bet'] > $betBefore;
        $p['acted'] = true;
        $verb = $facing ? 'raise a ' : 'bet ';
        $text = match ($action) {
            'fold' => 'hizo fold',
            'check' => 'hizo check',
            'call' => 'hizo call ' . $this->bb($p['bet'] - $betBefore, $meta),
            'raise' => 'hizo ' . $verb . $this->bb($p['bet'], $meta),
            'allin' => 'hizo all-in ' . ($p['bet'] > $legal['maxBet'] ? 'a ' : 'por ') . $this->bb($p['bet'], $meta),
        };
        $this->log($meta, $p['name'] . ' ' . $text, 'action');

        $record = [
            'player_id' => $p['id'],
            'name' => $p['name'],
            'street' => $meta['street'],
            'action' => $action,
            'amount' => $action === 'call' ? $p['bet'] - $betBefore : $p['bet'],
            'pot_before' => $potBefore,
            'stack_before' => $stackBefore,
            'to_call' => $toCallBefore,
            'text' => $text,
        ];
        $meta['history'][] = $record;
        $meta['last_action'] = $record;

        // Historial estructurado (permanente)
        $this->history()->action(
            $hand, $meta, $meta['street'], $p['id'], $action,
            $p['bet'] - $betBefore, $p['bet'], $meta['pot'], $p['stack'], null,
            ['pot_before' => $potBefore, 'stack_before' => $stackBefore, 'to_call' => $toCallBefore,
             'players_active' => $activeBefore, 'decision' => $decision]
        );
        unset($p);

        $this->advance($hand, $players, $meta, $idx);

        return $withState ? $this->state($hand->fresh()) : [];
    }

    private function advance(TournamentHand $hand, array $players, array $meta, int $fromIdx): void
    {
        $alive = array_values(array_filter($players, fn($p) => !$p['folded']));
        $max = $this->maxBet($players);
        $pending = array_filter($alive, fn($p) => !$p['all_in'] && (!$p['acted'] || $p['bet'] < $max));

        if (count($alive) === 1) {
            $this->finish($hand, $players, $meta, $alive[0]['id']);
            return;
        }
        if (empty($pending)) {
            $canAct = array_filter($alive, fn($p) => !$p['all_in']);
            if ($meta['street'] === 'river' || count($canAct) <= 1) {
                $this->showdown($hand, $players, $meta);
                return;
            }
            $this->nextStreet($hand, $players, $meta);
            return;
        }

        $n = count($players);
        for ($i = 1; $i <= $n; $i++) {
            $cand = $players[($fromIdx + $i) % $n];
            if (!$cand['folded'] && !$cand['all_in'] && (!$cand['acted'] || $cand['bet'] < $max)) {
                $meta['turn'] = $cand['id'];
                break;
            }
        }

        $hand->players_in_hand = $players;
        $hand->actions = $meta;
        $hand->save();
    }

    private function nextStreet(TournamentHand $hand, array $players, array $meta): void
    {
        $next = ['preflop' => 'flop', 'flop' => 'turn', 'turn' => 'river'][$meta['street']];
        $count = ['flop' => 3, 'turn' => 4, 'river' => 5][$next];

        $meta['street'] = $next;
        foreach ($meta['bot_flags'] ?? [] as $pid => $flags) {
            unset($meta['bot_flags'][$pid]['check_raise']);
        }
        $meta['last_raise'] = $meta['bb'];
        $meta['raises'] = 0;
        $hand->community_cards = array_slice($meta['board_full'], 0, $count);
        $dealt = array_slice($hand->community_cards, -($next === 'flop' ? 3 : 1));
        $this->log($meta, ucfirst($next), 'street', ['cards' => $dealt]);
        $this->history()->action($hand, $meta, $next, null, 'deal', 0, null, $meta['pot'], null, $dealt);

        foreach ($players as &$p) {
            $p['bet'] = 0;
            $p['bet_visible'] = false;
            $p['acted'] = false;
            $p['locked'] = false;
        }
        unset($p);

        // Primero en actuar: el primer jugador activo a la izquierda del botón
        $n = count($players);
        $btn = (int) ($meta['btn'] ?? 0);
        for ($i = 1; $i <= $n; $i++) {
            $cand = $players[($btn + $i) % $n];
            if (!$cand['folded'] && !$cand['all_in']) {
                $meta['turn'] = $cand['id'];
                break;
            }
        }

        $hand->players_in_hand = $players;
        $hand->actions = $meta;
        $hand->save();
    }

    private function showdown(TournamentHand $hand, array $players, array $meta): void
    {
        $board = $meta['board_full'];

        // Historial: calles que aún no se habían repartido (all-in antes del river)
        $visible = count($hand->community_cards ?? []);
        foreach ([['flop', 0, 3], ['turn', 3, 1], ['river', 4, 1]] as [$street, $from, $len]) {
            if ($visible <= $from) {
                $this->history()->action($hand, $meta, $street, null, 'deal', 0, null, $meta['pot'], null, array_slice($board, $from, $len));
            }
        }

        $hand->community_cards = $board;
        $meta['street'] = 'showdown';

        $scores = [];
        foreach ($players as $p) {
            if (!$p['folded']) {
                $scores[$p['id']] = $this->score(array_merge($p['hole_cards'], $board));
            }
        }

        $awards = [];
        $potResults = [];
        foreach ($this->buildPots($players) as $i => $pot) {
            $best = null;
            $winners = [];
            foreach ($pot['eligible'] as $id) {
                $cmp = $best === null ? 1 : $this->compare($scores[$id], $best);
                if ($cmp > 0) {
                    $best = $scores[$id];
                    $winners = [$id];
                } elseif ($cmp === 0) {
                    $winners[] = $id;
                }
            }
            $share = intdiv($pot['amount'], count($winners));
            $rest = $pot['amount'] - $share * count($winners);
            foreach ($winners as $k => $id) {
                $awards[$id] = ($awards[$id] ?? 0) + $share + ($k === 0 ? $rest : 0);
            }
            $potResults[] = [
                'label' => $i === 0 ? 'Bote principal' : 'Bote lateral ' . $i,
                'amount' => $pot['amount'],
                'winners' => $winners,
            ];
        }

        // Representación en el log: board final y cada jugador enseña sus cartas y su mano
        $this->log($meta, 'Showdown', 'street', ['cards' => $board]);

        $n = count($players);
        $btn = (int) ($meta['btn'] ?? 0) % max(1, $n);
        for ($k = 1; $k <= $n; $k++) {
            $p = $players[($btn + $k) % $n];
            if ($p['folded']) {
                continue;
            }
            $won = $awards[$p['id']] ?? 0;
            $this->log($meta, $p['name'] . ' muestra', 'showdown', [
                'cards' => $p['hole_cards'],
                'hand' => $this->handName($scores[$p['id']]),
                'winner' => $won > 0,
                'won' => $won > 0 ? $this->bb($won, $meta) : null,
            ]);
        }

        foreach ($potResults as $res) {
            $names = array_map(fn($id) => $players[$this->indexOf($players, $id)]['name'], $res['winners']);
            $this->log(
                $meta,
                "{$res['label']} (" . $this->bb($res['amount'], $meta) . '): ' . implode(', ', $names)
                    . ' gana con ' . $this->handName($scores[$res['winners'][0]]),
                'win'
            );
        }

        $handNames = [];
        foreach ($scores as $id => $score) {
            $handNames[$id] = $this->handName($score);
        }
        $potsOut = array_map(fn($res) => [
            'label' => $res['label'],
            'amount' => $res['amount'],
            'winners' => $res['winners'],
            'hand_name' => $handNames[$res['winners'][0]] ?? null,
        ], $potResults);

        $this->finish($hand, $players, $meta, array_key_first($awards), $awards, $potsOut, $handNames);
    }

    // ---------------------------------------------------- nombres de mano

    private const RANK_PLURAL = [2 => 'doses', 3 => 'treses', 4 => 'cuatros', 5 => 'cincos', 6 => 'seises', 7 => 'sietes',
        8 => 'ochos', 9 => 'nueves', 10 => 'dieces', 11 => 'jotas', 12 => 'damas', 13 => 'reyes', 14 => 'ases'];
    private const RANK_SINGULAR = [2 => 'Dos', 3 => 'Tres', 4 => 'Cuatro', 5 => 'Cinco', 6 => 'Seis', 7 => 'Siete',
        8 => 'Ocho', 9 => 'Nueve', 10 => 'Diez', 11 => 'Jota', 12 => 'Dama', 13 => 'Rey', 14 => 'As'];
    private const RANK_HIGH = [5 => 'al 5', 6 => 'al 6', 7 => 'al 7', 8 => 'al 8', 9 => 'al 9', 10 => 'al 10',
        11 => 'a la Jota', 12 => 'a la Dama', 13 => 'al Rey', 14 => 'al As'];

    /** Nombre en español de una mano a partir de su puntuación [categoría, desempates…]. */
    private function handName(array $s): string
    {
        $pl = fn(int $v) => self::RANK_PLURAL[$v] ?? (string) $v;

        return match ($s[0]) {
            0 => 'Carta alta ' . (self::RANK_SINGULAR[$s[1]] ?? $s[1]),
            1 => 'Pareja de ' . $pl($s[1]),
            2 => 'Doble pareja de ' . $pl($s[1]) . ' y ' . $pl($s[2]),
            3 => 'Trío de ' . $pl($s[1]),
            4 => 'Escalera ' . (self::RANK_HIGH[$s[1]] ?? $s[1]),
            5 => 'Color ' . (self::RANK_HIGH[$s[1]] ?? $s[1]),
            6 => 'Full de ' . $pl($s[1]) . ' y ' . $pl($s[2]),
            7 => 'Póker de ' . $pl($s[1]),
            8 => $s[1] === 14 ? 'Escalera real' : 'Escalera de color ' . (self::RANK_HIGH[$s[1]] ?? $s[1]),
            default => '',
        };
    }
    /**
     * Divide el bote en principal + laterales según lo aportado por cada jugador.
     * Cada bote: ['amount' => int, 'eligible' => [ids no foldeados que llegan a ese nivel]].
     */
    private function buildPots(array $players): array
    {
        $levels = [];
        foreach ($players as $p) {
            if (!$p['folded']) {
                $levels[] = (int) ($p['total'] ?? 0);
            }
        }
        $levels = array_values(array_unique($levels));
        sort($levels);

        $pots = [];
        $prev = 0;
        foreach ($levels as $level) {
            if ($level <= $prev) {
                continue;
            }
            $amount = 0;
            foreach ($players as $p) {
                $amount += max(0, min((int) ($p['total'] ?? 0), $level) - $prev);
            }
            $eligible = [];
            foreach ($players as $p) {
                if (!$p['folded'] && (int) ($p['total'] ?? 0) >= $level) {
                    $eligible[] = $p['id'];
                }
            }
            $pots[] = ['amount' => $amount, 'eligible' => $eligible];
            $prev = $level;
        }

        // Lo aportado por foldeados por encima del mayor nivel va al último bote
        $excess = 0;
        foreach ($players as $p) {
            $excess += max(0, (int) ($p['total'] ?? 0) - $prev);
        }
        if ($excess > 0 && $pots) {
            $pots[count($pots) - 1]['amount'] += $excess;
        }

        return $pots;
    }

    private function finish(TournamentHand $hand, array $players, array $meta, int $winnerId, ?array $awards = null, ?array $pots = null, array $handNames = []): void
    {
        $awards ??= [$winnerId => $meta['pot']];
        foreach ($awards as $id => $amount) {
            $players[$this->indexOf($players, $id)]['stack'] += $amount;
        }
        if (count($awards) === 1 && ($meta['street'] ?? '') !== 'showdown') {
            $this->log($meta, $players[$this->indexOf($players, $winnerId)]['name'] . ' gana ' . $this->bb(array_sum($awards), $meta), 'win');
        }

        $w = $this->indexOf($players, $winnerId);
        $meta['winner_id'] = $winnerId;
        $meta['awards'] = $awards;

        // Estadísticas observadas del torneo (se calculan una sola vez, al cerrar la mano)
        app(\App\Services\Stats\TournamentStatsService::class)
            ->recordHand($players, $meta, $awards, $hand->community_cards ?? []);

        // Historial estructurado: resultado de cada jugador, botes y board completo
        $pots ??= [['label' => 'Bote', 'amount' => $meta['pot'], 'winners' => array_keys($awards), 'hand_name' => null]];
        $this->history()->finish($hand, $players, $awards, $pots, $meta, $handNames);

        // Análisis de EV: equity contra las cartas reales, EV por decisión y all-in ajustado.
        // Se guarda en el historial y, para el héroe, se añade al log de la mesa.
        try {
            foreach (app(\App\Services\Ev\DecisionEvAnalyzer::class)->analyze($hand) as $entry) {
                $meta['log'][] = $entry;
            }
        } catch (\Throwable $e) {
            report($e); // un fallo del análisis nunca debe romper la partida
        }
        $meta['turn'] = null;
        $meta['pot_won'] = $meta['pot'];
        $meta['pot'] = 0;

        $hero = collect($players)->firstWhere('is_bot', false);
        $heroWon = $hero ? ($awards[$hero['id']] ?? 0) : 0;

        $hand->players_in_hand = $players;
        $hand->actions = $meta;
        $hand->status = 'completed';
        $hand->winning_seat = $w + 1;
        $hand->result = $heroWon <= 0 ? 'lose' : (count($awards) > 1 ? 'split' : 'win');
        $hand->stacks_after = array_column($players, 'stack', 'id');
        $hand->save();

        // Persistir stacks en el torneo
        foreach ($players as $p) {
            \App\Models\TournamentPlayer::whereKey($p['id'])->update(['current_stack' => $p['stack']]);
        }
    }

    // ------------------------------------------------------------- inicio

    public function ensureInitialized(TournamentHand $hand): void
    {
        if (!empty($hand->players_in_hand) && array_key_exists('turn', $hand->actions ?? [])) {
            return;
        }

        $pending = $hand->actions['pending_log'] ?? [];

        if ($hand->table_id) {
            $tp = $hand->table->players()->where('tournament_players.current_stack', '>', 0)
                ->orderBy('table_players.seat_number')->take(self::TABLE_SIZE)->get();
        } else {
            // Manos antiguas sin mesa: primeros jugadores del torneo
            $tp = $hand->tournament->players()->where('current_stack', '>', 0)->orderBy('id')->take(self::TABLE_SIZE)->get();
        }
        if ($tp->count() < 2) {
            throw new \RuntimeException('Se necesitan al menos 2 jugadores');
        }

        $deck = [];
        foreach (self::RANKS as $r) {
            foreach (self::SUITS as $s) {
                $deck[] = $r . $s;
            }
        }
        shuffle($deck);

        $players = [];
        foreach ($tp as $i => $row) {
            $players[] = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'stack' => (int) $row->current_stack,
                'bet' => 0,
                'bet_visible' => false,
                'total' => 0,
                'folded' => false,
                'all_in' => false,
                'acted' => false,
                'is_bot' => (bool) $row->is_bot,
                'seat' => (int) ($row->pivot->seat_number ?? ($i + 1)),
                'hole_cards' => [array_shift($deck), array_shift($deck)],
            ];
        }

        $n = count($players);
        $blinds = $hand->blind_level_config ?? [];
        $sb = (int) ($blinds['small_blind'] ?? 100);
        $bb = (int) ($blinds['big_blind'] ?? 200);

        $btn = (int) ($hand->button_seat ?? 0) % $n;
        $meta = ['pot' => 0, 'turn' => null, 'street' => 'preflop', 'log' => [], 'winner_id' => null, 'bb' => $bb, 'btn' => $btn, 'last_raise' => $bb, 'raises' => 0,
            'board_full' => array_splice($deck, 0, 5)];

        // Historial: quién se sienta, con qué stack y qué cartas (antes de poner ciegas)
        $positions = [];
        foreach ($players as $i => $_) {
            $positions[$i] = $this->positionLabel($n, ($i - $btn + $n) % $n);
        }
        $this->history()->seat(
            $hand, $players, $positions,
            app(\App\Services\Stats\TournamentStatsService::class)->summaries(array_column($players, 'id'))
        );

        $sbIdx = $n === 2 ? $btn : ($btn + 1) % $n;
        $bbIdx = $n === 2 ? ($btn + 1) % $n : ($btn + 2) % $n;
        $ante = max(0, (int) ($blinds['ante'] ?? 0));
        if ($ante > 0) {
            foreach ($players as &$player) {
                $antePaid = min($ante, $player['stack']);
                $this->postAnte($player, $meta, $ante);
                $this->history()->action(
                    $hand,
                    $meta,
                    'preflop',
                    $player['id'],
                    'post_ante',
                    $antePaid,
                    $antePaid,
                    $meta['pot'],
                    $player['stack'],
                );
            }
            unset($player);
        }

        $this->putChips($players[$sbIdx], $meta, min($sb, $players[$sbIdx]['stack']));
        $this->history()->action($hand, $meta, 'preflop', $players[$sbIdx]['id'], 'post_sb', $players[$sbIdx]['bet'], $players[$sbIdx]['bet'], $meta['pot'], $players[$sbIdx]['stack']);
        $this->putChips($players[$bbIdx], $meta, min($bb, $players[$bbIdx]['stack']));
        $this->history()->action($hand, $meta, 'preflop', $players[$bbIdx]['id'], 'post_bb', $players[$bbIdx]['bet'], $players[$bbIdx]['bet'], $meta['pot'], $players[$bbIdx]['stack']);
        foreach ($pending as $e) {
            $meta['log'][] = $e; // eventos del torneo (eliminaciones, cambios de mesa)
        }
        $this->log($meta, '🎰 Mano #' . $hand->hand_number . ' comenzó', 'start');
        $heroCards = collect($players)->firstWhere('is_bot', false)['hole_cards'] ?? [];
        $this->log($meta, 'Tus cartas', 'deal', ['cards' => $heroCards]);
        $this->log(
            $meta,
            ($ante > 0 ? "Ante {$ante}. " : '') . 'Ciegas ' . $sb . '/' . $bb
                . ' (' . $players[$sbIdx]['name'] . ' SB, ' . $players[$bbIdx]['name'] . ' BB)',
            'info',
        );

        $first = $n === 2 ? $sbIdx : ($btn + 3) % $n;
        $meta['turn'] = null;
        for ($offset = 0; $offset < $n; $offset++) {
            $candidate = ($first + $offset) % $n;
            if (!$players[$candidate]['folded'] && !$players[$candidate]['all_in']) {
                $meta['turn'] = $players[$candidate]['id'];
                break;
            }
        }

        $hand->players_in_hand = $players;
        $hand->actions = $meta;
        $hand->hero_cards = collect($players)->firstWhere('is_bot', false)['hole_cards'] ?? [];
        $hand->community_cards = [];
        $hand->save();
        if ($meta['turn'] === null) {
            $this->showdown($hand, $players, $meta);
        }
    }

    // ------------------------------------------------------------ helpers

    /**
     * Acciones legales de un jugador ahora mismo.
     * - check: sin apuesta que igualar
     * - call: cantidad a poner (acotada a su stack)
     * - raise: [min, max] "subir a" (null si no puede subir)
     * - allin: null si el all-in sería una subida y tiene la acción cerrada
     */
    private function legalActions(array $players, array $meta, array $p): array
    {
        $max = $this->maxBet($players);
        $toCall = max(0, $max - $p['bet']);
        // El límite del motor es el objetivo total, incluida la apuesta ya puesta.
        $total = $p['stack'] + $p['bet'];
        $locked = (bool) ($p['locked'] ?? false);
        $opponentMax = 0;
        foreach ($players as $opponent) {
            if ($opponent['id'] === $p['id'] || ($opponent['folded'] ?? false) || ($opponent['all_in'] ?? false)) {
                continue;
            }
            $opponentMax = max($opponentMax, (int) $opponent['stack'] + (int) $opponent['bet']);
        }
        $effectiveMax = min($total, $opponentMax);

        $raise = null;
        if (!$locked && $effectiveMax > $max) {
            $raise = ['min' => min($this->minRaiseTo($players, $meta), $effectiveMax), 'max' => $effectiveMax];
        }
        $playerIndex = $this->indexOf($players, $p['id']);
        $buttonIndex = (int) ($meta['btn'] ?? 0) % max(1, count($players));
        $bigBlindIndex = count($players) === 2
            ? ($buttonIndex + 1) % count($players)
            : ($buttonIndex + 2) % count($players);

        return [
            'fold' => $toCall > 0 || ($meta['street'] === 'preflop' && $playerIndex !== $bigBlindIndex),
            'check' => $toCall === 0,
            'call' => $toCall > 0 ? min($toCall, $p['stack']) : null,
            'raise' => $raise,
            'allin' => ($locked && $total > $max) ? null : $p['stack'],
            'maxBet' => $max,
            'is_bet' => $max === 0,
        ];
    }

    /**
     * Sube la apuesta de $p hasta $target (total).
     * Una subida completa (>= la última) reabre la acción a todos; una subida corta
     * (all-in menor) obliga a igualar pero NO deja volver a subir a quien ya había actuado.
     */
    private function raiseTo(array &$players, array &$meta, array &$p, int $target): void
    {
        $oldMax = $this->maxBet($players);
        $this->putChips($p, $meta, $target - $p['bet']);

        if ($p['bet'] <= $oldMax) {
            return;
        }

        $size = $p['bet'] - $oldMax;
        $fullRaise = $size >= max((int) ($meta['last_raise'] ?? 0), (int) ($meta['bb'] ?? 100));

        if ($fullRaise) {
            $meta['last_raise'] = $size;
            $meta['raises'] = ($meta['raises'] ?? 0) + 1;
        }

        foreach ($players as &$o) {
            if ($o['id'] === $p['id']) {
                continue;
            }
            if ($fullRaise) {
                $o['acted'] = false;
                $o['locked'] = false;
            } elseif ($o['acted']) {
                $o['locked'] = true;
            }
        }
        unset($o);
    }

    private function minRaiseTo(array $players, array $meta): int
    {
        return $this->maxBet($players) + max((int) ($meta['last_raise'] ?? 0), (int) ($meta['bb'] ?? 100));
    }

    private function putChips(array &$p, array &$meta, int $amount): void
    {
        $amount = max(0, min($amount, $p['stack']));
        $p['stack'] -= $amount;
        $p['bet'] += $amount;
        $p['total'] = ($p['total'] ?? 0) + $amount;
        $meta['pot'] += $amount;
        if ($p['stack'] === 0) {
            $p['all_in'] = true;
        }
    }

    private function postAnte(array &$p, array &$meta, int $amount): void
    {
        $amount = max(0, min($amount, $p['stack']));
        $p['stack'] -= $amount;
        $p['total'] = ($p['total'] ?? 0) + $amount;
        $meta['pot'] += $amount;
        if ($p['stack'] === 0) {
            $p['all_in'] = true;
        }
    }

    private function maxBet(array $players): int
    {
        return (int) max(array_column($players, 'bet'));
    }

    private function indexOf(array $players, int $id): int
    {
        foreach ($players as $i => $p) {
            if ($p['id'] === $id) {
                return $i;
            }
        }
        throw new \RuntimeException('Jugador no encontrado');
    }

    private function find(array $players, ?int $id): array
    {
        if ($id === null) {
            throw new \RuntimeException('No hay turno activo');
        }
        return $players[$this->indexOf($players, $id)];
    }

    private function assertInProgress(TournamentHand $hand): void
    {
        if ($hand->status === 'completed') {
            throw new \RuntimeException('La mano ya terminó');
        }
    }

    // ----------------------------------------------------------- evaluador

    /** Mejor mano de 5 entre 7 cartas → [categoría, kickers...]. */
    private function score(array $cards): array
    {
        $best = [0];
        $n = count($cards);
        for ($a = 0; $a < $n - 4; $a++) for ($b = $a + 1; $b < $n - 3; $b++)
        for ($c = $b + 1; $c < $n - 2; $c++) for ($d = $c + 1; $d < $n - 1; $d++)
        for ($e = $d + 1; $e < $n; $e++) {
            $s = $this->score5([$cards[$a], $cards[$b], $cards[$c], $cards[$d], $cards[$e]]);
            if ($this->compare($s, $best) > 0) {
                $best = $s;
            }
        }
        return $best;
    }

    private function score5(array $cards): array
    {
        $vals = array_map(fn($c) => array_search(mb_substr($c, 0, 1), self::RANKS) + 2, $cards);
        $suits = array_map(fn($c) => mb_substr($c, 1, 1), $cards);
        rsort($vals);

        $flush = count(array_unique($suits)) === 1;
        $uniq = array_values(array_unique($vals));
        $straightHigh = 0;
        if (count($uniq) === 5) {
            if ($vals[0] - $vals[4] === 4) {
                $straightHigh = $vals[0];
            } elseif ($vals === [14, 5, 4, 3, 2]) {
                $straightHigh = 5;
            }
        }

        $counts = array_count_values($vals);
        uksort($counts, fn($x, $y) => $counts[$y] <=> $counts[$x] ?: $y <=> $x);
        $groups = array_values($counts);
        $order = array_keys($counts);

        if ($straightHigh && $flush) return [8, $straightHigh];
        if ($groups[0] === 4) return [7, ...$order];
        if ($groups[0] === 3 && $groups[1] === 2) return [6, ...$order];
        if ($flush) return [5, ...$vals];
        if ($straightHigh) return [4, $straightHigh];
        if ($groups[0] === 3) return [3, ...$order];
        if ($groups[0] === 2 && $groups[1] === 2) return [2, ...$order];
        if ($groups[0] === 2) return [1, ...$order];
        return [0, ...$vals];
    }

    private function compare(array $a, array $b): int
    {
        $len = max(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $d = ($a[$i] ?? 0) <=> ($b[$i] ?? 0);
            if ($d !== 0) {
                return $d;
            }
        }
        return 0;
    }
}
