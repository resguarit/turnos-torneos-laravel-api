<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CanchaController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\HorarioController;
use App\Http\Controllers\BloqueoTemporalController;
use App\Http\Controllers\DisponibilidadController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\UserController;

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/canchas', [CanchaController::class, 'index']);
    Route::get('/canchas/{id}', [CanchaController::class, 'show']);
    Route::post('/canchas', [CanchaController::class, 'store']);
    Route::patch('/canchas/{id}', [CanchaController::class, 'update']);
    Route::delete('/canchas/{id}', [CanchaController::class, 'destroy']);

    Route::get('/reservas', [reservaController::class, 'index']);
    Route::get('/reservas-all', [reservaController::class, 'getAll']);
    Route::post('/reservas', [reservaController::class, 'store']);
    Route::patch('/reservas/{id}', [reservaController::class, 'update']);
    Route::delete('/reservas/{id}', [reservaController::class, 'destroy']);

    Route::post('/reservas/bloqueotemporal', [BloqueoTemporalController::class, 'bloquearHorarios']);

    Route::get('/disponibilidad', [DisponibilidadController::class, 'getHorariosNoDisponibles']); 
    Route::get('/disponibilidad/fecha', [DisponibilidadController::class, 'getHorariosDisponiblesPorFecha']);
    Route::get('/disponibilidad/cancha', [DisponibilidadController::class, 'getCanchasPorHorarioFecha']);

    Route::get('/horarios', [HorarioController::class, 'index']);
    Route::get('/horarios/{id}', [HorarioController::class, 'show']);
    Route::post('/horarios', [HorarioController::class, 'store']);
    Route::delete('/horarios/{horario}', [HorarioController::class, 'destroy']);
    
    Route::post('/configurar-horarios', [ConfigController::class, 'configurarHorarios']);   

    Route::get('/usuarios', [UserController::class, 'index']);
    Route::get('/usuarios/{id}', [UserController::class, 'show']);
    Route::patch('/usuarios/{id}', [UserController::class, 'update']);
    Route::post('/create-user', [UserController::class, 'createUser']);

});

Route::post('/login', [UserController::class, 'login']);
Route::post('/register', [UserController::class, 'register']);
