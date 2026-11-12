<?php

namespace App\Services\History;

use App\Models\HandAction;
use App\Models\HandPlayer;
use App\Models\Tournament;
use App\Models\TournamentEvent;
use App\Models\TournamentHand;

/**
 * Consulta del historial estructurado de un torneo (todas las mesas, ronda a ronda).
 */
class TournamentHistoryService
{
    public const ROUNDS_PER_PAGE = 8;

    /**
     * Vista general paginada por rondas.
     *
     * @param array{table?:?int,hero?:bool,player?:?int} $filters
     */
    public function overview(Tournament $t, array $filters, int $page): array
    {
        $base = $this->baseQuery($t, $filters);

        $rounds = (clone $base)->reorder()->distinct()->orderByDesc('hand_number')->pluck('hand_number')->values();
        $pages = max(1, (int) ceil($rounds->count() / self::ROUNDS_PER_PAGE));
        $page = min(max(1, $page), $pages);
        $pageRounds = $rounds->slice(($page - 1) * self::ROUNDS_PER_PAGE, self::ROUNDS_PER_PAGE)->values();

        $hands = (clone $base)->whereIn('hand_number', $pageRounds)
            ->with(['table', 'handPlayers.player'])
            ->orderBy('table_id')->orderBy('id')->get()
            ->groupBy('hand_number');

        $eventRounds = $pageRounds->all();
        if ($page === $pages) {
            $eventRounds[] = 0; // ronda 0 = inicio del torneo
        }
        $events = TournamentEvent::where('tournament_id', $t->id)->whereIn('round', $eventRounds)
            ->orderBy('id')->get()->groupBy('round');

        $out = [];
        foreach ($pageRounds as $round) {
            $list = $hands[$round] ?? collect();
            $first = $list->first();
            $out[] = [
                'round' => $round,
                'level' => $first?->level,
                'small_blind' => $first?->blind_level_config['small_blind'] ?? null,
                'big_blind' => $first?->blind_level_config['big_blind'] ?? null,
                'hands' => $list->map(fn($h) => $this->summary($h))->values()->all(),
                'events' => ($events[$round] ?? collect())->values()->all(),
            ];
        }

        return [
            'rounds' => $out,
            'page' => $page,
            'pages' => $pages,
            'start_events' => $page === $pages ? ($events[0] ?? collect())->values()->all() : [],
            'total_rounds' => $rounds->count(),
        ];
    }

    private function baseQuery(Tournament $t, array $f)
    {
        $q = TournamentHand::where('tournament_id', $t->id)->where('status', 'completed');

        if (!empty($f['table'])) {
            $q->where('table_id', $f['table']);
        }
        if (!empty($f['hero'])) {
            $q->whereHas('handPlayers.player', fn($p) => $p->where('is_bot', false));
        }
        if (!empty($f['player'])) {
            $q->whereHas('handPlayers', fn($p) => $p->where('tournament_player_id', $f['player']));
        }

        return $q;
    }

    /** Resumen de una mano para la lista. */
    private function summary(TournamentHand $h): array
    {
        $players = $h->handPlayers;
        $bb = max(1, (int) ($h->blind_level_config['big_blind'] ?? 1));
        $base = [
            'id' => $h->id,
            'round' => $h->hand_number,
            'table_number' => $h->table?->table_number,
            'bb' => $bb,
            'structured' => $players->isNotEmpty(),
        ];

        if ($players->isEmpty()) {
            return $base + ['players' => 0, 'winner' => null, 'winner_won' => 0, 'winner_hand' => null,
                'pot' => 0, 'showdown' => false, 'hero' => null];
        }

        $winner = $players->sortByDesc('won')->first();
        $hero = $players->first(fn($hp) => !$hp->player->is_bot);

        return $base + [
            'players' => $players->count(),
            'winner' => $winner->player->name,
            'winner_won' => (int) $winner->won,
            'winner_hand' => $winner->hand_name,
            'pot' => (int) $players->sum('won'),
            'showdown' => $players->contains(fn($hp) => $hp->went_to_showdown),
            'hero' => $hero ? [
                'position' => $hero->position,
                'cards' => $hero->hole_cards,
                'net' => (int) $hero->stack_end - (int) $hero->stack_start,
                'folded_street' => $hero->folded_street,
            ] : null,
        ];
    }

