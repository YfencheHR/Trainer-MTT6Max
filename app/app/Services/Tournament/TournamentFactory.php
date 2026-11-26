<?php

namespace App\Services\Tournament;

use App\Models\Bot;
use App\Models\Tournament;
use App\Models\TournamentEvent;
use App\Models\TournamentPlayer;
use App\Services\Bots\BotFreezer;
use App\Services\Bots\Brain\BrainRepository;
use App\Services\TableManagementService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Crea e inicia torneos. Lo usan la pantalla de creación (con héroe) y el simulador sin cabeza (solo bots), para que los
 * dos generen torneos EXACTAMENTE igual: mismo reparto de rivales, mismas copias congeladas de los perfiles, mismas mesas.
 */
class TournamentFactory
{
    /**
     * Reparte $opponents plazas entre los bots según su porcentaje (método del mayor resto: la suma es exacta) y las baraja.
     *
     * @param  Collection<int, Bot>  $bots  bots disponibles, indexados por id
     * @param  array<int|string, int|float>  $percentages  porcentaje (peso) de cada bot por id
     * @return Bot[] un elemento por plaza (un bot puede repetirse)
     */
    public function allocateBots(Collection $bots, array $percentages, int $opponents): array
    {
        $totalWeight = array_sum($percentages);
        $shares = [];
        foreach ($percentages as $botId => $percentage) {
            if ($bots->get($botId)) {
                $raw = $opponents * $percentage / $totalWeight;
                $shares[$botId] = ['count' => (int) floor($raw), 'rest' => $raw - floor($raw)];
            }
        }
        $missing = $opponents - array_sum(array_column($shares, 'count'));
        uasort($shares, fn ($x, $y) => $y['rest'] <=> $x['rest']);
        foreach (array_keys($shares) as $botId) {
            if ($missing-- <= 0) {
                break;
            }
            $shares[$botId]['count']++;
        }

        $toAdd = [];
        foreach ($shares as $botId => $share) {
            for ($i = 0; $i < $share['count']; $i++) {
                $toAdd[] = $bots->get($botId);
            }
        }
        shuffle($toAdd);

        return $toAdd;
    }

    /**
     * Crea el torneo (pendiente) con sus jugadores.
     *
     * @param  array  $settings  starting_stack, big_blind, speed, hands_per_level, ante_by_level, hand_types, show_all_cards,
     *                           no_hud_data, bot_memory (none|tournament), player_time_mode, time_per_decision_seconds, bank_time_seconds
     * @param  Bot[]  $bots  rivales (uno por plaza)
     * @param  array{user_id?: int|null, name?: string}|null  $hero  datos del héroe humano; null = torneo solo de bots
     * @param  bool  $heroEntryInConfig  incluir la entrada "tú" en config.players (la pantalla de creación siempre lo ha hecho)
     */
    public function create(array $settings, array $bots, ?array $hero, bool $heroEntryInConfig = true): Tournament
    {
        $numPlayers = count($bots) + ($hero ? 1 : 0);

        $tournament = Tournament::create([
            'title' => Tournament::generateTitle(),
            'status' => 'pending',
            'config' => $this->buildConfig($settings, $bots, $numPlayers, $heroEntryInConfig),
        ]);

        $this->seatPlayers($tournament, $bots, $settings['starting_stack'], $hero);

        return $tournament;
    }

    /** Pasa el torneo a "en curso", reparte los jugadores en mesas y deja el reparto inicial en el historial. */
    public function start(Tournament $tournament): void
    {
        $tournament->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        (new TableManagementService)->distributePlayers($tournament);

        // Historial: reparto inicial de asientos (necesario para poder reproducir el torneo)
        $seating = [];
        foreach ($tournament->tables()->orderBy('table_number')->get() as $table) {
            $seats = DB::table('table_players')
                ->join('tournament_players', 'tournament_players.id', '=', 'table_players.tournament_player_id')
                ->where('table_players.table_id', $table->id)->orderBy('table_players.seat_number')
                ->get(['table_players.seat_number as seat', 'tournament_players.id as player_id', 'tournament_players.name', 'tournament_players.bot_id', 'tournament_players.starting_stack as stack']);
            $seating[] = ['table_id' => $table->id, 'number' => $table->table_number, 'seats' => $seats->all()];
        }
        TournamentEvent::record(
            $tournament, 'tournament_started',
            'Torneo iniciado: '.$tournament->players()->count().' jugadores en '.count($seating).' mesas',
            0, ['tables' => $seating, 'config' => $tournament->config]
        );
    }

