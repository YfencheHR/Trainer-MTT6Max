<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\Tournament;
use App\Models\TournamentHand;
use App\Services\Tournament\TournamentFactory;
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
            'hero_player' => 'boolean',
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

        // Validar que hay al menos 1 bot importado (no-base) con todas sus estadísticas
        $selectedBots = $poolBots->only(array_keys($botPercentages));
        $importedBots = $selectedBots->filter(fn ($bot) => ($bot->metadata['source'] ?? null) === 'pt4_import');

        if ($importedBots->isEmpty()) {
            return back()->withInput()->withErrors([
                'bot_percentages' => __('tournaments.no_imported_bots'),
            ]);
        }

        $botsWithFullStats = $importedBots->filter(function ($bot) {
            $hudStats = $bot->hudStats()
                ->whereNull('posicion')
                ->whereNull('accion')
                ->first();
            return $hudStats &&
                   !is_null($hudStats->vpip) &&
                   !is_null($hudStats->pfr) &&
                   !is_null($hudStats->three_bet) &&
                   !is_null($hudStats->cbet) &&
                   !is_null($hudStats->donk);
        });

        if ($botsWithFullStats->isEmpty()) {
            return back()->withInput()->withErrors([
                'bot_percentages' => __('tournaments.no_bots_with_complete_stats'),
            ]);
        }

        // Rivales = jugadores totales - el héroe (si existe), repartidos según el % de cada bot
        $hasHero = $validated['hero_player'] ?? true;
        $opponents = $hasHero ? max(1, (int) $validated['num_players'] - 1) : (int) $validated['num_players'];

        $factory = app(TournamentFactory::class);
        $botsToAdd = $factory->allocateBots($poolBots, $botPercentages, $opponents);
        $hero = $hasHero ? ['user_id' => auth()->id(), 'name' => auth()->user()->name ?? 'Player'] : null;
        $tournament = $factory->create($validated, $botsToAdd, $hero);

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

        // Si no hay hero, usar la primera mesa activa del torneo
        $displayTable = $heroTable ?? $tournament->tables()->where('is_active', true)->first();

        if ($displayTable) {
            // Mano más reciente de la mesa (o la primera ronda)
            $hand = TournamentHand::where('table_id', $displayTable->id)->orderByDesc('id')->first()
                ?? $rounds->createHand($displayTable, 1);
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

        app(TournamentFactory::class)->start($tournament);

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

}