    /** Detalle completo de una mano terminada (jugadores, cartas, acciones y botes). */
    public function detail(TournamentHand $hand): array
    {
        $hand->load(['table', 'handPlayers.player', 'handActions']);
        $names = $hand->handPlayers->mapWithKeys(fn(HandPlayer $hp) => [$hp->tournament_player_id => $hp->player->name]);

        return [
            'hand' => [
                'id' => $hand->id,
                'round' => $hand->hand_number,
                'table_number' => $hand->table?->table_number,
                'level' => $hand->level,
                'small_blind' => $hand->blind_level_config['small_blind'] ?? null,
                'big_blind' => $hand->blind_level_config['big_blind'] ?? 1,
                'button_seat' => $hand->button_seat,
                'board' => $hand->board_full ?? $hand->community_cards ?? [],
                'pots' => $hand->pots ?? [],
            ],
            'players' => $hand->handPlayers->map(fn(HandPlayer $hp) => [
                'id' => $hp->tournament_player_id,
                'seat' => $hp->seat,
                'position' => $hp->position,
                'name' => $hp->player->name,
                'is_hero' => !$hp->player->is_bot,
                'stack_start' => $hp->stack_start,
                'stack_end' => $hp->stack_end,
                'won' => $hp->won,
                'cards' => $hp->hole_cards,
                'folded_street' => $hp->folded_street,
                'went_to_showdown' => $hp->went_to_showdown,
                'hand_name' => $hp->hand_name,
                'stats_before' => $hp->stats_before,
                'ev_net' => $hp->ev_net,
                'ev_luck' => $hp->ev_luck,
                'allin_equity' => $hp->allin_equity,
            ])->values()->all(),
            'actions' => $hand->handActions->map(fn(HandAction $a) => [
                'seq' => $a->sequence,
                'street' => $a->street,
                'player' => $a->tournament_player_id ? ($names[$a->tournament_player_id] ?? '?') : null,
                'type' => $a->type,
                'amount' => $a->amount,
                'to_amount' => $a->to_amount,
                'to_call' => $a->to_call,
                'pot_before' => $a->pot_before,
                'pot_after' => $a->pot_after,
                'stack_before' => $a->stack_before,
                'stack_after' => $a->stack_after,
                'players_active' => $a->players_active,
                'cards' => $a->cards,
                'reason' => $a->decision['reason'] ?? null,
                'equity' => $a->equity,
                'required_equity' => $a->required_equity,
                'ev_taken' => $a->ev_taken,
                'ev_alternative' => $a->ev_alternative,
                'ev_diff' => $a->ev_diff,
                'ev_best' => $a->ev_best,
                'ev_verdict' => $a->ev_verdict,
            ])->values()->all(),
        ];
    }

    /**
     * Exportación completa del torneo (para análisis y futura reproducción).
     * Incluye todo lo grabado: asientos iniciales, eventos, manos, acciones y el contexto de cada decisión de bot.
     */
    public function export(Tournament $t): array
    {
        $hands = [];
        TournamentHand::where('tournament_id', $t->id)->where('status', 'completed')
            ->with(['table', 'handPlayers', 'handActions'])
            ->orderBy('hand_number')->orderBy('table_id')
            ->chunk(100, function ($chunk) use (&$hands) {
                foreach ($chunk as $h) {
                    $hands[] = [
                        'id' => $h->id,
                        'round' => $h->hand_number,
                        'table_id' => $h->table_id,
                        'table_number' => $h->table?->table_number,
                        'level' => $h->level,
                        'blinds' => $h->blind_level_config,
                        'button_seat' => $h->button_seat,
                        'board_full' => $h->board_full,
                        'pots' => $h->pots,
                        'players' => $h->handPlayers->map(fn($hp) => $hp->only([
                            'tournament_player_id', 'seat', 'position', 'stack_start', 'stack_end', 'hole_cards',
                            'stats_before', 'folded_street', 'went_to_showdown', 'hand_name', 'won', 'ev_net', 'ev_luck', 'allin_equity',
                        ]))->values()->all(),
                        'actions' => $h->handActions->map(fn($a) => $a->only([
                            'sequence', 'street', 'tournament_player_id', 'type', 'amount', 'to_amount', 'pot_before',
                            'pot_after', 'stack_before', 'stack_after', 'to_call', 'players_active', 'cards', 'decision',
                            'equity', 'required_equity', 'ev_taken', 'ev_alternative', 'ev_diff', 'ev_best', 'ev_verdict', 'ev_model',
                        ]))->values()->all(),
                    ];
                }
            });

        return [
            'format_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'tournament' => $t->only(['id', 'title', 'status', 'config', 'started_at', 'ended_at']),
            'players' => $t->players()->get(['id', 'name', 'bot_id', 'is_bot', 'starting_stack', 'current_stack', 'is_eliminated', 'placement'])->all(),
            'tables' => $t->tables()->get(['id', 'table_number', 'is_active'])->all(),
            'events' => TournamentEvent::where('tournament_id', $t->id)->orderBy('id')->get()->map(fn($e) => $e->only([
                'round', 'table_id', 'tournament_player_id', 'type', 'message', 'payload', 'created_at',
            ]))->all(),
            'hands' => $hands,
        ];
    }
}
