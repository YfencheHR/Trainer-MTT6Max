<?php

namespace App\Services\Tournament;

use App\Models\Table;
use App\Models\Tournament;
use App\Models\TournamentHand;
use App\Models\TournamentPlayer;
use App\Services\HandPlayService;
use Illuminate\Support\Facades\DB;

/**
 * Rondas del torneo. En cada ronda TODAS las mesas juegan una mano:
 *  - la mesa del héroe la juega el usuario;
 *  - las demás se resuelven en el servidor, una petición por mesa (resolveTable);
 *  - al terminar todas, finishRound procesa eliminaciones, nivela mesas y crea la siguiente mano que se verá.
 */
class TournamentRoundService
{
    public const TABLE_SIZE = 6;

    public function __construct(
        private HandPlayService $hands,
        private TableBalanceService $balance,
        private BlindStructureService $blindStructure,
    ) {
    }

    // ------------------------------------------------------------ ciegas

    /** Ciegas de una ronda según la estructura del torneo (nivel por número de manos). */
    public function blindsForRound(Tournament $t, int $round): array
    {
        $baseBB = (int) data_get($t->config, 'basic_settings.big_blind', 100);
        $speed = (string) data_get($t->config, 'blind_structure.speed', 'tipica');
        $handsPerLevel = data_get(
            $t->config,
            'blind_structure.hands_per_level',
            data_get($t->config, 'blind_structure.levels.0.hands_per_level'),
        );
        $antesByLevel = data_get($t->config, 'blind_structure.antes', []);

        return $this->blindStructure->forRound($baseBB, $speed, $round, $handsPerLevel, $antesByLevel);
    }

    // ------------------------------------------------------------- mesas

    public function heroTable(Tournament $t): ?Table
    {
        $heroId = $t->players()->where('is_bot', false)->value('id');
        if (!$heroId) {
            return null;
        }

        return Table::where('tournament_id', $t->id)
            ->where('is_active', true)
            ->whereHas('players', fn($q) => $q->where('tournament_players.id', $heroId))
            ->first();
    }

    /** Crea la mano de una mesa para una ronda (botón rotado respecto a la mano anterior de la mesa). */
    public function createHand(Table $table, int $round, array $pendingLog = []): TournamentHand
    {
        $t = $table->tournament;
        $prev = TournamentHand::where('table_id', $table->id)->orderByDesc('id')->first();
        $blinds = $this->blindsForRound($t, $round);

        return TournamentHand::create([
            'tournament_id' => $t->id,
            'table_id' => $table->id,
            'hand_number' => $round,
            'level' => $blinds['level'],
            'blind_level_config' => [
                'small_blind' => $blinds['small_blind'],
                'big_blind' => $blinds['big_blind'],
                'ante' => $blinds['ante'],
            ],
            'button_seat' => $prev ? ((int) $prev->button_seat + 1) % self::TABLE_SIZE : 0,
            'hero_seat' => 0,
            'hero_cards' => [],
            'community_cards' => [],
            'stacks_before' => [],
            'stacks_after' => [],
            'players_in_hand' => [],
            'actions' => $pendingLog ? ['pending_log' => $pendingLog] : [],
            'status' => 'in_progress',
            'result' => 'in_progress',
        ]);
    }

    // ----------------------------------------------- mesas secundarias

    /**
     * Resuelve en el servidor la mano de una mesa secundaria para la ronda dada
     * (los bots deciden con el mismo motor que la mesa del héroe). Idempotente.
     */
    public function resolveTable(Tournament $t, Table $table, int $round): array
    {
        $existing = TournamentHand::where('table_id', $table->id)->where('hand_number', $round)->first();

        if ($existing && $existing->status === 'completed') {
            return $this->summary($table, $existing, []);
        }

        $alive = DB::table('table_players')
            ->join('tournament_players', 'tournament_players.id', '=', 'table_players.tournament_player_id')
            ->where('table_players.table_id', $table->id)
            ->where('tournament_players.current_stack', '>', 0)
            ->count();
        if ($alive < 2) {
            return ['table_id' => $table->id, 'table_number' => $table->table_number, 'players' => $alive, 'skipped' => true, 'eliminated' => []];
        }

        $hand = $existing ?? $this->createHand($table, $round);
        $this->hands->playQuiet($hand);
        $hand->refresh();

        $eliminated = $this->processEliminations($t, $table, $hand);

        return $this->summary($table, $hand, $eliminated);
    }

    private function summary(Table $table, TournamentHand $hand, array $eliminated): array
    {
        $winnerId = $hand->actions['winner_id'] ?? null;
        $winner = collect($hand->players_in_hand)->firstWhere('id', $winnerId)['name'] ?? null;

        return [
            'hand_id' => $hand->id,
            'table_id' => $table->id,
            'table_number' => $table->table_number,
            'players' => count($hand->players_in_hand),
            'winner' => $winner,
            'eliminated' => $eliminated,
        ];
    }

