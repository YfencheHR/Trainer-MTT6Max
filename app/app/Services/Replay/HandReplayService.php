<?php

namespace App\Services\Replay;

use App\Models\HandPlayer;
use App\Models\HandReplay;
use App\Models\TournamentHand;
use App\Models\TournamentPlayer;
use App\Services\HandPlayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Repite una mano ya jugada con las MISMAS cartas (las de cada jugador y el board) pero con decisiones NUEVAS de los bots: no juegan como jugaron, deciden
 * otra vez con el mismo motor y con la misma versión congelada de cada bot que usó su torneo. Sirve para probar manos concretas y ver si los bots actúan
 * muy distinto de como lo hicieron.
 *
 * La repetición se juega con el motor real de la mesa (HandPlayService) sobre una copia de la mano dentro de una transacción que se DESHACE al terminar: el
 * torneo no se toca (ni stacks, ni estadísticas, ni historial). Lo que queda es lo que esta clase guarda aparte (hand_replays y hand_replay_actions): el
 * resumen y todas las acciones de las dos versiones, la jugada y la repetida, para analizarlas después.
 *
 * Limitaciones: los bots deciden con las estadísticas observadas del torneo tal como están ahora (no como estaban en esa mano); un jugador humano repite lo
 * que hizo mientras la mano se desarrolla igual y, si ya no se puede, pasa o se retira.
 */
class HandReplayService
{
    /** Acciones que cuentan como decisión (el resto del historial son ciegas, antes y reparto de calles). */
    private const DECISIONS = ['fold', 'check', 'call', 'raise', 'allin'];

    /** Para no chocar con la mano original en la restricción de números de mano únicos. */
    private const NUMBER_OFFSET = 1_000_000;

    public function __construct(private HandPlayService $play)
    {
    }

    /**
     * Repite la mano `$times` veces (cada una con decisiones nuevas) y guarda cada repetición.
     *
     * @return array<int, HandReplay>
     */
    public function replay(TournamentHand $original, int $times = 1): array
    {
        if ($original->status !== 'completed' || ! $original->handPlayers()->exists()) {
            throw new \RuntimeException(__('tournaments.replay_not_available'));
        }
        $deal = $this->dealOf($original);
        $played = $this->actionsOf($original);
        $script = $this->humanScript($original, $deal);
        $originalResult = $this->resultOf($original);
        $batch = (string) Str::uuid();

        $saved = [];
        for ($run = 1; $run <= max(1, $times); $run++) {
            [$replayed, $replayResult] = $this->simulate($original, $deal, $script);
            $saved[] = $this->save($original, $batch, $run, $played, $originalResult, $replayed, $replayResult);
        }

        return $saved;
    }

    /** El reparto de la mano jugada: quién se sentó, con cuántas fichas y qué cartas, y el board completo. */
    private function dealOf(TournamentHand $hand): array
    {
        $stacks = HandPlayer::where('tournament_hand_id', $hand->id)->pluck('stack_start', 'tournament_player_id');
        $players = [];
        foreach ((array) $hand->players_in_hand as $p) {
            $players[] = ['id' => $p['id'], 'name' => $p['name'], 'stack' => (int) ($stacks[$p['id']] ?? $p['stack']), 'seat' => $p['seat'],
                'is_bot' => (bool) $p['is_bot'], 'hole_cards' => $p['hole_cards']];
        }

        return ['players' => $players, 'board_full' => $hand->actions['board_full'] ?? $hand->board_full];
    }

    /** Las acciones guardadas de una repetición (o de la mano jugada tal como quedó copiada en ella), con la misma forma que `actionsOf`. */
    public function replayActions(HandReplay $replay, string $side = 'replay'): array
    {
        return $replay->actions()->where('side', $side)->get()->map(fn($a) => [
            'street' => $a->street, 'player_id' => (int) $a->tournament_player_id, 'player_name' => $a->player_name, 'bot_id' => $a->bot_id,
            'action' => $a->action, 'amount' => (int) $a->amount, 'pot_before' => $a->pot_before, 'to_call' => $a->to_call, 'stack_before' => $a->stack_before,
            'equity' => $a->equity, 'distribution' => $a->distribution, 'reason' => $a->reason,
        ])->values()->all();
    }

