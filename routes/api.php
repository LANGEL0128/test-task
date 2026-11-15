<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use App\Models\Task;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

// Un id que no es un UUID es una petición incorrecta (400); un UUID inexistente es 404.
Route::bind('task', function (string $value) {
    if (! Str::isUuid($value)) {
        throw new BadRequestHttpException('El identificador de la tarea debe ser un UUID válido.');
    }

    return Task::findOrFail($value);
});

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
