<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CanchaController;
use App\Http\Controllers\Api\HorarioController;
use App\Http\Controllers\Api\BloqueoTemporalController;
use App\Http\Controllers\Api\DisponibilidadController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Api\TurnoController;
use App\Http\Controllers\Api\AuditoriaController;
use App\Http\Controllers\Api\DeporteController;
use App\Http\Controllers\Api\TorneoController;
use App\Http\Controllers\Api\EquipoController;
use App\Http\Controllers\Api\JugadorController;
use App\Http\Controllers\Api\ZonaController;
use App\Http\Controllers\Api\FechaController;
use App\Http\Controllers\Api\PartidoController;
use App\Http\Controllers\Api\EstadisticaController;
use App\Http\Controllers\Api\GrupoController;

Route::post('/login', [UserController::class, 'login']);
Route::post('/register', [UserController::class, 'register']);

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
    Route::post('/turnos/cancelar/{id}', [TurnoController::class, 'cancel']);
    Route::get('/disponibilidad/turnos-fijos', [DisponibilidadController::class, 'getHorariosDisponiblesTurnosFijos']);

    Route::get('/grilla', [TurnoController::class, 'grid']);

    Route::post('/turnos/bloqueotemporal', [BloqueoTemporalController::class, 'bloquearHorario']);
    Route::post('/turnos/cancelarbloqueo', [BloqueoTemporalController::class, 'cancelarBloqueo']);
    Route::get('/turnos/listarbloqueos', [BloqueoTemporalController::class, 'listarBloqueos']);

    Route::get('/horarios', [HorarioController::class, 'index']);
    Route::post('/horarios', [HorarioController::class, 'store']);
    Route::delete('/horarios/{horario}', [HorarioController::class, 'destroy']);
    Route::get('/horarios-extremos-activos', [HorarioController::class, 'getHorariosExtremosActivos']);

    
    Route::post('/configurar-horarios', [ConfigController::class, 'configurarHorarios']);
    Route::put('/deshabilitar-franja-horaria', [HorarioController::class, 'deshabilitarFranjaHoraria']);
    Route::put('/habilitar-franja-horaria', [HorarioController::class, 'habilitarFranjaHoraria']);
    Route::get('/franjas-horarias-no-disponibles', [HorarioController::class, 'showFranjasHorariasNoDisponibles']);

    Route::get('/usuarios', [UserController::class, 'index']);
    Route::get('/usuarios/{id}', [UserController::class, 'show']);
    Route::patch('/usuarios/{id}', [UserController::class, 'update']);
    Route::delete('/usuarios/{id}', [UserController::class, 'destroy']);
    Route::post('/create-user', [UserController::class, 'createUser']);
    Route::post('/logout', [UserController::class, 'logout']);
    
    Route::get('/auditorias', [AuditoriaController::class, 'index']);

    Route::get('/deportes', [DeporteController::class, 'index']);
    Route::get('/deportes/{id}', [DeporteController::class, 'show']);
    Route::post('/deportes', [DeporteController::class, 'store']);
    Route::put('/deportes/{id}', [DeporteController::class, 'update']);
    Route::delete('/deportes/{id}', [DeporteController::class, 'destroy']);

    Route::get('/torneos', [TorneoController::class, 'index']);
    Route::get('/torneos/{id}', [TorneoController::class, 'show']);
    Route::post('/torneos', [TorneoController::class, 'store']);
    Route::put('/torneos/{id}', [TorneoController::class, 'update']);
    Route::delete('/torneos/{id}', [TorneoController::class, 'destroy']);

    Route::get('/equipos', [EquipoController::class, 'index']);
    Route::get('/equipos/{id}', [EquipoController::class, 'show']);
    Route::post('/equipos', [EquipoController::class, 'store']);
    Route::put('/equipos/{id}', [EquipoController::class, 'update']);
    Route::delete('/equipos/{id}', [EquipoController::class, 'destroy']);
    Route::get('/zonas/{zonaId}/equipos', [EquipoController::class, 'getByZona']);

    Route::get('/jugadores', [JugadorController::class, 'index']);
    Route::get('/jugadores/{id}', [JugadorController::class, 'show']);
    Route::post('/jugadores', [JugadorController::class, 'store']);
    Route::put('/jugadores/{id}', [JugadorController::class, 'update']);
    Route::delete('/jugadores/{id}', [JugadorController::class, 'destroy']);
    Route::get('/equipos/{equipoId}/jugadores', [JugadorController::class, 'getByEquipo']);
    Route::post('/equipos/{equipoId}/jugadores/multiple', [JugadorController::class, 'createMultiple']);

    Route::get('/zonas', [ZonaController::class, 'index']);
    Route::get('/zonas/{id}', [ZonaController::class, 'show']);
    Route::post('/zonas', [ZonaController::class, 'store']);
    Route::put('/zonas/{id}', [ZonaController::class, 'update']);
    Route::delete('/zonas/{id}', [ZonaController::class, 'destroy']);
    Route::get('/torneos/{torneoId}/zonas', [ZonaController::class, 'getByTorneo']);
    Route::post('/zonas/{zonaId}/fechas', [ZonaController::class, 'createFechas']);

    Route::get('/fechas', [FechaController::class, 'index']);
    Route::get('/fechas/{id}', [FechaController::class, 'show']);
    Route::post('/fechas', [FechaController::class, 'store']);
    Route::put('/fechas/{id}', [FechaController::class, 'update']);
    Route::delete('/fechas/{id}', [FechaController::class, 'destroy']);
    Route::get('/zonas/{zonaId}/fechas', [FechaController::class, 'getByZona']);
    Route::post('/fechas/{fechaId}/postergar', [FechaController::class, 'postergarFechas']);

    Route::get('/partidos', [PartidoController::class, 'index']);
    Route::get('/partidos/{id}', [PartidoController::class, 'show']);
    Route::post('/partidos', [PartidoController::class, 'store']);
    Route::put('/partidos/{id}', [PartidoController::class, 'update']);
    Route::delete('/partidos/{id}', [PartidoController::class, 'destroy']);
    Route::get('/fechas/{fechaId}/partidos', [PartidoController::class, 'getByFecha']);
    Route::get('/equipos/{equipoId}/partidos', [PartidoController::class, 'getByEquipo']);

    Route::get('/estadisticas', [EstadisticaController::class, 'index']);
    Route::get('/estadisticas/{id}', [EstadisticaController::class, 'show']);
    Route::post('/estadisticas', [EstadisticaController::class, 'store']);
    Route::put('/estadisticas/{id}', [EstadisticaController::class, 'update']);
    Route::delete('/estadisticas/{id}', [EstadisticaController::class, 'destroy']);
    Route::get('/partidos/{partidoId}/estadisticas', [EstadisticaController::class, 'getByPartido']);
    Route::get('/equipos/{equipoId}/estadisticas', [EstadisticaController::class, 'getByEquipo']);
    Route::get('/jugadores/{jugadorId}/estadisticas', [EstadisticaController::class, 'getByJugador']);
    Route::get('/zonas/{zonaId}/estadisticas', [EstadisticaController::class, 'getByZona']);

    Route::get('/grupos', [GrupoController::class, 'index']);
    Route::get('/grupos/{id}', [GrupoController::class, 'show']);
    Route::post('/grupos', [GrupoController::class, 'store']);
    Route::put('/grupos/{id}', [GrupoController::class, 'update']);
    Route::delete('/grupos/{id}', [GrupoController::class, 'destroy']);
    Route::get('/zonas/{zonaId}/grupos', [GrupoController::class, 'getByZona']);

    // Ruta temporal para probar la creación de grupos aleatoriamente
    Route::post('/zonas/{zonaId}/crear-grupos', [ZonaController::class, 'crearGruposAleatoriamente']);

}); 

Route::get('/disponibilidad', [DisponibilidadController::class, 'getHorariosNoDisponibles']);
Route::get('/disponibilidad/dias', [DisponibilidadController::class, 'getDiasNoDisponibles']);
Route::get('/disponibilidad/fecha', [DisponibilidadController::class, 'getHorariosDisponiblesPorFecha']);
Route::get('/disponibilidad/cancha', [DisponibilidadController::class, 'getCanchasPorHorarioFecha']);


Route::get('/horarios/{id}', [HorarioController::class, 'show']);
Route::get('/horarios-dia', [HorarioController::class, 'getPorDiaSemana']);
Route::get('/canchas/{id}', [CanchaController::class, 'show']);

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle']);
Route::post('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);

