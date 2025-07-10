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
use App\Http\Controllers\Api\DeporteController;
use App\Http\Controllers\Api\TorneoController;
use App\Http\Controllers\Api\EquipoController;
use App\Http\Controllers\Api\JugadorController;
use App\Http\Controllers\Api\ZonaController;
use App\Http\Controllers\Api\FechaController;
use App\Http\Controllers\Api\PartidoController;
use App\Http\Controllers\Api\EstadisticaController;
use App\Http\Controllers\Api\GrupoController;
use App\Http\Controllers\Api\PersonaController;
use App\Http\Controllers\Api\CuentaCorrienteController;
use App\Http\Controllers\Api\TransaccionesController;
use App\Http\Controllers\Api\MetodoPagoController;
use App\Http\Controllers\Api\CajaController;
use App\Http\Controllers\Api\TransaccionController;
use App\Http\Controllers\Api\SancionController;

use App\Http\Controllers\Api\AuditoriaController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\EventoController;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Checkout\MercadoPagoController;
use App\Http\Controllers\Webhook\MercadoPagoWebhook;
use App\Http\Controllers\Api\BloqueoDisponibilidadController;
use App\Http\Controllers\TipoGastoController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\Configuracion\ConfiguracionController;
use App\Http\Controllers\Api\PenalController;
use App\Http\Controllers\Api\DescuentoController;

Route::post('/login', [UserController::class, 'login']);
Route::post('/register', [UserController::class, 'register']);
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
Route::post('/verify-email', [VerifyEmailController::class, 'verifyEmail']);

// Rutas de MercadoPago públicas (no requieren autenticación)
Route::post('/mercadopago/webhook/{subdominio}', [MercadoPagoWebhook::class, 'handleWebhook']);
Route::post('/mercadopago/verify-payment', [MercadoPagoController::class, 'verifyPaymentStatus']);
Route::post('/mercadopago/verify-payment-by-preference', [MercadoPagoController::class, 'verifyPaymentStatusByPreference']);    

