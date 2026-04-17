<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReturnLogController;
use App\Http\Controllers\AIChatController;

// React student auth
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::post('ai/chat', [AIChatController::class, 'chat']);
    Route::post('ai/search-by-image', [AIChatController::class, 'searchByImage']);
    Route::post('items/{id}/claim', [ItemController::class, 'claim']);
});

// Existing routes
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('users', UserController::class);
Route::apiResource('items', ItemController::class);

Route::get('return-logs', [ReturnLogController::class, 'index']);
Route::get('return-logs/{id}', [ReturnLogController::class, 'show']);