    public function seatPlayers(Tournament $tournament, array $bots, $startingStack, ?array $hero): void
    {
        $position = 0;

        if ($hero) {
            TournamentPlayer::create([
                'tournament_id' => $tournament->id,
                'user_id' => $hero['user_id'] ?? null,
                'bot_id' => null,
                'name' => $hero['name'] ?? 'Player',
                'starting_stack' => $startingStack,
                'current_stack' => $startingStack,
                'is_bot' => false,
                'position' => $position++,
            ]);
        }

        // Crear jugadores bots (si un bot se repite, se numera: Setminer, Setminer 2…)
        // Cada jugador hereda el cerebro de su bot tal como está ahora (snapshot congelado) y juega con una COPIA CONGELADA
        // de su perfil (estadísticas y rangos): importar, editar o borrar el perfil después no afecta a este torneo.
        // bot_id es la copia; source_bot_id, el perfil vivo del que salió.
        $brains = app(BrainRepository::class);
        $freezer = app(BotFreezer::class);
        $snapshots = [];
        $frozen = [];
        $seen = [];
        foreach ($bots as $bot) {
            $seen[$bot->id] = ($seen[$bot->id] ?? 0) + 1;
            $baseName = $bot->nombre ?? $bot->name;
            TournamentPlayer::create([
                'tournament_id' => $tournament->id,
                'bot_id' => ($frozen[$bot->id] ??= $freezer->frozenFor($bot))->id,
                'source_bot_id' => $bot->id,
                'user_id' => null,
                'brain_snapshot_id' => $snapshots[$bot->id] ??= $brains->snapshotFor($bot),
                'name' => $seen[$bot->id] > 1 ? $baseName.' '.$seen[$bot->id] : $baseName,
                'starting_stack' => $startingStack,
                'current_stack' => $startingStack,
                'is_bot' => true,
                'position' => $position++,
            ]);
        }
    }

    private function buildConfig(array $data, array $bots, int $numPlayers, bool $heroEntry): array
    {
        $players = [];
        $seat = 1;
        if ($heroEntry) {
            $players[] = [
                'seat' => $seat++,
                'name' => __('tournaments.you'),
                'type' => 'human',
                'starting_stack' => $data['starting_stack'],
                'status' => 'active',
            ];
        }
        foreach ($bots as $bot) {
            $players[] = [
                'seat' => $seat++,
                'name' => $bot->nombre ?? $bot->name,
                'type' => 'bot',
                'bot_id' => $bot->id,
                'starting_stack' => $data['starting_stack'],
                'status' => 'active',
            ];
        }

        return [
            'tournament_type' => 'mtt',
            'format' => '6-max',
            'basic_settings' => [
                'starting_stack' => $data['starting_stack'],
                'num_players' => $numPlayers,
                'big_blind' => $data['big_blind'],
            ],
            'blind_structure' => [
                'speed' => $data['speed'],
                'hands_per_level' => $data['hands_per_level'],
                'antes' => $data['ante_by_level'] ?? [0],
            ],
            'players' => $players,
            'hand_types' => $data['hand_types'],
            'game_settings' => array_merge(config('game-settings'), [
                'show_all_cards' => $data['show_all_cards'] ?? false,
                'no_hud_data' => $data['no_hud_data'] ?? false,
                'bot_memory' => $data['bot_memory'] ?? 'none',
            ]),
            'player_time_settings' => [
                'mode' => $data['player_time_mode'] ?? 'unlimited',
                'time_per_decision_seconds' => $data['time_per_decision_seconds'] ?? 30,
                'bank_time_seconds' => $data['bank_time_seconds'] ?? 300,
            ],
        ];
    }
}
