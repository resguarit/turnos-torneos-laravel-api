<?php

namespace App\Services\Implementation;

use App\Services\Interface\PaymentServiceInterface;
use Illuminate\Support\Facades\Log;
use App\Models\Turno;
use App\Models\Persona;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\TurnoCancelacion;
use App\Models\CuentaCorriente;
use App\Models\Transaccion;
use App\Models\Caja;
use App\Enums\TurnoEstado;
use Carbon\Carbon;
use App\Models\MetodoPago;
use App\Models\User;
use App\Notifications\ReservaNotification;

class PaymentService implements PaymentServiceInterface
{
    public function handleNewPayment($payment)
    {
        Log::info("payment status desde payment service: ");
        Log::info(json_encode($payment));
        Log::info($payment->status);
        Log::info($payment->external_reference);

        $turno = Turno::where('id', $payment->external_reference)->first();
        $persona = Persona::with(['usuario', 'cuentaCorriente'])->where('id', $turno->persona_id)->first();

        $clave = "bloqueo:{$turno->fecha_turno->format('Y-m-d')}:{$turno->horario_id}:{$turno->cancha_id}";
        $bloqueo = Cache::get($clave);

        if($payment->status == 'rejected') {

            DB::beginTransaction();

            try {
                if ($turno->estado != TurnoEstado::CANCELADO) {

                    Transaccion::create([
                        'cuenta_corriente_id' => $persona->cuentaCorriente->id,
                        'monto' => $turno->monto_total,
                        'turno_id' => $turno->id,
                        'tipo' => 'saldo',
                        'descripcion' => 'Cancelacion de turno #' . $turno->id . '(Por pago rechazado)',
                        'payment_id' => $payment->id,
                    ]);

                    $persona->cuentaCorriente->saldo += $turno->monto_total;
                    $persona->cuentaCorriente->save();

                    $turno->estado = TurnoEstado::CANCELADO;
                    $turno->save();

                    TurnoCancelacion::create([
                        'turno_id' => $turno->id,
                        'cancelado_por' => $persona->id,
                        'motivo' => 'Pago rechazado',
                        'fecha_cancelacion' => Carbon::now('America/Argentina/Buenos_Aires'),
                    ]);

                    DB::commit();
                }

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error al cancelar el turno: " . $e->getMessage());
            }

        } elseif ($payment->status == 'approved') {

            DB::beginTransaction();

            try {

                if ($turno->estado == TurnoEstado::CANCELADO) {
                    $persona->cuentaCorriente->saldo -= $turno->monto_total;
                    $persona->cuentaCorriente->save();

                    $cajaAbierta = Caja::where('activa', true)->first();

                    $metodoPago = MetodoPago::where('nombre', 'mercadopago')->first();

                    Transaccion::create([
                        'cuenta_corriente_id' => $persona->cuentaCorriente->id,
                        'monto' => $turno->monto_seña,
                        'turno_id' => $turno->id,
                        'tipo' => 'turno',
                        'descripcion' => 'Pago de seña para turno #' . $turno->id,
                        'caja_id' => $cajaAbierta ? $cajaAbierta->id : null,
                        'payment_id' => $payment->id,
                        'metodo_pago_id' => $metodoPago->id,
                    ]);

                    $persona->cuentaCorriente->saldo += $turno->monto_seña;
                    $persona->cuentaCorriente->save();

                    $turno->estado = TurnoEstado::SEÑADO;
                    $turno->save();

                    $clave = "bloqueo:{$turno->fecha_turno->format('Y-m-d')}:{$turno->horario_id}:{$turno->cancha_id}";
                    Cache::forget($clave);

                    if ($persona->usuario) {
                        $persona->usuario->notify(new ReservaNotification($turno, 'confirmacion'));
                    }

                    User::where('rol', 'admin')->get()->each->notify(new ReservaNotification($turno, 'admin.confirmacion'));

                    DB::commit();
                } else if ($turno->estado == TurnoEstado::PENDIENTE) {
                    $cajaAbierta = Caja::where('activa', true)->first();

                    $metodoPago = MetodoPago::where('nombre', 'mercadopago')->first();

                    Transaccion::create([
                        'cuenta_corriente_id' => $persona->cuentaCorriente->id,
                        'monto' => $turno->monto_seña,
                        'turno_id' => $turno->id,
                        'tipo' => 'turno',
                        'descripcion' => 'Pago de seña para turno #' . $turno->id,
                        'caja_id' => $cajaAbierta ? $cajaAbierta->id : null,
                        'payment_id' => $payment->id,
                        'metodo_pago_id' => $metodoPago->id,
                    ]);

                    $persona->cuentaCorriente->saldo += $turno->monto_seña;
                    $persona->cuentaCorriente->save();

                    $turno->estado = TurnoEstado::SEÑADO;
                    $turno->save();

                    $clave = "bloqueo:{$turno->fecha_turno->format('Y-m-d')}:{$turno->horario_id}:{$turno->cancha_id}";
                    Cache::forget($clave);

                    if ($persona->usuario) {
                        $persona->usuario->notify(new ReservaNotification($turno, 'confirmacion'));
                    }

                    User::where('rol', 'admin')->get()->each->notify(new ReservaNotification($turno, 'admin.confirmacion'));

                    DB::commit();
                }

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error al pagar el turno: " . $e->getMessage());
            }
        }

        Log::info("turno desde payment service: ");
        Log::info(json_encode($turno));
        Log::info("bloqueo desde payment service: ");
        Log::info(json_encode($bloqueo));

    }
}

