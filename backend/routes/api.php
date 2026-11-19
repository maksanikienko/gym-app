<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CycleController;
use App\Http\Controllers\Api\ExerciseController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\SetLogController;
use App\Http\Controllers\Api\WorkoutSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:30,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me', [AuthController::class, 'update']);
    Route::put('/me/password', [AuthController::class, 'updatePassword'])->middleware('throttle:6,1');
    Route::delete('/me', [AuthController::class, 'destroy']);

    Route::get('/exercises', [ExerciseController::class, 'index']);
    Route::post('/exercises', [ExerciseController::class, 'store']);
    Route::get('/exercises/{exercise}/history', [ExerciseController::class, 'history']);
    Route::get('/records', [ExerciseController::class, 'records']);

    Route::get('/programs', [ProgramController::class, 'index']);
    Route::post('/programs', [ProgramController::class, 'store']);
    Route::post('/programs/import', [ProgramController::class, 'import']);
    Route::put('/programs/{program}', [ProgramController::class, 'update']);
    Route::post('/programs/{program}/copy', [ProgramController::class, 'copy']);
    Route::get('/programs/{program}', [ProgramController::class, 'show']);
    Route::delete('/programs/{program}', [ProgramController::class, 'destroy']);

    Route::get('/cycles/current', [CycleController::class, 'current']);
    Route::post('/cycles', [CycleController::class, 'store']);
    Route::get('/cycles/{cycle}/summary', [CycleController::class, 'summary']);
    Route::post('/cycles/{cycle}/finish', [CycleController::class, 'finish']);
    Route::post('/cycles/{cycle}/skip-next', [CycleController::class, 'skipNext']);

    Route::get('/sessions', [WorkoutSessionController::class, 'index']);
    Route::post('/sessions', [WorkoutSessionController::class, 'store']);
    Route::get('/sessions/{session}', [WorkoutSessionController::class, 'show']);
    Route::patch('/sessions/{session}', [WorkoutSessionController::class, 'update']);
    Route::post('/sessions/{session}/finish', [WorkoutSessionController::class, 'finish']);
    Route::post('/sessions/{session}/skip', [WorkoutSessionController::class, 'skip']);
    Route::delete('/sessions/{session}', [WorkoutSessionController::class, 'destroy']);

    Route::post('/sessions/{session}/sets', [SetLogController::class, 'store']);
    Route::patch('/sets/{set}', [SetLogController::class, 'update']);
    Route::delete('/sets/{set}', [SetLogController::class, 'destroy']);
});
