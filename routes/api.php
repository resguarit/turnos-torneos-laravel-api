<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\canchaController;
use App\Http\Controllers\reservaController;
use App\Http\Controllers\horarioController;
use App\Http\Controllers\BloqueoTemporalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\disponibilidadController;
use App\Http\Controllers\configController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/canchas', [canchaController::class, 'index']);
Route::post('/canchas', [canchaController::class, 'store']);
Route::patch('/canchas/{id}', [canchaController::class, 'update']);
Route::delete('/canchas/{id}', [canchaController::class, 'destroy']);

Route::get('/reservas', [reservaController::class, 'index']);
Route::post('/reservas', [reservaController::class, 'store']);
Route::patch('/reservas/{id}', [reservaController::class, 'update']);
Route::delete('/reservas/{id}', [reservaController::class, 'destroy']);


Route::post('/reservas/bloqueotemporal', [BloqueoTemporalController::class, 'bloquearHorario']);

Route::get('/disponibilidad', [disponibilidadController::class, 'getHorariosNoDisponibles']);
Route::get('/disponibilidad/fecha', [disponibilidadController::class, 'getHorariosNoDisponiblesPorFecha']);

Route::get('/horarios', [horarioController::class, 'index']);
Route::post('/horarios', [horarioController::class, 'store']);
Route::delete('/horarios/{horario}', [horarioController::class, 'destroy']);

Route::post('/login',[AuthController::class, 'login']); //falta register

Route::post('/configurar-horarios', [configController::class, 'configurarHorarios']);   
