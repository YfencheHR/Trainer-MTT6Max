<?php

use App\Http\Controllers\BotController;
use App\Http\Controllers\GameActionController;
use App\Http\Controllers\GamePlayController;
use App\Http\Controllers\HandMatrixController;
use App\Http\Controllers\HandReplayController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MadeHandController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentHistoryController;
use App\Http\Controllers\TournamentWindowController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $bots = \App\Models\Bot::latest()->limit(6)->get();
    return view('dashboard', ['bots' => $bots]);
})->name('dashboard');

// CRUD Routes - must be before catch-all
Route::get('/bots', [BotController::class, 'index'])->name('bots.index');
Route::get('/bots/create', [BotController::class, 'create'])->name('bots.create');
Route::post('/bots', [BotController::class, 'store'])->name('bots.store');
Route::patch('/bots/{bot}/preflop-tables/{position}/{hand}', [BotController::class, 'updatePreflopRange'])->name('bots.preflop-tables.update');
Route::delete('/bots/{bot}/preflop-tables/{position}/{hand}', [BotController::class, 'resetPreflopRange'])->name('bots.preflop-tables.reset');
Route::get('/bots/{bot}/preflop-tables/{position}/{type}', [BotController::class, 'editPreflopTable'])->name('bots.preflop-tables.edit');
Route::get('/bots/{bot}', [BotController::class, 'show'])->name('bots.show');
Route::get('/bots/{bot}/edit', [BotController::class, 'edit'])->name('bots.edit');
Route::patch('/bots/{bot}', [BotController::class, 'update'])->name('bots.update');
Route::delete('/bots/{bot}', [BotController::class, 'destroy'])->name('bots.destroy');
Route::post('/bots/{bot}/duplicate', [BotController::class, 'duplicate'])->name('bots.duplicate');

// Made Hands Routes
Route::resource('made-hands', MadeHandController::class);

// MTT Simulator Routes (Tournaments)
Route::get('/mtt-simulator', [TournamentController::class, 'index'])->name('mtt-simulator.index');
Route::get('/mtt-simulator/create', [TournamentController::class, 'create'])->name('mtt-simulator.create');
Route::post('/mtt-simulator', [TournamentController::class, 'store'])->name('mtt-simulator.store');
Route::get('/mtt-simulator/{tournament}/play', [TournamentController::class, 'play'])->name('mtt-simulator.play');
Route::get('/mtt-simulator/{tournament}', [TournamentController::class, 'show'])->name('mtt-simulator.show');
Route::post('/mtt-simulator/{tournament}/start', [TournamentController::class, 'start'])->name('mtt-simulator.start');
Route::post('/mtt-simulator/{tournament}/end', [TournamentController::class, 'end'])->name('mtt-simulator.end');

// Hand Matrix Routes - Rutas específicas ANTES que los parámetros
Route::get('/hand-matrix', [HandMatrixController::class, 'index'])->name('hand-matrix.index');
Route::get('/hand-matrix/api/filter-structure', [HandMatrixController::class, 'getFilterStructure'])->name('hand-matrix.get-filter-structure');
Route::get('/hand-matrix/matrix-values', [HandMatrixController::class, 'getMatrixValues'])->name('hand-matrix.get-matrix-values');
Route::patch('/hand-matrix/batch-update', [HandMatrixController::class, 'batchUpdate'])->name('hand-matrix.batch-update');
Route::patch('/hand-matrix/strategies', [HandMatrixController::class, 'batchUpdateStrategies'])->name('hand-matrix.batch-update-strategies');
// Rutas genéricas con parámetros AL FINAL
Route::get('/hand-matrix/{handGroup}', [HandMatrixController::class, 'show'])->name('hand-matrix.show');
Route::patch('/hand-matrix/{handGroup}', [HandMatrixController::class, 'update'])->name('hand-matrix.update');

