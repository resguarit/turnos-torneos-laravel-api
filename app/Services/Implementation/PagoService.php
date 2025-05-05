<?php

namespace App\Services\Implementation;

use App\Models\Equipo;
use App\Models\Torneo;
use App\Models\CuentaCorriente;
use App\Models\Persona;
use App\Models\Transaccion;
use Illuminate\Support\Facades\DB;

class PagoService
{
    public function registrarPagoInscripcion($equipoId, $torneoId)
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
        $caja = \App\Models\Caja::where('estado', 'abierta')->orderBy('id')->first();
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
                'monto' => -$monto,
                'tipo' => 'inscripcion',
                'descripcion' => "Pago inscripción torneo {$torneo->nombre} ({$torneo->id})"
            ]);
            $cuentaCorriente->saldo -= $monto;
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

    public function registrarPagoPorFecha($fechaId)
    {
        $fecha = \App\Models\Fecha::with('zona.equipos', 'zona.torneo')->findOrFail($fechaId);

        if (!$fecha->zona || !$fecha->zona->torneo) {
            return [
                'message' => 'La fecha no está asociada a un torneo',
                'status' => 400
            ];
        }

        $torneo = $fecha->zona->torneo;
        $equipos = $fecha->zona->equipos;

        $pagos = [];

        foreach ($equipos as $equipo) {
            // Buscar capitán del equipo
            $capitan = $equipo->jugadores()->wherePivot('capitan', true)->first();
            if (!$capitan) {
                $pagos[] = [
                    'equipo_id' => $equipo->id,
                    'equipo' => $equipo->nombre,
                    'status' => 'error',
                    'message' => 'No se encontró capitán para este equipo'
                ];
                continue;
            }

            // Buscar persona y cuenta corriente
            $persona = \App\Models\Persona::where('dni', $capitan->dni)->first();
            if (!$persona) {
                $pagos[] = [
                    'equipo_id' => $equipo->id,
                    'equipo' => $equipo->nombre,
                    'status' => 'error',
                    'message' => 'No se encontró persona para el capitán'
                ];
                continue;
            }
            $cuentaCorriente = \App\Models\CuentaCorriente::firstOrCreate(
                ['persona_id' => $persona->id],
                ['saldo' => 0]
            );

            // Buscar la primera caja abierta
            $caja = \App\Models\Caja::where('estado', 'abierta')->orderBy('id')->first();
            if (!$caja) {
                $pagos[] = [
                    'equipo_id' => $equipo->id,
                    'equipo' => $equipo->nombre,
                    'status' => 'error',
                    'message' => 'Error al registrar el pago',
                    'error' => 'No hay una caja abierta disponible'
                ];
                continue;
            }

            // Registrar el pago (transacción)
            try {
                $monto = $torneo->precio_por_fecha;
                $transaccion = \App\Models\Transaccion::create([
                    'cuenta_corriente_id' => $cuentaCorriente->id,
                    'caja_id' => $caja->id,
                    'monto' => -$monto,
                    'tipo' => 'fecha',
                    'descripcion' => "Pago de fecha '{$fecha->nombre}' del torneo {$torneo->nombre} ({$torneo->id})"
                ]);
                $cuentaCorriente->saldo -= $monto;
                $cuentaCorriente->save();

                $pagos[] = [
                    'equipo_id' => $equipo->id,
                    'equipo' => $equipo->nombre,
                    'status' => 'ok',
                    'transaccion' => $transaccion,
                    'nuevo_saldo' => $cuentaCorriente->saldo
                ];
            } catch (\Exception $e) {
                $pagos[] = [
                    'equipo_id' => $equipo->id,
                    'equipo' => $equipo->nombre,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }

        return [
            'message' => 'Pagos de fecha procesados',
            'pagos' => $pagos,
            'status' => 200
        ];
    }

    public function obtenerPagoInscripcion($equipoId, $torneoId)
    {
        $equipo = \App\Models\Equipo::findOrFail($equipoId);
        $torneo = \App\Models\Torneo::findOrFail($torneoId);

        // Buscar capitán del equipo
        $capitan = $equipo->jugadores()->wherePivot('capitan', true)->first();
        if (!$capitan) {
            return [
                'message' => 'No se encontró capitán para este equipo',
                'status' => 400
            ];
        }

        // Buscar persona y cuenta corriente
        $persona = \App\Models\Persona::where('dni', $capitan->dni)->first();
        if (!$persona) {
            return [
                'message' => 'No se encontró persona para el capitán',
                'status' => 400
            ];
        }
        $cuentaCorriente = \App\Models\CuentaCorriente::where('persona_id', $persona->id)->first();

        if (!$cuentaCorriente) {
            return [
                'message' => 'No se encontró cuenta corriente para el capitán',
                'status' => 400
            ];
        }

        // Buscar transacción de inscripción
        $transaccion = \App\Models\Transaccion::where('cuenta_corriente_id', $cuentaCorriente->id)
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
        $equipo = \App\Models\Equipo::findOrFail($equipoId);
        $zona = \App\Models\Zona::with(['fechas', 'torneo'])->findOrFail($zonaId);

        // Buscar capitán del equipo
        $capitan = $equipo->jugadores()->wherePivot('capitan', true)->first();
        if (!$capitan) {
            return [
                'message' => 'No se encontró capitán para este equipo',
                'status' => 400
            ];
        }

        // Buscar persona y cuenta corriente
        $persona = \App\Models\Persona::where('dni', $capitan->dni)->first();
        if (!$persona) {
            return [
                'message' => 'No se encontró persona para el capitán',
                'status' => 400
            ];
        }
        $cuentaCorriente = \App\Models\CuentaCorriente::where('persona_id', $persona->id)->first();

        if (!$cuentaCorriente) {
            return [
                'message' => 'No se encontró cuenta corriente para el capitán',
                'status' => 400
            ];
        }

        $torneo = $zona->torneo ?? null;
        $resultados = [];

        foreach ($zona->fechas as $fecha) {
            $transaccion = \App\Models\Transaccion::where('cuenta_corriente_id', $cuentaCorriente->id)
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
}