Route::get('/configuracion-usuario', [ConfiguracionController::class, 'ObtenerConfiguracion']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/configuracion-update', [ConfiguracionController::class, 'actualizarConfiguracion']);

    Route::post('/descuentos', [DescuentoController::class, 'storeBatch']);
    Route::get('/descuentos', [DescuentoController::class, 'getDescuentos']);
    Route::delete('/descuentos/{id}', [DescuentoController::class, 'destroy']);

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

    Route::post('/bloquear-disponibilidad', [BloqueoDisponibilidadController::class, 'bloquearDisponibilidad']);
    Route::post('/desbloquear-disponibilidad', [BloqueoDisponibilidadController::class, 'desbloquearDisponibilidad']);
    Route::get('/bloqueados', [BloqueoDisponibilidadController::class, 'getAll']);
    Route::delete('/bloqueados/{id}', [BloqueoDisponibilidadController::class, 'destroy']);

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
     
    Route::get('/horarios', [HorarioController::class, 'index']);
    Route::get('/turnos/listarbloqueos', [BloqueoTemporalController::class, 'listarBloqueos']);


    Route::post('/horarios', [HorarioController::class, 'store']);
    Route::delete('/horarios/{horario}', [HorarioController::class, 'destroy']);
    Route::get('/horarios-extremos-activos', [HorarioController::class, 'getHorariosExtremosActivos']);

    
    Route::post('/configurar-horarios', [ConfigController::class, 'configurarHorarios']);
    Route::put('/deshabilitar-franja-horaria', [HorarioController::class, 'deshabilitarFranjaHoraria']);
    Route::put('/habilitar-franja-horaria', [HorarioController::class, 'habilitarFranjaHoraria']);
    Route::get('/franjas-horarias-no-disponibles', [HorarioController::class, 'showFranjasHorariasNoDisponibles']);
    Route::post('/config/horario-semana-completa', [ConfigController::class, 'setHorarioSemanaCompleta']);


    Route::get('/usuarios', [UserController::class, 'index']);
    Route::get('/usuarios/{id}', [UserController::class, 'show']);
    Route::patch('/usuarios/{id}', [UserController::class, 'update']);
    Route::delete('/usuarios/{id}', [UserController::class, 'destroy']);
    Route::post('/create-user', [UserController::class, 'createUser']);
    Route::post('/logout', [UserController::class, 'logout']);

    Route::post('/apertura-caja', [CajaController::class, 'abrirCaja']);
    Route::get('/caja-abierta', [CajaController::class, 'getCaja']);
    Route::get('/cajas', [CajaController::class, 'index']);
    Route::post('/cierre-caja', [CajaController::class, 'cerrarCaja']);

    Route::get('/auditorias', [AuditoriaController::class, 'index']);
    Route::get('/auditoria/tipos-accion', [AuditoriaController::class, 'tiposDeAccion']);

    Route::post('/deportes', [DeporteController::class, 'store']);
    Route::put('/deportes/{id}', [DeporteController::class, 'update']);
    Route::delete('/deportes/{id}', [DeporteController::class, 'destroy']);

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
    Route::get('/equipos/exclude-zona/{zonaId}', [EquipoController::class, 'getExcludeZona']);
    Route::get('/equipos/dos/{id1}/{id2}', [EquipoController::class, 'getDosEquiposPorId']);


    Route::get('/jugadores', [JugadorController::class, 'index']);
    Route::get('/jugadores/{id}', [JugadorController::class, 'show']);
    Route::post('/jugadores', [JugadorController::class, 'store']);
    Route::put('/jugadores/{id}', [JugadorController::class, 'update']);
    Route::delete('/jugadores/{id}', [JugadorController::class, 'destroy']);
    Route::get('/jugadores/search/dni', [JugadorController::class, 'searchByDni']);
    Route::post('/jugadores/asociar-a-equipo', [JugadorController::class, 'asociarJugadorAEquipo']);
    Route::get('/equipos/{equipoId}/jugadores', [JugadorController::class, 'getByEquipo']);
    Route::post('/equipos/{equipoId}/jugadores/multiple', [JugadorController::class, 'createMultiple']);
    Route::post('/jugadores/multiple-sin-equipo', [JugadorController::class, 'createMultipleSinEquipo']);
    Route::get('/jugadores/info-por-dni/{dni}', [JugadorController::class, 'infoPorDni']);
    Route::get('/equipos/{equipoId}/jugadores/{jugadorId}/equipo-jugador-id', [JugadorController::class, 'getEquipoJugadorId']);
    Route::post('equipos/{equipoId}/jugadores/{jugadorId}/desvincular-jugador', [EquipoController::class, 'desvincularJugadorDeEquipo']);
    Route::post('jugadores/crear-persona-cuenta-si-capitan', [JugadorController::class, 'crearPersonaYCuentaCorrienteSiCapitan']);
    Route::post('jugadores/cambiar-capitan', [JugadorController::class, 'cambiarCapitan']);

    Route::prefix('zonas')->group(function () {
        Route::get('/', [ZonaController::class, 'index']);

        Route::post('/', [ZonaController::class, 'store']);
        Route::put('/{id}', [ZonaController::class, 'update']);
        Route::delete('/{id}', [ZonaController::class, 'destroy']);
        Route::get('/torneo/{torneoId}', [ZonaController::class, 'getByTorneo']);
        Route::post('/{zonaId}/fechas', [ZonaController::class, 'createFechas']);
        Route::post('/{zonaId}/grupos-aleatorios', [ZonaController::class, 'crearGruposAleatoriamente']);
        Route::post('/{zonaId}/reemplazar-equipo', [ZonaController::class, 'reemplazarEquipo']);
        Route::post('/{zonaId}/siguiente-ronda', [ZonaController::class, 'generarSiguienteRonda']);
        Route::post('/{zonaId}/playoff', [ZonaController::class, 'crearPlayoff']);
        Route::post('/{zonaId}/equipos', [ZonaController::class, 'agregarEquipos']);
        Route::delete('/{zonaId}/equipos', [ZonaController::class, 'quitarEquipos']);
        Route::post('/{zonaId}/crear-playoff-en-liga', [ZonaController::class, 'crearPlayoffEnLiga']);
        Route::post('/{zonaId}/crear-playoff-en-grupos', [ZonaController::class, 'crearPlayoffEnGrupos']);
    });

    Route::get('/fechas', [FechaController::class, 'index']);
    Route::get('/fechas/{id}', [FechaController::class, 'show']);
    Route::post('/fechas', [FechaController::class, 'store']);
    Route::put('/fechas/{id}', [FechaController::class, 'update']);
    Route::delete('/fechas/{id}', [FechaController::class, 'destroy']);
    Route::delete('/fechas', [FechaController::class, 'destroyMultiple']);
    Route::post('/fechas/{fechaId}/postergar', [FechaController::class, 'postergarFechas']);
    Route::post('/fechas/{fechaId}/verificar-estado', [FechaController::class, 'verificarEstadoFecha']);

    Route::get('/partidos', [PartidoController::class, 'index']);
    Route::get('/partidos/{id}', [PartidoController::class, 'show']);
    Route::post('/partidos', [PartidoController::class, 'store']);
    Route::put('/partidos/{id}', [PartidoController::class, 'update']);
    Route::delete('/partidos/{id}', [PartidoController::class, 'destroy']);

    Route::get('/equipos/{equipoId}/zona/{zonaId}/partidos', [PartidoController::class, 'getByEquipoAndZona']);
    //Route::get('/zonas/{zonaId}/equipos/{equipoId}/partidos', [PartidoController::class, 'getByEquipoAndZona']);
    /* Route::post('/partidos/asignar-hora-cancha', [PartidoController::class, 'asignarHoraYCancha']); */

    Route::get('/estadisticas', [EstadisticaController::class, 'index']);
    Route::get('/estadisticas/{id}', [EstadisticaController::class, 'show']);
    Route::post('/estadisticas', [EstadisticaController::class, 'store']);
    Route::put('/estadisticas/{id}', [EstadisticaController::class, 'update']);
    Route::delete('/estadisticas/{id}', [EstadisticaController::class, 'destroy']);
    Route::get('/partidos/{partidoId}/estadisticas', [EstadisticaController::class, 'getByPartido']);
    Route::post('/partidos/{partidoId}/estadisticas/multiple', [EstadisticaController::class, 'createOrUpdateMultiple']);
    Route::get('/equipos/{equipoId}/estadisticas', [EstadisticaController::class, 'getByEquipo']);
    Route::get('/jugadores/{jugadorId}/estadisticas', [EstadisticaController::class, 'getByJugador']);
    Route::get('/zonas/{zonaId}/estadisticas', [EstadisticaController::class, 'getByZona']);

    Route::get('/metodos-pago', [MetodoPagoController::class, 'index']);
    Route::post('/metodos-pago', [MetodoPagoController::class, 'store']);
    Route::put('/metodos-pago/{id}', [MetodoPagoController::class, 'update']);
    Route::delete('/metodos-pago/{id}', [MetodoPagoController::class, 'destroy']);


    Route::get('/grupos', [GrupoController::class, 'index']);
    Route::get('/grupos/{id}', [GrupoController::class, 'show']);
    Route::post('/grupos', [GrupoController::class, 'store']);
    Route::put('/grupos/{id}', [GrupoController::class, 'update']);
    Route::delete('/grupos/{id}', [GrupoController::class, 'destroy']);
    Route::delete('/grupos/{grupoId}/equipos/{equipoId}', [GrupoController::class, 'eliminarEquipoDeGrupo']);
    Route::delete('/zonas/{zonaId}/eliminar-grupos-zona', [GrupoController::class, 'eliminarGruposDeZona']);
    Route::get('/zonas/{zonaId}/grupos', [GrupoController::class, 'getByZona']);
    Route::post('/zonas/{zonaId}/crear-grupos', [ZonaController::class, 'crearGruposAleatoriamente']);
    Route::post('/zonas/{zonaId}/asignar-hora-cancha', [PartidoController::class, 'asignarHoraYCanchaPorZona']);
    Route::post('/zona/{zonaId}/generar-siguiente-ronda', [ZonaController::class, 'generarSiguienteRonda']);
    Route::post('/zonas/{zonaId}/crear-playoff', [ZonaController::class, 'crearPlayoff']);
    Route::post('/grupos/{grupoId}/equipos/{equipoId}', [GrupoController::class, 'agregarEquipoAGrupo']);
    Route::put('/grupos/{grupoId}/equipos', [GrupoController::class, 'actualizarEquiposDeGrupo']);
    

    Route::get('/auditorias', [AuditoriaController::class, 'index']);

    Route::post('/equipos/{equipoId}/torneos/{torneoId}/pagar-inscripcion', [PagoController::class, 'registrarPagoInscripcion']);
    Route::post('/fechas/{fechaId}/pagar', [PagoController::class, 'registrarPagoPorFecha']);
    Route::get('/equipos/{equipoId}/torneos/{torneoId}/pago-inscripcion', [PagoController::class, 'obtenerPagoInscripcion']);
    Route::get('/equipos/{equipoId}/zonas/{zonaId}/pago-fecha', [PagoController::class, 'obtenerPagoPorFecha']);
    Route::get('/equipos/{equipoId}/torneos/{torneoId}/zonas/{zonaId}/pagos', [PagoController::class, 'obtenerPagosEquipoTorneo']);
    
    Route::delete('/personas/{id}', [PersonaController::class, 'destroy']);

    Route::post('pago/inscripcion/{equipoId}/{torneoId}/{metodoPagoId}', [PagoController::class, 'registrarPagoInscripcion']);
    Route::post('pago/fecha/{fechaId}/{metodoPagoId}', [PagoController::class, 'registrarPagoPorFecha']);
    Route::post('pago/evento/{id}/{metodoPagoId}', [PagoController::class, 'registrarPagoEvento']);

    Route::post('/sanciones', [SancionController::class, 'store']);
    Route::get('/sanciones/{id}', [SancionController::class, 'show']);
    Route::delete('/sanciones/{id}', [SancionController::class, 'destroy']);
    Route::put('/sanciones/{id}', [SancionController::class, 'updateSancion']);
    Route::get('/expulsiones-permanentes', [SancionController::class, 'getExpulsionesPermanentes']);

    Route::get('eventos', [EventoController::class, 'index']);
    Route::get('eventos/{id}', [EventoController::class, 'show']);
    Route::post('eventos', [EventoController::class, 'store']);
    Route::put('eventos/{id}', [EventoController::class, 'update']);
    Route::delete('eventos/{id}', [EventoController::class, 'destroy']);
    Route::get('eventosComoTurnos', [EventoController::class, 'eventosComoTurnos']);
    Route::get('estadoPago/eventos', [EventoController::class, 'obtenerEstadosPagoEventos']);

    Route::get('/penales', [PenalController::class, 'index']);
    Route::get('/penales/{id}', [PenalController::class, 'show']);
    Route::post('/penales', [PenalController::class, 'store']);
    Route::put('/penales/{id}', [PenalController::class, 'update']);
    Route::delete('/penales/{id}', [PenalController::class, 'destroy']);
    Route::get('/penales/partido/{partidoId}', [PenalController::class, 'getByPartido']);

    // Rutas que requieren verificación de MercadoPago habilitado
    Route::middleware(['check.mercadopago'])->group(function () {
        Route::post('/mercadopago/create-preference', [MercadoPagoController::class, 'createPreference']);
    });
});