// Gameplay Routes - MTT Simulator (Nueva mesa de poker)
// Sin middleware auth: la app aún no tiene login (igual que el resto de rutas)
Route::prefix('mtt-simulator/hand')->group(function () {
    Route::get('{hand}/state', [GamePlayController::class, 'getHandState'])->name('mtt-simulator.hand.state');
    Route::get('{hand}/log', [GamePlayController::class, 'logHistory'])->name('mtt-simulator.hand.log');
    Route::get('{hand}/history', [TournamentHistoryController::class, 'hand'])->name('mtt-simulator.hand.history');
    Route::get('{hand}/ranges/{player}', [GamePlayController::class, 'getPlayerRanges'])->name('mtt-simulator.hand.ranges');
    // Repetir una mano jugada con las mismas cartas y decisiones nuevas de los bots (ver HandReplayService)
    Route::get('{hand}/replays', [HandReplayController::class, 'show'])->name('mtt-simulator.hand.replays');
    Route::get('{hand}/replays/frames', [HandReplayController::class, 'frames'])->name('mtt-simulator.hand.replays.frames');
    Route::post('{hand}/replays', [HandReplayController::class, 'store'])->name('mtt-simulator.hand.replays.store');
    // Solo la ventana activa del torneo puede jugar (ver EnsureTournamentWindow)
    Route::middleware('tournament.window')->group(function () {
        Route::post('{hand}/action', [GamePlayController::class, 'playerAction'])->name('mtt-simulator.hand.action');
        Route::post('{hand}/bot-action', [GamePlayController::class, 'botAction'])->name('mtt-simulator.hand.bot-action');
        Route::post('{hand}/next', [GamePlayController::class, 'nextHand'])->name('mtt-simulator.hand.next');
    });
});

// Importación (PokerTracker 4: torneos MTT, manos, jugadores y estadísticas)
Route::get('/import', [ImportController::class, 'index'])->name('import.index');
Route::post('/import/upload', [ImportController::class, 'chunk'])->name('import.upload');
Route::get('/import/status/{run}', [ImportController::class, 'status'])->name('import.status');
Route::post('/import/simulate', [\App\Http\Controllers\SimulationController::class, 'run'])->name('import.simulate');

// Historial del torneo (todas las mesas, ronda a ronda)
Route::get('/mtt-simulator/{tournament}/history', [TournamentHistoryController::class, 'index'])->name('mtt-simulator.history');
Route::get('/mtt-simulator/{tournament}/history/export', [TournamentHistoryController::class, 'export'])->name('mtt-simulator.history.export');

// Resolución de mesas secundarias (una petición por mesa y ronda)
Route::post('/mtt-simulator/{tournament}/tables/{table}/resolve', [GamePlayController::class, 'resolveTable'])->middleware('tournament.window')->name('mtt-simulator.table.resolve');

// Una sola ventana a la vez por torneo: pedir el turno (o renovarlo) y liberarlo al cerrar
Route::post('/mtt-simulator/{tournament}/window', [TournamentWindowController::class, 'claim'])->name('mtt-simulator.window.claim');
Route::post('/mtt-simulator/{tournament}/window/release', [TournamentWindowController::class, 'release'])->name('mtt-simulator.window.release');

// Equity Simulation Routes
Route::prefix('equity')->group(function () {
    Route::get('/', [\App\Http\Controllers\EquitySimulationController::class, 'index'])->name('equity.index');
    Route::get('/simulator', [\App\Http\Controllers\EquitySimulationController::class, 'simulator'])->name('equity.simulator');

    // API endpoints
    Route::post('/api/simulate', [\App\Http\Controllers\EquitySimulationController::class, 'simulate'])->name('equity.api.simulate');
    Route::get('/api/range', [\App\Http\Controllers\EquitySimulationController::class, 'getRange'])->name('equity.api.range');
    Route::get('/api/check-hand', [\App\Http\Controllers\EquitySimulationController::class, 'checkHand'])->name('equity.api.check-hand');
    Route::post('/api/compare', [\App\Http\Controllers\EquitySimulationController::class, 'compare'])->name('equity.api.compare');
    Route::get('/api/status', [\App\Http\Controllers\EquitySimulationController::class, 'status'])->name('equity.api.status');
});

// API Routes
Route::prefix('api')->group(function () {
    Route::get('/bots', [BotController::class, 'api']);
    Route::get('/bots/{bot}', [BotController::class, 'apiShow']);
    Route::get('/bots/{bot}/preflop-ranges/{position}', [BotController::class, 'getPreflopRanges']);

    // Game Action Routes
    Route::post('/tournaments/{tournament}/hands/start', [GameActionController::class, 'startHand'])->name('api.hand.start');
    Route::post('/hands/{hand}/action', [GameActionController::class, 'recordAction'])->name('api.hand.action');
    Route::post('/hands/{hand}/next-street', [GameActionController::class, 'moveToNextStreet'])->name('api.hand.next-street');
    Route::post('/hands/{hand}/showdown', [GameActionController::class, 'evaluateShowdown'])->name('api.hand.showdown');
    Route::get('/hands/{hand}', [GameActionController::class, 'getHandState'])->name('api.hand.state');
});