    // ------------------------------------------------------ eliminaciones

    /**
     * Marca como eliminados a los jugadores de la mesa que se quedaron sin fichas, asigna
     * su puesto (el que empezó la mano con menos fichas queda peor) y los saca de la mesa.
     * Los eventos quedan pendientes para el log de la siguiente mano del héroe.
     *
     * @return string[] textos de eliminación
     */
    public function processEliminations(Tournament $t, Table $table, TournamentHand $hand): array
    {
        $busted = collect($hand->players_in_hand)
            ->filter(fn($p) => (int) $p['stack'] <= 0)
            ->sortBy(fn($p) => (int) ($p['total'] ?? 0)) // menos fichas iniciales = peor puesto
            ->values();

        if ($busted->isEmpty()) {
            return [];
        }

        $activeIds = TournamentPlayer::whereIn('id', $busted->pluck('id'))
            ->where('is_eliminated', false)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
        $busted = $busted->filter(fn($p) => in_array((int) $p['id'], $activeIds, true))->values();

        if ($busted->isEmpty()) {
            return [];
        }

        // Puesto más bajo disponible: jugadores aún no eliminados (incluye a los de este grupo)
        $base = $t->players()->where('is_eliminated', false)->count();

        $texts = [];
        foreach ($busted as $i => $p) {
            $placement = $base - $i;
            $name = TournamentPlayer::whereKey($p['id'])->value('name');
            TournamentPlayer::whereKey($p['id'])->update(['is_eliminated' => true, 'placement' => $placement, 'current_stack' => 0]);
            DB::table('table_players')->where('table_id', $table->id)->where('tournament_player_id', $p['id'])->delete();
            $text = "{$name} eliminado en el puesto {$placement} (Mesa {$table->table_number})";
            $texts[] = $text;
            \App\Models\TournamentEvent::record($t, 'elimination', $text, $hand->hand_number, [
                'placement' => $placement,
                'table' => $table->table_number,
                'chips_in_hand' => (int) ($p['total'] ?? 0),
                'hand_id' => $hand->id,
            ], $table->id, (int) $p['id']);
        }

        $this->addPendingEvents($t, $texts);

        return $texts;
    }

    private function addPendingEvents(Tournament $t, array $texts): void
    {
        $t->refresh();
        $cfg = $t->config;
        $cfg['pending_events'] = array_merge($cfg['pending_events'] ?? [], $texts);
        $t->config = $cfg;
        $t->save();
    }

    // -------------------------------------------------- fin de ronda

