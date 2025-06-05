<?php

namespace App\Services\Implementation;

use Illuminate\Http\Request;
use App\Models\Evento;
use Illuminate\Support\Facades\Validator;
use App\Services\Interface\EventoServiceInterface;
use App\Enums\EventoEstado;
use App\Models\CuentaCorriente;
use App\Models\Transaccion;
use App\Models\Persona;
use Illuminate\Support\Facades\DB;



class EventoService implements EventoServiceInterface
{
    public function getAll(){
        return Evento::with(['persona', 'combinaciones.horario', 'combinaciones.cancha'])->get();
    }

    public function getById($id){
        return Evento::with('persona')->find($id);
    }

    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'required|string|max:255',
            'fecha' => 'required|date',
            'monto' => 'required|numeric',
            'persona_id' => 'required|exists:personas,id',
            'combinaciones' => 'required|array',
            'combinaciones.*.horario_id' => 'required|exists:horarios,id',
            'combinaciones.*.cancha_id' => 'required|exists:canchas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        DB::beginTransaction();

        try {   

            $evento = Evento::create($request->only(['nombre', 'descripcion', 'fecha', 'persona_id', 'monto']));

            foreach ($request->combinaciones as $combinacion) {
                \App\Models\EventoHorarioCancha::create([
                    'evento_id' => $evento->id,
                    'horario_id' => $combinacion['horario_id'],
                    'cancha_id' => $combinacion['cancha_id'],
                    // Agrega otros campos necesarios como 'estado'
                ]);
            }

            $cuentaCorriente = CuentaCorriente::firstOrCreate(
                            ['persona_id' => $request->persona_id],
                            ['saldo' => 0] 
                        );

            Transaccion::create([
                'cuenta_corriente_id' => $cuentaCorriente->id,
                'evento_id' => $evento->id,
                'monto' => -$evento->monto,
                'tipo' => 'saldo',
                'descripcion' => 'Reserva de evento #' . $evento->id . '(Pendiente de pago)',
            ]);
            
            $cuentaCorriente->saldo -= $evento->monto;

            $cuentaCorriente->save();

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el evento',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }

        return response()->json([
            'message' => 'Evento creado correctamente',
            'evento' => $evento,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id){
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'sometimes|string|max:255',
            'monto' => 'sometimes|numeric',
            'fecha' => 'sometimes|date',
            'persona_id' => 'sometimes|exists:personas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $evento = Evento::find($id);
        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado',
                'status' => 404
            ], 404);
        }

        $evento->update($request->all());

        return response()->json([
            'message' => 'Evento actualizado correctamente',
            'evento' => $evento,
            'status' => 200
        ], 200);
    }

    public function delete($id){
        $evento = Evento::find($id);
        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado',
                'status' => 404
            ], 404);
        }

        $evento->delete();

        return response()->json([
            'message' => 'Evento eliminado correctamente',
            'status' => 200
        ], 200);
    }

    public function getEventosComoTurnos()
    {
        $eventos = Evento::with(['persona', 'combinaciones.horario', 'combinaciones.cancha'])->get();

        $result = [];

        foreach ($eventos as $evento) {
            // Agrupar combinaciones por horario_id
            $combinacionesPorHorario = $evento->combinaciones->groupBy('horario_id');
            foreach ($combinacionesPorHorario as $horarioId => $combinaciones) {
                $horario = $combinaciones->first()->horario;
                $canchas = $combinaciones->map(function($comb) {
                    return [
                        'id' => $comb->cancha->id,
                        'nro' => $comb->cancha->nro,
                        'tipo' => $comb->cancha->tipo_cancha,
                        'descripcion' => $comb->cancha->descripcion,
                    ];
                })->unique('id')->values();

                // Si hay más de una combinación para el mismo horario, puede haber más de un estado
                $estados = $combinaciones->pluck('estado')->unique()->values();

                $result[] = [
                    'evento_id' => $evento->id,
                    'nombre' => $evento->nombre,
                    'descripcion' => $evento->descripcion,
                    'estado' => $evento->estado,
                    'fecha' => $evento->fecha,
                    'monto' => $evento->monto,
                    'persona' => $evento->persona,
                    'horario' => [
                        'id' => $horario->id,
                        'hora_inicio' => $horario->hora_inicio,
                        'hora_fin' => $horario->hora_fin,
                        'dia' => $horario->dia,
                    ],
                    'canchas' => $canchas,
                    'estado_combinacion' => $estados, 
                ];
            }
        }

        return response()->json([
            'eventos_turnos' => $result,
            'status' => 200
        ], 200);
    }

    public function obtenerEstadoPago($id){
        $evento = Evento::where('id', $id)->with('persona')->first();
        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado',
                'status' => 404
            ], 404);
        }

        $estadoPago=false;

        $persona = Persona::where('id', $evento->persona_id)->first();
        if (!$persona) {
            return response()->json([
                'message' => 'Persona no encontrada',
                'status' => 404
            ], 404);
        }
        
        $cuentacorriente = CuentaCorriente::where('persona_id', $evento->persona->id)->first();

        if (!$cuentacorriente) {
            return response()->json([
                'message' => 'Cuenta corriente no encontrada',
                'status' => 404
            ], 404);
        }

        $transaccion = Transaccion::where('cuenta_corriente_id', $cuentacorriente->id)
            ->where('evento_id', $evento->id)
            ->where('tipo', 'evento')
            ->first();
            
        if (!$transaccion) {
            $estadoPago = false;
        } else {
            $estadoPago = true;
        }

        return response()->json([
            'estado_pago' => $estadoPago,
            'status' => 200
        ], 200);
    }

    public function obtenerEstadosPagoEventos()
    {
        $eventos = Evento::with('persona')->get();
        $result = [];

        foreach ($eventos as $evento) {
            $estadoPago = false;

            $cuentacorriente = CuentaCorriente::where('persona_id', $evento->persona_id)->first();

            if ($cuentacorriente) {
                $transaccion = Transaccion::where('cuenta_corriente_id', $cuentacorriente->id)
                    ->where('evento_id', $evento->id)
                    ->where('tipo', 'evento')
                    ->first();

                if ($transaccion) {
                    $estadoPago = true;
                }
            }

            $result[] = [
                'evento_id' => $evento->id,
                'nombre' => $evento->nombre,
                'fecha' => $evento->fecha,
                'persona' => $evento->persona,
                'estado_pago' => $estadoPago
            ];
        }

        return response()->json([
            'eventos' => $result,
            'status' => 200
        ], 200);
    }
}