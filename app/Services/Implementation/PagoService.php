<?php

namespace App\Services\Implementation;

use App\Models\Equipo;
use App\Models\Torneo;
use App\Models\Evento;
use App\Models\Caja;
use App\Models\Zona;
use App\Models\Fecha;
use App\Models\CuentaCorriente;
use App\Models\Persona;
use App\Models\Transaccion;
use Illuminate\Support\Facades\DB;

class PagoService
{
    public function registrarPagoInscripcion($equipoId, $torneoId, $metodoPagoId)
    {
        $equipo = Equipo::findOrFail($equipoId);
        $torneo = Torneo::findOrFail($torneoId);

        // Buscar el jugador capitán
        $capitan = $equipo->jugadores()->wherePivot('capitan', true)->first();
        if (!$capitan) {
            return [
                'message' => 'No se encontró capitán para este equipo',
                'status' => 400
            ];
        }

        // Buscar persona y cuenta corriente
        $persona = Persona::where('dni', $capitan->dni)->first();
        if (!$persona) {
            return [
                'message' => 'No se encontró persona para el capitán',
                'status' => 400
            ];
        }
        $cuentaCorriente = CuentaCorriente::firstOrCreate(
            ['persona_id' => $persona->id],
            ['saldo' => 0]
        );

        // Buscar la primera caja abierta
        $caja = Caja::where('activa', 1)->orderBy('id')->first();
        if (!$caja) {
            return [
                'message' => 'Error al registrar el pago',
                'error' => 'No hay una caja abierta disponible',
                'status' => 400
            ];
        }

        // Registrar el pago (transacción)
        DB::beginTransaction();
        try {
            $monto = $torneo->precio_inscripcion;
            $transaccion = Transaccion::create([
                'cuenta_corriente_id' => $cuentaCorriente->id,
                'caja_id' => $caja->id,
                'metodo_pago_id' => $metodoPagoId,
                'monto' => $monto, // Cambia a positivo para un depósito
                'tipo' => 'inscripcion', // Cambia el tipo a "deposito"
                'descripcion' => "Pago inscripción torneo {$torneo->nombre} ({$torneo->id})"
            ]);
            $cuentaCorriente->saldo += $monto; // Incrementa el saldo
            $cuentaCorriente->save();

            DB::commit();
            return [
                'message' => 'Pago de inscripción registrado correctamente',
                'transaccion' => $transaccion,
                'nuevo_saldo' => $cuentaCorriente->saldo,
                'status' => 201
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'message' => 'Error al registrar el pago de inscripción',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function registrarPagoPorFecha($fechaId, $metodoPagoId)
    {
        $fecha = Fecha::with('zona.torneo')->findOrFail($fechaId);

        if (!$fecha->zona || !$fecha->zona->torneo) {
            return [
                'message' => 'La fecha no está asociada a un torneo',
                'status' => 400
            ];
        }

        $torneo = $fecha->zona->torneo;

        // Buscar la primera caja abierta
        $caja = Caja::where('activa', 1)->orderBy('id')->first();
        if (!$caja) {
            return [
                'message' => 'Error al registrar el pago',
                'error' => 'No hay una caja abierta disponible',
                'status' => 400
            ];
        }

        // Buscar el capitán del equipo asociado a la fecha
        $equipo = $fecha->zona->equipos->first(); // Asume que hay un equipo asociado a la zona
        if (!$equipo) {
            return [
                'message' => 'No se encontró un equipo asociado a la zona de la fecha',
                'status' => 400
            ];
        }

        $capitan = $equipo->jugadores()->wherePivot('capitan', true)->first();
        if (!$capitan) {
            return [
                'message' => 'No se encontró un capitán para el equipo',
                'status' => 400
            ];
        }

        // Buscar la persona asociada al capitán
        $persona = Persona::where('dni', $capitan->dni)->first();
        if (!$persona) {
            return [
                'message' => 'No se encontró una persona asociada al capitán',
                'status' => 400
            ];
        }

        // Buscar la cuenta corriente asociada a la persona
        $cuentaCorriente = CuentaCorriente::where('persona_id', $persona->id)->first();
        if (!$cuentaCorriente) {
            return [
                'message' => 'No se encontró una cuenta corriente asociada a la persona',
                'status' => 400
            ];
        }

        // Registrar el pago en la cuenta corriente del capitán
        DB::beginTransaction();
        try {
            $monto = $torneo->precio_por_fecha;

            // Crear la transacción asociada a la cuenta corriente del capitán
            $transaccion = Transaccion::create([
                'cuenta_corriente_id' => $cuentaCorriente->id,
                'caja_id' => $caja->id,
                'metodo_pago_id' => $metodoPagoId,
                'monto' => $monto,
                'tipo' => 'fecha',
                'descripcion' => "Pago de fecha '{$fecha->nombre}' del torneo {$torneo->nombre} ({$torneo->id})"
            ]);
            
            $cuentaCorriente->save();

            DB::commit();

            return [
                'message' => 'Pago de fecha registrado correctamente',
                'transaccion' => $transaccion,
                'nuevo_saldo' => $cuentaCorriente->saldo,
                'status' => 201
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'message' => 'Error al registrar el pago de fecha',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function obtenerPagoInscripcion($equipoId, $torneoId)
    {
        $equipo = Equipo::findOrFail($equipoId);
        $torneo = Torneo::findOrFail($torneoId);

        // Buscar capitán del equipo
        $capitan = $equipo->jugadores()->wherePivot('capitan', true)->first();
        if (!$capitan) {
            return [
                'message' => 'No se encontró capitán para este equipo',
                'status' => 400
            ];
        }

        // Buscar persona y cuenta corriente
        $persona = Persona::where('dni', $capitan->dni)->first();
        if (!$persona) {
            return [
                'message' => 'No se encontró persona para el capitán',
                'status' => 400
            ];
        }
        $cuentaCorriente = CuentaCorriente::where('persona_id', $persona->id)->first();

        if (!$cuentaCorriente) {
            return [
                'message' => 'No se encontró cuenta corriente para el capitán',
                'status' => 400
            ];
        }

        // Buscar transacción de inscripción
        $transaccion = Transaccion::where('cuenta_corriente_id', $cuentaCorriente->id)
            ->where('tipo', 'inscripcion')
            ->where('descripcion', 'like', "%torneo {$torneo->nombre}%")
            ->first();

        return [
            'transaccion' => $transaccion,
            'status' => 200
        ];
    }

    public function obtenerPagoPorFecha($equipoId, $zonaId)
    {
        $equipo = Equipo::findOrFail($equipoId);
        $zona = Zona::with(['fechas', 'torneo'])->findOrFail($zonaId);

        // Buscar capitán del equipo
        $capitan = $equipo->jugadores()->wherePivot('capitan', true)->first();
        if (!$capitan) {
            return [
                'message' => 'No se encontró capitán para este equipo',
                'status' => 400
            ];
        }

        // Buscar persona y cuenta corriente
        $persona = Persona::where('dni', $capitan->dni)->first();
        if (!$persona) {
            return [
                'message' => 'No se encontró persona para el capitán',
                'status' => 400
            ];
        }
        $cuentaCorriente = CuentaCorriente::where('persona_id', $persona->id)->first();

        if (!$cuentaCorriente) {
            return [
                'message' => 'No se encontró cuenta corriente para el capitán',
                'status' => 400
            ];
        }

        $torneo = $zona->torneo ?? null;
        $resultados = [];

        foreach ($zona->fechas as $fecha) {
            $transaccion = Transaccion::where('cuenta_corriente_id', $cuentaCorriente->id)
                ->where('tipo', 'fecha')
                ->where('descripcion', 'like', "%fecha '{$fecha->nombre}'%")
                ->when($torneo, function ($query) use ($torneo) {
                    $query->where('descripcion', 'like', "%torneo {$torneo->nombre}%");
                })
                ->first();

            $resultados[] = [
                'fecha_id' => $fecha->id,
                'fecha_nombre' => $fecha->nombre,
                'pagado' => $transaccion ? true : false,
                'transaccion' => $transaccion
            ];
        }

        return [
            'pagos_por_fecha' => $resultados,
            'status' => 200
        ];
    }

    public function registrarPagoEvento($id, $metodoPagoId){

        $evento = Evento::findOrFail($id);

        if (!$evento) {
            return [
                'message' => 'No se encontró el evento',
                'status' => 404
            ];
        }

        $cuentaCorriente = CuentaCorriente::where('persona_id', $evento->persona_id)->first();
        if (!$cuentaCorriente) {
            return [
                'message' => 'No se encontró cuenta corriente para la persona asociada al evento',
                'status' => 404
            ];
        }

        $caja = Caja::where('activa', 1)->orderBy('id')->first();
        if (!$caja) {
            return [
                'message' => 'Error al registrar el pago',
                'error' => 'No hay una caja abierta disponible',
                'status' => 400
            ];
        }

        DB::beginTransaction();
        try {
            $montoAPagar = $evento->monto;
            $transaccion = Transaccion::create([
                'cuenta_corriente_id' => $cuentaCorriente->id,
                'caja_id' => $caja->id,
                'metodo_pago_id' => $metodoPagoId,
                'monto' => $montoAPagar, 
                'tipo' => 'evento', 
                'descripcion' => "Pago evento {$evento->nombre} ({$evento->id})"
            ]);
            $cuentaCorriente->saldo += $montoAPagar;
            $cuentaCorriente->save();

            DB::commit();
            return [
                'message' => 'Pago de evento registrado correctamente',
                'transaccion' => $transaccion,
                'nuevo_saldo' => $cuentaCorriente->saldo,
                'status' => 201
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'message' => 'Error al registrar el pago de inscripción',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }


    }
}