    /**
     * Cierra la ronda visible: procesa eliminaciones, nivela mesas y crea la siguiente mano visible.
     *
     * @return array{hand_id:?int, finished:bool, events:string[], message?:string}
     */
    public function finishRound(TournamentHand $heroHand): array
    {
        return DB::transaction(function () use ($heroHand) {
            $heroHand = TournamentHand::whereKey($heroHand->id)->lockForUpdate()->firstOrFail();
            if ($heroHand->status !== 'completed') {
                throw new \RuntimeException('La mano actual no ha terminado');
            }

            $t = $heroHand->tournament;
            $table = $heroHand->table;
            if (!$table) {
                return $this->legacyNextHand($heroHand);
            }

            $next = TournamentHand::where('table_id', $table->id)->where('hand_number', '>', $heroHand->hand_number)->first();
            if ($next) {
                return ['hand_id' => $next->id, 'finished' => false, 'events' => []];
            }

            // 1) Eliminaciones de la mesa del héroe (las demás ya se procesaron al resolverlas)
            $this->processEliminations($t, $table, $heroHand);

            // 2) Nivelación / unificación de mesas
            $activeTableCount = Table::where('tournament_id', $t->id)
                ->where('is_active', true)
                ->count();
            $balanceEvents = $activeTableCount > 1 ? $this->balance->rebalance($t) : [];
            foreach ($balanceEvents as $ev) {
                \App\Models\TournamentEvent::record($t, $ev['type'], $ev['text'], $heroHand->hand_number, $ev['payload'], $ev['table_id'], $ev['player_id']);
            }
            if ($balanceEvents) {
                $this->addPendingEvents($t, array_column($balanceEvents, 'text'));
            }

            $hero = $t->players()->where('is_bot', false)->first();
            $alive = $t->players()->where('is_eliminated', false)->where('current_stack', '>', 0)->count();
            $heroEliminatedThisRound = $hero && $hero->is_eliminated
                && collect($heroHand->players_in_hand)->contains(
                    fn($player) => (int) $player['id'] === (int) $hero->id && (int) $player['stack'] <= 0
                );

            if ($heroEliminatedThisRound) {
                $message = "Has sido eliminado en el puesto {$hero->placement}";
                \App\Models\TournamentEvent::record(
                    $t,
                    'hero_eliminated',
                    $message,
                    $heroHand->hand_number,
                    ['placement' => $hero->placement],
                    $table->id,
                    $hero->id,
                );

                if ($alive > 1) {
                    $this->addPendingEvents($t, [$message . '. El torneo continuará en modo espectador.']);
                }
            }

            // Subida de nivel al empezar la siguiente ronda
            $nextRound = $heroHand->hand_number + 1;
            $prevBlinds = $this->blindsForRound($t, $heroHand->hand_number);
            $nextBlinds = $this->blindsForRound($t, $nextRound);
            if ($nextBlinds['level'] > $prevBlinds['level']) {
                $text = "Nivel {$nextBlinds['level']}: ciegas {$nextBlinds['small_blind']}/{$nextBlinds['big_blind']}";
                \App\Models\TournamentEvent::record($t, 'level_up', $text, $nextRound, $nextBlinds);
                $this->addPendingEvents($t, [$text]);
            }

            $t->refresh();
            $events = $t->config['pending_events'] ?? [];
            $cfg = $t->config;
            unset($cfg['pending_events']);
            $t->config = $cfg;
            $t->save();

            // 3) El torneo termina cuando queda un único jugador, no cuando se elimina el héroe.
            if ($alive <= 1) {
                $t->players()->where('is_eliminated', false)->update(['placement' => 1]);
                $t->update(['status' => 'completed', 'ended_at' => now()]);
                $winner = $t->players()->where('placement', 1)->first();
                \App\Models\TournamentEvent::record($t, 'tournament_finished', 'Torneo terminado: ' . ($winner->name ?? '?') . ' gana', $heroHand->hand_number, ['winner_id' => $winner->id ?? null]);
                return ['hand_id' => null, 'finished' => true, 'events' => $events, 'message' => '¡Has ganado el torneo!'];
            }

            // 4) Siguiente mano del héroe con los eventos al inicio del log
            $time = now()->format('H:i:s');
            $pending = array_map(fn($text) => ['time' => $time, 'text' => $text, 'type' => 'info'], $events);
            $watchTable = $hero && $hero->is_eliminated
                ? $this->spectatorTable($t, $table)
                : $table->refresh();
            $new = $this->createHand($watchTable, $heroHand->hand_number + 1, $pending);

            return ['hand_id' => $new->id, 'finished' => false, 'events' => $events];
        });
    }

    private function spectatorTable(Tournament $t, Table $preferred): Table
    {
        $tables = Table::where('tournament_id', $t->id)
            ->where('is_active', true)
            ->withCount('players')
            ->get()
            ->filter(fn(Table $table) => $table->players_count >= 2);

        $table = $tables->firstWhere('id', $preferred->id);
        if ($table) {
            return $table;
        }

        $table = $tables->sortByDesc('players_count')->first();
        if (!$table) {
            throw new \RuntimeException('No hay una mesa activa con suficientes jugadores para continuar como espectador.');
        }

        return $table;
    }

    /** Torneos antiguos sin mesas: una sola "mesa" con todos los jugadores. */
    private function legacyNextHand(TournamentHand $hand): array
    {
        $t = $hand->tournament;
        $existing = $t->hands()->where('hand_number', '>', $hand->hand_number)->first();
        if ($existing) {
            return ['hand_id' => $existing->id, 'finished' => false, 'events' => []];
        }

        $t->players()->where('current_stack', '<=', 0)->update(['is_eliminated' => true]);
        $hero = $t->players()->where('is_bot', false)->first();
        if ($hero && $hero->current_stack <= 0) {
            return ['hand_id' => null, 'finished' => true, 'events' => [], 'message' => 'Has sido eliminado del torneo'];
        }
        $alive = $t->players()->where('current_stack', '>', 0)->get();
        if ($alive->count() < 2) {
            return ['hand_id' => null, 'finished' => true, 'events' => [], 'message' => 'El torneo ha terminado'];
        }

        $blinds = $this->blindsForRound($t, $hand->hand_number + 1);
        $new = $t->hands()->create([
            'hand_number' => $hand->hand_number + 1,
            'level' => $blinds['level'],
            'blind_level_config' => [
                'small_blind' => $blinds['small_blind'],
                'big_blind' => $blinds['big_blind'],
                'ante' => $blinds['ante'],
            ],
            'button_seat' => ((int) $hand->button_seat + 1) % self::TABLE_SIZE,
            'hero_seat' => 0,
            'hero_cards' => [],
            'community_cards' => [],
            'stacks_before' => $alive->pluck('current_stack', 'id')->all(),
            'stacks_after' => [],
            'players_in_hand' => [],
            'actions' => [],
            'status' => 'in_progress',
            'result' => 'in_progress',
        ]);

        return ['hand_id' => $new->id, 'finished' => false, 'events' => []];
    }
}
