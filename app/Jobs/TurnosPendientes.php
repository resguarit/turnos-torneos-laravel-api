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

class TurnosPendientes implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    protected $turnoId;

    /**
     * Create a new job instance.
     */
    public function __construct($turnoId)
    {
        $this->turnoId = $turnoId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Iniciando el job TurnosPendientes para el turno #' . $this->turnoId);
        
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

            $this->enviarNotificaciones($turnoActual);

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

    private function enviarNotificaciones(Turno $turno): void
    {
        try {
            if($turno->persona && $turno->persona->usuario) {
                $turno->persona->usuario->notify(
                    new ReservaNotification($turno, 'cancelacion_automatica')
                );
            }

            User::where('rol', 'admin')->get()->each->notify(
                new ReservaNotification($turno, 'admin.cancelacion_automatica')
            );
        } catch (\Exception $e) {
            Log::error('Error al enviar notificaciones: ' . $e->getMessage());
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Error en el job TurnosPendientes para el turno #' . $this->turnoId . ': ' . $exception->getMessage());
    }
}
