<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Lectura pública
Route::apiResource('tasks', TaskController::class)->only(['index', 'show']);

// Escritura: solo super_admin
Route::middleware(['auth:api', 'role:super_admin'])->group(function () {
    Route::apiResource('tasks', TaskController::class)->only(['store', 'update', 'destroy']);
});
