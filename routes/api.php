<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CanchaController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\HorarioController;
use App\Http\Controllers\BloqueoTemporalController;
use App\Http\Controllers\DisponibilidadController;
use App\Http\Controllers\configController;
use App\Http\Controllers\UserController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/canchas', [CanchaController::class, 'index']);
    Route::post('/canchas', [CanchaController::class, 'store']);
    Route::patch('/canchas/{id}', [CanchaController::class, 'update']);
    Route::delete('/canchas/{id}', [CanchaController::class, 'destroy']);

    Route::get('/reservas', [ReservaController::class, 'index']);
    Route::post('/reservas', [ReservaController::class, 'store']);
    Route::patch('/reservas/{id}', [ReservaController::class, 'update']);
    Route::delete('/reservas/{id}', [ReservaController::class, 'destroy']);

    Route::post('/reservas/bloqueotemporal', [BloqueoTemporalController::class, 'bloquearHorario']);

    Route::get('/disponibilidad', [DisponibilidadController::class, 'getHorariosNoDisponibles']); 

    Route::get('/horarios', [HorarioController::class, 'index']);
    Route::post('/horarios', [HorarioController::class, 'store']);
    Route::delete('/horarios/{horario}', [HorarioController::class, 'destroy']);
    
    Route::post('/configurar-horarios', [configController::class, 'configurarHorarios']);   

    Route::post('/create-user', [UserController::class, 'createUser']);

});

Route::post('/login', [UserController::class, 'login']);
Route::post('/register', [UserController::class, 'register']);
