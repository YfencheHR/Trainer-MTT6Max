<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\Tournament;
use App\Models\TournamentEvent;
use App\Models\TournamentHand;
use App\Models\TournamentPlayer;
use App\Services\Bots\Brain\BrainRepository;
use App\Services\TableManagementService;
use App\Services\Tournament\TournamentRoundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentController extends Controller
{
    public function index()
    {
        $tournaments = Tournament::latest()->paginate(15);

        return view('mtt-simulator.index', compact('tournaments'));
    }

    public function create()
    {
        $bots = Bot::with('hudStats')
            ->get()
            ->filter(fn ($bot) => $bot->isPool())
            ->map(function ($bot) {
                $stats = $bot->hudStats->first();
                $bot->stats = $stats ? [
                    'vpip' => $stats->vpip,
                    'pfr' => $stats->pfr,
                    'three_bet' => $stats->three_bet,
                    'af' => $stats->af_preflop,
                ] : [
                    'vpip' => $bot->vpip ?? 20,
                    'pfr' => $bot->pfr ?? 15,
                    'three_bet' => 7,
                    'af' => 2.5,
                ];

                return $bot;
            })
            ->values();

        $handTypes = config('hand-types');
        $defaults = config('tournament-defaults');
        $blindLevelDefaults = array_map(
            fn ($settings) => $settings['hands_per_level'],
            config('blind-levels'),
        );

        return view('mtt-simulator.create', compact('bots', 'handTypes', 'defaults', 'blindLevelDefaults'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'starting_stack' => 'required|integer|min:100|max:1000000',
            'big_blind' => 'required|integer|min:2|max:1000',
            'num_players' => 'required|integer|min:2|max:1000',
            'speed' => 'required|in:tipica,rapida,muy_rapida',
            'hands_per_level' => 'required|integer|min:1|max:1000',
            'ante_schedule' => ['nullable', 'string', 'max:500', 'regex:/^[0-9]+(?:\s*,\s*[0-9]+)*$/'],
            'bot_percentages' => 'required|array',
            'bot_percentages.*' => 'required|integer|min:0|max:100',
            'hand_types' => 'required|array|min:1',
            'hand_types.*' => 'required|integer|between:1,13',
            'show_all_cards' => 'boolean',
            'no_hud_data' => 'boolean',
            'player_time_mode' => 'required|in:unlimited,standard',
            'time_per_decision_seconds' => 'nullable|integer|min:5|max:300',
            'bank_time_seconds' => 'nullable|integer|min:30|max:3600',
        ]);

        $anteLevels = isset($validated['ante_schedule'])
            ? array_map('intval', array_map('trim', explode(',', $validated['ante_schedule'])))
            : [0];
        if (max($anteLevels) > 100000) {
            return back()->withInput()->withErrors([
                'ante_schedule' => __('validation.max.numeric', ['attribute' => __('tournaments.ante_schedule'), 'max' => 100000]),
            ]);
        }
        $validated['ante_by_level'] = $anteLevels;

        $poolBots = Bot::all()
            ->filter(fn ($bot) => $bot->isPool())
            ->keyBy('id');

        // Filtrar bots con porcentaje > 0
        $botPercentages = array_filter($validated['bot_percentages'], fn ($p) => $p > 0);

        $invalidBotIds = array_filter(array_keys($botPercentages), fn ($botId) => ! $poolBots->has($botId));
        if ($invalidBotIds) {
            return back()->withInput()->withErrors(['bot_percentages' => __('tournaments.pool_bots_only')]);
        }

        if (empty($botPercentages)) {
            return back()->withInput()->withErrors(['bot_percentages' => __('tournaments.no_bots_selected')]);
        }

        // Rivales = jugadores totales - el héroe, repartidos según el % de cada bot
        $allBots = $poolBots;
        $opponents = max(1, (int) $validated['num_players'] - 1);
        $totalWeight = array_sum($botPercentages);

        $shares = [];
        foreach ($botPercentages as $botId => $percentage) {
            if ($allBots->find($botId)) {
                $raw = $opponents * $percentage / $totalWeight;
                $shares[$botId] = ['count' => (int) floor($raw), 'rest' => $raw - floor($raw)];
            }
        }
        // Método del mayor resto: que la suma sea exactamente el nº de rivales
        $missing = $opponents - array_sum(array_column($shares, 'count'));
        uasort($shares, fn ($x, $y) => $y['rest'] <=> $x['rest']);
        foreach (array_keys($shares) as $botId) {
            if ($missing-- <= 0) {
                break;
            }
            $shares[$botId]['count']++;
        }

        $botsToAdd = [];
        foreach ($shares as $botId => $share) {
            for ($i = 0; $i < $share['count']; $i++) {
                $botsToAdd[] = $allBots->find($botId);
            }
        }
        shuffle($botsToAdd);
        $num_players = count($botsToAdd) + 1; // +1 para el jugador

        $config = $this->buildTournamentConfig($validated, $botsToAdd, $num_players);

        $tournament = Tournament::create([
            'title' => Tournament::generateTitle(),
            'status' => 'pending',
            'config' => $config,
        ]);

        // Crear jugadores del torneo
        $this->createTournamentPlayers($tournament, $botsToAdd, $validated['starting_stack']);

        return redirect()->route('mtt-simulator.show', $tournament)
            ->with('success', __('tournaments.created_successfully'));
    }

    public function play(Tournament $tournament)
    {
        if ($tournament->status !== 'in_progress') {
            return redirect()->route('mtt-simulator.show', $tournament)
                ->with('error', __('tournaments.tournament_not_started'));
        }

        $rounds = app(TournamentRoundService::class);
        $heroTable = $rounds->heroTable($tournament);

        if ($heroTable) {
            // Mano más reciente de la mesa del héroe (o la primera ronda)
            $hand = TournamentHand::where('table_id', $heroTable->id)->orderByDesc('id')->first()
                ?? $rounds->createHand($heroTable, 1);
        } else {
            // Torneos antiguos sin mesas
            $hand = $tournament->hands()->orderByDesc('id')->first();
            if (! $hand) {
                $blinds = $rounds->blindsForRound($tournament, 1);
                $hand = $tournament->hands()->create([
                    'hand_number' => 1,
                    'level' => $blinds['level'],
                    'blind_level_config' => [
                        'small_blind' => $blinds['small_blind'],
                        'big_blind' => $blinds['big_blind'],
                        'ante' => $blinds['ante'],
                    ],
                    'hero_seat' => 0, 'hero_cards' => [], 'hero_hand_type_id' => null, 'community_cards' => [],
                    'stacks_before' => [], 'stacks_after' => [], 'players_in_hand' => [], 'actions' => [],
                    'status' => 'in_progress', 'result' => 'in_progress',
                ]);
            }
        }
        $config = $tournament->config;
        $players = $config['players'] ?? [];
        $logs = [];
        $table = [
            'id' => $tournament->id,
            'name' => $tournament->title,
        ];
        $communityCards = [];
        $currentPlayer = $players[0] ?? null;
        $pot = 0;
        $activeTables = [];
        $currentHand = $hand;
        $noHudData = $config['game_settings']['no_hud_data'] ?? false;

        return view('mtt-simulator.play', compact(
            'tournament',
            'hand',
            'players',
            'logs',
            'table',
            'communityCards',
            'currentPlayer',
            'pot',
            'activeTables',
            'currentHand',
            'noHudData'
        ));
    }

    public function show(Tournament $tournament)
    {
        $tournament->load('hands');

        return view('mtt-simulator.show', compact('tournament'));
    }

    public function start(Tournament $tournament)
    {
        if ($tournament->status !== 'pending') {
            return back()->with('error', __('tournaments.cannot_start'));
        }

        $tournament->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        // Distribuir jugadores en mesas
        $tableService = new TableManagementService;
        $tableService->distributePlayers($tournament);

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

        return redirect()->route('mtt-simulator.play', $tournament)
            ->with('success', __('tournaments.started'));
    }

    public function end(Tournament $tournament)
    {
        if ($tournament->status !== 'in_progress') {
            return back()->with('error', __('tournaments.cannot_end'));
        }

        $tournament->update([
            'status' => 'completed',
            'ended_at' => now(),
        ]);

        return redirect()->route('mtt-simulator.show', $tournament)
            ->with('success', __('tournaments.ended'));
    }

    private function createTournamentPlayers(Tournament $tournament, $bots, $starting_stack): void
    {
        $position = 0;

        // Crear jugador humano (usuario actual)
        TournamentPlayer::create([
            'tournament_id' => $tournament->id,
            'user_id' => auth()->id(),
            'bot_id' => null,
            'name' => auth()->user()->name ?? 'Player',
            'starting_stack' => $starting_stack,
            'current_stack' => $starting_stack,
            'is_bot' => false,
            'position' => $position++,
        ]);

        // Crear jugadores bots (si un bot se repite, se numera: Setminer, Setminer 2…)
        // Cada jugador hereda el cerebro de su bot tal como está ahora (snapshot congelado)
        $brains = app(BrainRepository::class);
        $snapshots = [];
        $seen = [];
        foreach ($bots as $bot) {
            $seen[$bot->id] = ($seen[$bot->id] ?? 0) + 1;
            $baseName = $bot->nombre ?? $bot->name;
            TournamentPlayer::create([
                'tournament_id' => $tournament->id,
                'bot_id' => $bot->id,
                'user_id' => null,
                'brain_snapshot_id' => $snapshots[$bot->id] ??= $brains->snapshotFor($bot),
                'name' => $seen[$bot->id] > 1 ? $baseName.' '.$seen[$bot->id] : $baseName,
                'starting_stack' => $starting_stack,
                'current_stack' => $starting_stack,
                'is_bot' => true,
                'position' => $position++,
            ]);
        }
    }

    private function buildTournamentConfig(array $data, $bots, int $num_players): array
    {
        $players = [
            [
                'seat' => 1,
                'name' => __('tournaments.you'),
                'type' => 'human',
                'starting_stack' => $data['starting_stack'],
                'status' => 'active',
            ],
        ];

        $seat = 2;
        foreach ($bots as $bot) {
            $players[] = [
                'seat' => $seat,
                'name' => $bot->nombre ?? $bot->name,
                'type' => 'bot',
                'bot_id' => $bot->id,
                'starting_stack' => $data['starting_stack'],
                'status' => 'active',
            ];
            $seat++;
        }

        $gameSettings = config('game-settings');

        return [
            'tournament_type' => 'mtt',
            'format' => '6-max',
            'basic_settings' => [
                'starting_stack' => $data['starting_stack'],
                'num_players' => $num_players,
                'big_blind' => $data['big_blind'],
            ],
            'blind_structure' => [
                'speed' => $data['speed'],
                'hands_per_level' => $data['hands_per_level'],
                'antes' => $data['ante_by_level'] ?? [0],
            ],
            'players' => $players,
            'hand_types' => $data['hand_types'],
            'game_settings' => array_merge($gameSettings, [
                'show_all_cards' => $data['show_all_cards'] ?? false,
                'no_hud_data' => $data['no_hud_data'] ?? false,
            ]),
            'player_time_settings' => [
                'mode' => $data['player_time_mode'] ?? 'unlimited',
                'time_per_decision_seconds' => $data['time_per_decision_seconds'] ?? 30,
                'bank_time_seconds' => $data['bank_time_seconds'] ?? 300,
            ],
        ];
    }
}