    /** Las decisiones de la mano jugada, en orden. */
    public function actionsOf(TournamentHand $hand): array
    {
        $names = collect((array) $hand->players_in_hand)->pluck('name', 'id');
        $bots = TournamentPlayer::whereIn('id', $names->keys())->pluck('bot_id', 'id');

        return $hand->handActions()->whereIn('type', self::DECISIONS)->orderBy('sequence')->get()->map(fn($a) => [
            'street' => $a->street, 'player_id' => (int) $a->tournament_player_id, 'player_name' => (string) ($names[$a->tournament_player_id] ?? '?'),
            'bot_id' => $bots[$a->tournament_player_id] ?? null, 'action' => $a->type, 'amount' => (int) ($a->type === 'raise' || $a->type === 'allin' ? ($a->to_amount ?? $a->amount) : $a->amount),
            'pot_before' => $a->pot_before, 'to_call' => $a->to_call, 'stack_before' => $a->stack_before, 'equity' => $a->equity,
            'distribution' => $this->distributionOf($a->decision), 'reason' => $a->decision['reason'] ?? null,
        ])->all();
    }

    /** Las probabilidades que el bot daba a cada acción (el motor de reglas las guarda en %, la IA en la traza). */
    private function distributionOf(?array $decision): ?array
    {
        $distribution = $decision['brain']['distribution'] ?? null;

        return is_array($distribution) ? $distribution : null;
    }

    /** Lo que hizo el jugador humano, si lo hubo: [id => [[acción, importe], …]]. Los bots no lo necesitan: deciden de nuevo. */
    private function humanScript(TournamentHand $original, array $deal): array
    {
        $humans = collect($deal['players'])->where('is_bot', false)->pluck('id')->all();
        $script = [];
        foreach ($this->actionsOf($original) as $a) {
            if (in_array($a['player_id'], $humans, true)) {
                $script[$a['player_id']][] = [$a['action'], $a['amount']];
            }
        }

        return $script;
    }

    /** Quién ganó y cuánto, tal como quedó guardado en la mano (hand_players). */
    private function resultOf(TournamentHand $hand): array
    {
        $names = collect((array) $hand->players_in_hand)->pluck('name', 'id');
        $won = HandPlayer::where('tournament_hand_id', $hand->id)->where('won', '>', 0)->get(['tournament_player_id', 'won']);

        return [
            'pot' => (int) ($hand->actions['pot_won'] ?? $hand->getTotalPot()),
            'winners' => $won->map(fn($w) => ['id' => (int) $w->tournament_player_id, 'name' => $names[$w->tournament_player_id] ?? '?', 'won' => (int) $w->won])->values()->all(),
        ];
    }

    /**
     * Juega la repetición sobre una copia de la mano y deshace todo lo que escribió el motor.
     *
     * @return array{0: array, 1: array} acciones y resultado de la repetición
     */
    private function simulate(TournamentHand $original, array $deal, array $script): array
    {
        DB::beginTransaction();
        try {
            $copy = $this->copyOf($original);
            $this->play->ensureInitialized($copy, $deal);
            $this->play->playReplay($copy, $script);
            $copy = $copy->fresh();

            return [$this->actionsOf($copy), $this->resultOf($copy)];
        } finally {
            DB::rollBack();
        }
    }

    /** La copia de trabajo de la mano (en una transacción que se deshace): misma mesa, ciegas y botón, sin jugar. */
    private function copyOf(TournamentHand $original): TournamentHand
    {
        return TournamentHand::create([
            'tournament_id' => $original->tournament_id, 'table_id' => $original->table_id,
            'hand_number' => $original->hand_number + self::NUMBER_OFFSET, 'level' => $original->level,
            'blind_level_config' => $original->blind_level_config, 'button_seat' => $original->button_seat, 'hero_seat' => $original->hero_seat,
            'hero_cards' => [], 'community_cards' => [], 'stacks_before' => [], 'stacks_after' => [], 'players_in_hand' => [], 'actions' => [],
            'status' => 'in_progress', 'result' => 'in_progress',
        ]);
    }

