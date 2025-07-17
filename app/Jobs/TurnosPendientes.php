<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Turno;
use App\Enums\TurnoEstado;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\CuentaCorriente;
use App\Models\Transaccion;
use App\Models\TurnoCancelacion;
use App\Notifications\ReservaNotification;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Configuracion;
use App\Models\Complejo;
use Illuminate\Support\Facades\Config;
use App\Jobs\SendTenantNotification;

class TurnosPendientes implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    protected $turnoId;
    protected $configuracionId;
    protected $subdominio;

    /**
     * Create a new job instance.
     */
    public function __construct($turnoId, $configuracionId = null, $subdominio)
    {
        $this->turnoId = $turnoId;
        $this->configuracionId = $configuracionId;
        $this->subdominio = $subdominio;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Iniciando el job TurnosPendientes para el turno #' . $this->turnoId . ' en el complejo: ' . $this->subdominio);
        $complejo = Complejo::where('subdominio', $this->subdominio)->first();

        if (!$complejo) {
            Log::error('Complejo con subdominio ' . $this->subdominio . ' no encontrado');
            return;
        }

        DB::purge('mysql_tenant');
        Config::set('database.connections.mysql_tenant.host', $complejo->db_host);
        Config::set('database.connections.mysql_tenant.database', $complejo->db_database);
        Config::set('database.connections.mysql_tenant.username', $complejo->db_username);
        Config::set('database.connections.mysql_tenant.password', $complejo->db_password);
        Config::set('database.connections.mysql_tenant.port', $complejo->db_port);
        Config::set('database.default', 'mysql_tenant');

        Log::info("Job TurnosPendientes: Ejecutando para el complejo '{$complejo->nombre}' (ID Turno: {$this->turnoId})");
        
        $turno = Turno::with(['persona', 'persona.cuentaCorriente', 'cancha'])->find($this->turnoId);

        if(!$turno) {
            Log::error('Turno #' . $this->turnoId . ' no encontrado');
            return;
        }

        if($turno->estado !== TurnoEstado::PENDIENTE) {
            Log::info('Turno #' . $this->turnoId . ' no está en estado pendiente');
            return;
        }

        DB::beginTransaction();

        try {
            $turnoActual = Turno::with(['persona', 'persona.cuentaCorriente', 'cancha'])->lockForUpdate()->find($this->turnoId);

            if (!$turnoActual || $turnoActual->estado !== TurnoEstado::PENDIENTE) {
                Log::error('Turno #' . $this->turnoId . ' no está en estado pendiente o no existe');
                DB::rollBack();
                return;
            }

            $fechaFormateada = Carbon::parse($turnoActual->fecha_turno)->format('Y-m-d');
            $clave = "bloqueo:{$fechaFormateada}:{$turnoActual->horario_id}:{$turnoActual->cancha_id}";
            Log::info('Borrando clave: ' . $clave); 
            Cache::forget($clave);

            $this->manejarDevolucionCuentaCorriente($turnoActual);

            $turnoActual->estado = TurnoEstado::CANCELADO;
            $turnoActual->save();

            TurnoCancelacion::create([
                'turno_id' => $turnoActual->id,
                'cancelado_por' => null,
                'motivo' => "Cancelación automática por falta de pago (30 minutos)",
                'fecha_cancelacion' => now(),	
            ]);

           
            // Usar directamente this->turnoId y this->configuracionId sin modificaciones
            Log::info('Enviando notificaciones para el turno #' . $this->turnoId . ' en el complejo: ' . $this->subdominio);
            
            if($turno->persona && $turno->persona->usuario) {
                SendTenantNotification::dispatch(
                    $this->subdominio,
                    $turno->persona->usuario->id,
                    ReservaNotification::class,
                    [$this->turnoId, 'cancelacion_automatica', $this->configuracionId]
                );
            }

            // Enviar notificaciones a los administradores
            $admins = User::where('rol', 'admin')->get();
            foreach ($admins as $admin) {
                SendTenantNotification::dispatch(
                    $this->subdominio,
                    $admin->id,
                    ReservaNotification::class,
                    [$this->turnoId, 'admin.cancelacion_automatica', $this->configuracionId]
                );
            }

            DB::commit();

            Log::info('Job TurnosPendientes para el turno #' . $this->turnoId . ' completado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en el job TurnosPendientes para el turno #' . $this->turnoId . ': ' . $e->getMessage());
            throw $e;
        }

        Log::info('Job TurnosPendientes para el turno #' . $this->turnoId . ' completado exitosamente');
    }

    private function manejarDevolucionCuentaCorriente(Turno $turno): void
    {
        if (!$turno->persona) {
            return;
        }

        $cuentaCorriente = CuentaCorriente::firstOrCreate(
            ['persona_id' => $turno->persona_id],
            ['saldo' => 0]
        );

        $montoDevolver = $turno->monto_total;

        Transaccion::create([
            'cuenta_corriente_id' => $cuentaCorriente->id,
            'turno_id' => $turno->id,
            'persona_id' => $turno->persona_id,
            'monto' => $montoDevolver,
            'tipo' => 'saldo',
            'descripcion' => "Ajuste de saldo por cancelación automática del turno #{$turno->id}",
        ]);

        $cuentaCorriente->saldo += $montoDevolver;
        $cuentaCorriente->save();
    }

    private function enviarNotificaciones($turno, $subdominio): void
    {
        try {
            // Si no se pasó el ID de configuración en el constructor, intentamos obtenerlo
            $configuracionId = $this->configuracionId;
            if (!$configuracionId) {
                $configuracion = Configuracion::first();
                if ($configuracion) {
                    $configuracionId = $configuracion->id;
                }
            }

            Log::info('Enviando notificaciones para el turno #' . $turno->id . ' en el complejo: ' . $subdominio);
            
            // DEBUGGING: Agregar logs para ver exactamente qué valores se están enviando
            Log::info('Datos para notificación - turnoId: ' . $turno->id . ' - tipo: cancelacion_automatica - configuracionId: ' . $configuracionId);
            
            if($turno->persona && $turno->persona->usuario) {
                // CORRECIÓN CRÍTICA: Convertir explícitamente el ID a string para evitar problemas de tipo
                $turnoIdString = (string)$turno->id;
                
                SendTenantNotification::dispatch(
                    $subdominio,
                    $turno->persona->usuario->id,
                    ReservaNotification::class,
                    [$turnoIdString, 'cancelacion_automatica', $configuracionId]
                );
            }

            // Enviar notificaciones a los administradores
            $admins = User::where('rol', 'admin')->get();
            foreach ($admins as $admin) {
                // CORRECIÓN CRÍTICA: Convertir explícitamente el ID a string para evitar problemas de tipo
                $turnoIdString = (string)$turno->id;
                
                SendTenantNotification::dispatch(
                    $subdominio,
                    $admin->id,
                    ReservaNotification::class,
                    [$turnoIdString, 'admin.cancelacion_automatica', $configuracionId]
                );
            }
        } catch (\Exception $e) {
            // Mejorar el log para capturar más información sobre el error
            Log::error('Error al enviar notificaciones: ' . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Error en el job TurnosPendientes para el turno #' . $this->turnoId . ': ' . $exception->getMessage());
    }
}