Route::get('/disponibilidad', [DisponibilidadController::class, 'getHorariosNoDisponibles']);
Route::get('/disponibilidad/dias', [DisponibilidadController::class, 'getDiasNoDisponibles']);
Route::get('/disponibilidad/fecha', [DisponibilidadController::class, 'getHorariosDisponiblesPorFecha']);
Route::get('/disponibilidad/cancha', [DisponibilidadController::class, 'getCanchasPorHorarioFecha']);

Route::get('/personas', [PersonaController::class, 'index']);
Route::post('/personas', [PersonaController::class, 'store']);
Route::patch('/personas/{id}', [PersonaController::class, 'update']);

Route::get('/cuentas-corrientes', [CuentaCorrienteController::class, 'index']);
Route::get('/cuentas-corrientes/persona/{id}', [CuentaCorrienteController::class, 'show']);

Route::get('/transacciones', [TransaccionesController::class, 'index']);
Route::post('/transacciones', [TransaccionesController::class, 'store']);
Route::get('/transacciones/turno/{id}', [TransaccionesController::class, 'saldoPorTurno']);
Route::get('/transacciones/caja/{cajaId}', [TransaccionesController::class, 'getTransaccionesPorCaja']);

Route::get('/horarios/{id}', [HorarioController::class, 'show']);
Route::get('/horarios', [HorarioController::class, 'index']);
Route::get('/horarios-dia', [HorarioController::class, 'getHorariosPorDiaSemana']);
Route::get('/canchas/{id}', [CanchaController::class, 'show']);

