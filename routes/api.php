<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\canchaController;
use App\Http\Controllers\reservaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\disponibilidadController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/canchas', [canchaController::class, 'index']);
Route::post('/canchas', [canchaController::class, 'store']);

Route::get('/reservas', [reservaController::class, 'index']);
Route::post('/reservas', [reservaController::class, 'store']);

Route::get('/disponibilidad', [disponibilidadController::class, 'getHorariosNoDisponibles']);

Route::post('/login', [AuthController::class, 'login']);
