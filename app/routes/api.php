<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GameController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('game')->group(function () {
    Route::get('{handId}/state', [GameController::class, 'getState']);
    Route::post('{handId}/action', [GameController::class, 'playerAction']);
    Route::get('{handId}/bot-action', [GameController::class, 'getBotAction']);
});