Route::get('/deportes', [DeporteController::class, 'index']);
Route::get('/deportes/{id}', [DeporteController::class, 'show']);

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle']);
Route::post('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);


Route::get('/torneos', [TorneoController::class, 'index']);

Route::get('/torneos/{torneoId}/zonas', [ZonaController::class, 'getByTorneo']);
Route::get('/partidos/zona/{zonaId}', [PartidoController::class, 'getByZona']);
Route::get('/jugadores/zona/{zonaId}', [JugadorController::class, 'getByZona']);
Route::get('/zonas/{zonaId}/estadisticas', [EstadisticaController::class, 'getByZona']);
Route::get('/zonas/{zonaId}/fechas', [FechaController::class, 'getByZona']);
Route::get('/zonas/{zonaId}/equipos', [EquipoController::class, 'getByZona']);
Route::get('/fechas/{fechaId}/partidos', [PartidoController::class, 'getByFecha']);
Route::get('/zonas/{zonaId}/estadisticas-grupos', [ZonaController::class, 'obtenerEstadisticasGrupos']);
Route::get('/zonas/{zonaId}/estadisticas-liga', [ZonaController::class, 'obtenerEstadisticasLiga']);
Route::get('/zonas/{zonaId}/estadisticas/jugadores', [EstadisticaController::class, 'getJugadoresStatsByZona']);
Route::get('/zonas/{id}', [ZonaController::class, 'show']);

Route::get('/zonas/{zonaId}/sanciones', [SancionController::class, 'getSancionesPorZona']);

// Rutas para tipos de gasto
Route::get('/tipos-gasto', [TipoGastoController::class, 'index']);
Route::post('/tipos-gasto', [TipoGastoController::class, 'store']);
Route::put('/tipos-gasto/{id}', [TipoGastoController::class, 'update']);
Route::delete('/tipos-gasto/{id}', [TipoGastoController::class, 'destroy']);

// Ruta para balance entre fechas
Route::get('/balance', [BalanceController::class, 'getBalance']);

Route::get('/test-mercadopago', function () {
    $enabled = \App\Services\MercadoPagoConfigService::isEnabled();
    $token = \App\Services\MercadoPagoConfigService::getAccessToken();
    $webhookSecret = \App\Services\MercadoPagoConfigService::getWebhookSecret();
    
    return response()->json([
        'mercadopago_enabled' => $enabled,
        'token_masked' => $token ? substr($token, 0, 4) . '...' . substr($token, -4) : null,
        'webhook_secret_masked' => $webhookSecret ? substr($webhookSecret, 0, 4) . '...' . substr($webhookSecret, -4) : null
    ]);
});