    /**
     * La mesa paso a paso: un estado por cada momento de la mano (después de las ciegas y después de cada acción), con el MISMO formato que la mesa de
     * juego (`HandPlayService::state`), para pintarlo con el mismo componente. Se obtiene volviendo a jugar la mano con las acciones dadas (las de la
     * mano jugada o las de una repetición) sobre el reparto original, sin tocar el torneo. Se ven las cartas de todos.
     *
     * @param  array  $actions  acciones en orden (actionsOf o las guardadas de una repetición)
     * @return array<int, array> `frames[0]` es la mesa antes de actuar; `frames[i]` tras la acción i, con `decision` = esa acción y lo que pensó el bot
     */
    public function frames(TournamentHand $original, array $actions): array
    {
        $deal = $this->dealOf($original);
        $script = [];
        foreach ($actions as $a) {
            $script[$a['player_id']][] = [$a['action'], $a['amount']];
        }
        $everyone = array_column($deal['players'], 'id');
        $reveal = $original->tournament->replicate();
        $reveal->config = array_replace_recursive((array) $original->tournament->config, ['game_settings' => ['show_all_cards' => true, 'no_hud_data' => true]]);

        $frame = function (TournamentHand $hand, ?array $action) use ($original, $reveal) {
            $hand->setRelation('tournament', $reveal);
            $state = $this->play->state($hand, true);
            $state['hand_number'] = $state['tournament']['hand_number'] = (int) $original->hand_number;
            $state['decision'] = $action ? [
                'player' => $action['player_name'], 'street' => $action['street'], 'action' => $action['action'], 'amount' => $action['amount'],
                'distribution' => $action['distribution'] ?? null, 'reason' => $action['reason'] ?? null, 'equity' => $action['equity'] ?? null,
            ] : null;

            return $state;
        };

        DB::beginTransaction();
        try {
            $copy = $this->copyOf($original);
            $this->play->ensureInitialized($copy, $deal);
            $frames = [$frame($copy->fresh(), null)];
            $step = 0;
            $this->play->playReplay($copy, $script, $everyone, function (TournamentHand $hand) use (&$frames, &$step, $actions, $frame) {
                $frames[] = $frame($hand, $actions[$step++] ?? null);
            });

            return $frames;
        } finally {
            DB::rollBack();
        }
    }

    private function save(TournamentHand $original, string $batch, int $run, array $played, array $playedResult, array $replayed, array $replayResult): HandReplay
    {
        $divergence = $this->divergence($played, $replayed);
        $sameWinner = collect($playedResult['winners'])->pluck('id')->sort()->values()->all() === collect($replayResult['winners'])->pluck('id')->sort()->values()->all();

        $replay = HandReplay::create([
            'tournament_id' => $original->tournament_id, 'tournament_hand_id' => $original->id, 'batch_id' => $batch, 'run' => $run,
            'engine' => (string) config('ai.engine'), 'diverged' => $divergence['first'] !== null, 'same_prefix' => $divergence['same_prefix'],
            'original_actions' => count($played), 'replay_actions' => count($replayed), 'first_divergence' => $divergence['first'],
            'original_result' => $playedResult, 'replay_result' => $replayResult, 'same_winner' => $sameWinner,
        ]);
        $rows = [];
        foreach (['original' => $played, 'replay' => $replayed] as $side => $actions) {
            foreach ($actions as $i => $a) {
                $rows[] = ['hand_replay_id' => $replay->id, 'side' => $side, 'seq' => $i + 1, 'street' => $a['street'], 'tournament_player_id' => $a['player_id'],
                    'player_name' => $a['player_name'], 'bot_id' => $a['bot_id'], 'action' => $a['action'], 'amount' => $a['amount'], 'pot_before' => $a['pot_before'],
                    'to_call' => $a['to_call'], 'stack_before' => $a['stack_before'], 'equity' => $a['equity'],
                    'distribution' => $a['distribution'] === null ? null : json_encode($a['distribution']), 'reason' => $a['reason']];
            }
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('hand_replay_actions')->insert($chunk);
        }

        return $replay;
    }

    /**
     * Dónde se separan las dos versiones: cuántas acciones iguales hay desde el principio y la primera que cambia (otra acción, otro importe, otro
     * jugador o una mano que se acaba antes o después). `first` es null si la repetición fue idéntica.
     *
     * @return array{same_prefix: int, first: ?array}
     */
    public function divergence(array $played, array $replayed): array
    {
        $same = fn($a, $b) => $a['player_id'] === $b['player_id'] && $a['street'] === $b['street'] && $a['action'] === $b['action'] && $a['amount'] === $b['amount'];
        $max = max(count($played), count($replayed));
        for ($i = 0; $i < $max; $i++) {
            $a = $played[$i] ?? null;
            $b = $replayed[$i] ?? null;
            if ($a === null || $b === null || ! $same($a, $b)) {
                $who = $a ?? $b;

                return ['same_prefix' => $i, 'first' => [
                    'index' => $i + 1, 'street' => $who['street'], 'player' => $who['player_name'],
                    'played' => $a ? ['action' => $a['action'], 'amount' => $a['amount']] : null,
                    'replayed' => $b ? ['action' => $b['action'], 'amount' => $b['amount']] : null,
                ]];
            }
        }

        return ['same_prefix' => count($played), 'first' => null];
    }
}
