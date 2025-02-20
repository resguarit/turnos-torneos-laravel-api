<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CanchaController;
use App\Http\Controllers\HorarioController;
use App\Http\Controllers\BloqueoTemporalController;
use App\Http\Controllers\DisponibilidadController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\TurnoController;



Route::middleware(['auth:sanctum'])->group(function () {
    
    Route::get('/canchas', [CanchaController::class, 'index']);

    Route::post('/canchas', [CanchaController::class, 'store']);
    Route::patch('/canchas/{id}', [CanchaController::class, 'update']);
    Route::delete('/canchas/{id}', [CanchaController::class, 'destroy']);

    Route::get('/dashboard/total-reservas', [DashboardController::class, 'totalReservas']);
    Route::get('/dashboard/usuarios-activos', [DashboardController::class, 'usuariosActivos']);
    Route::get('/dashboard/ingresos', [DashboardController::class, 'ingresos']);
    Route::get('/dashboard/tasa-ocupacion', [DashboardController::class, 'tasaOcupacion']);
    Route::get('/dashboard/cancha-mas-popular', [DashboardController::class, 'canchaMasPopular']);
    Route::get('/dashboard/horas-pico', [DashboardController::class, 'horasPico']);
    Route::get('/dashboard/reservas-por-mes', [DashboardController::class, 'reservasPorMes']);

    Route::get('/turnos', [TurnoController::class, 'index']);
    Route::get('/turnos-all', [TurnoController::class, 'getAll']);
    Route::get('turnos/user/{id?}', [TurnoController::class, 'getTurnosByUser']);
    Route::get('turnos/user-proximos', [TurnoController::class, 'getProximos']);
    Route::get('/turnos/{id}', [TurnoController::class, 'show']);
    Route::post('/turnos/turnounico', [TurnoController::class, 'storeTurnoUnico']);
    Route::post('/turnos/turnofijo', [TurnoController::class, 'storeTurnoFijo']);
    Route::patch('/turnos/{id}', [TurnoController::class, 'update']);
    Route::delete('/turnos/{id}', [TurnoController::class, 'destroy']);
    Route::put('/turnos/cancelar/{id}', [TurnoController::class, 'cancel']);

    Route::get('/grilla', [TurnoController::class, 'grid']);

    Route::post('/turnos/bloqueotemporal', [BloqueoTemporalController::class, 'bloquearHorario']);
    Route::delete('/turnos/cancelarbloqueo/{id}', [BloqueoTemporalController::class, 'cancelarBloqueo']);

    Route::get('/horarios', [HorarioController::class, 'index']);
    Route::post('/horarios', [HorarioController::class, 'store']);
    Route::delete('/horarios/{horario}', [HorarioController::class, 'destroy']);
    
    Route::post('/configurar-horarios', [ConfigController::class, 'configurarHorarios']);
    Route::put('/deshabilitar-franja-horaria', [HorarioController::class, 'deshabilitarFranjaHoraria']);
    Route::put('/habilitar-franja-horaria', [HorarioController::class, 'habilitarFranjaHoraria']);
    Route::get('/franjas-horarias-no-disponibles', [HorarioController::class, 'showFranjasHorariasNoDisponibles']);

    Route::get('/usuarios', [UserController::class, 'index']);
    Route::get('/usuarios/{id}', [UserController::class, 'show']);
    Route::patch('/usuarios/{id}', [UserController::class, 'update']);
    Route::post('/create-user', [UserController::class, 'createUser']);
    Route::post('/logout', [UserController::class, 'logout']);

    Route::post('/turnos/turnounico', [TurnoController::class, 'storeTurnoUnico']);

}); 

Route::post('/login', [UserController::class, 'login']);
Route::post('/register', [UserController::class, 'register']);

Route::get('/disponibilidad', [DisponibilidadController::class, 'getHorariosNoDisponibles']);
Route::get('/disponibilidad/dias', [DisponibilidadController::class, 'getDiasNoDisponibles']);
Route::get('/disponibilidad/fecha', [DisponibilidadController::class, 'getHorariosDisponiblesPorFecha']);
Route::get('/disponibilidad/cancha', [DisponibilidadController::class, 'getCanchasPorHorarioFecha']);

Route::get('/horarios/{id}', [HorarioController::class, 'show']);
Route::get('/horarios-dia', [HorarioController::class, 'getPorDiaSemana']);
Route::get('/canchas/{id}', [CanchaController::class, 'show']);

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle']);
Route::post('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
Route::get('/horarios-extremos-activos', [HorarioController::class, 'getHorariosExtremosActivos']);

