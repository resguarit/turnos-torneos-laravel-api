<?php

namespace App\Http\Controllers;

use App\Models\BloqueoTemporal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Cancha;
use App\Models\Horario;
use App\Models\Turno;
use Illuminate\Support\Facades\Log;

class BloqueoTemporalController extends Controller
{
    public function bloquearHorario(Request $request)
    {
        abort_unless($user = Auth::user(), 401, 'No autorizado');

        abort_unless($user->tokenCan('turno:bloqueo') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $validated = $request->validate([
            'usuario_id' => 'required|exists:users,id',
            'cancha_id' => 'required|exists:canchas,id',
            'horario_id' => 'required|exists:horarios,id',
            'fecha' => 'required|date',
        ]);
        
        Log::info('Datos recibidos en el request:', $request->all());

        try {
            DB::beginTransaction(); // Inicia la transacción

            // Bloqueo exclusivo para evitar condiciones de carrera
            $ya_reservado = Turno::where('fecha_turno', $validated['fecha'])
                ->where('horario_id', $validated['horario_id'])
                ->where('cancha_id', $validated['cancha_id'])
                ->exists();

            $ya_bloqueado = BloqueoTemporal::where('fecha', $validated['fecha'])
                ->where('horario_id', $validated['horario_id'])
                ->where('cancha_id', $validated['cancha_id'])
                ->exists();

            if ($ya_reservado || $ya_bloqueado) {
                DB::rollBack();
                return response()->json(['message' => 'El horario ya no está disponible.'], 400);
            }

            // Crear el bloqueo temporal
            $bloqueo = BloqueoTemporal::create([
                'usuario_id' => $validated['usuario_id'],
                'horario_id' => $validated['horario_id'],
                'cancha_id' => $validated['cancha_id'],
                'fecha' => $validated['fecha'],
                'expira_en' => now()->addMinutes(10),
            ]);

            DB::commit(); // Confirma la transacción

            return response()->json(['message' => 'Bloqueo temporal creado con éxito.', 'bloqueo' => $bloqueo], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Revierte la transacción en caso de error
            return response()->json(['message' => 'Error al bloquear el horario.', 'error' => $e->getMessage()], 500);
        }
    }

    public function storeTurnoUnico(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->tokenCan('turnos:create') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $validated = $request->validate([
            'fecha_turno' => 'required|date',
            'cancha_id' => 'required|exists:canchas,id',
            'horario_id' => 'required|exists:horarios,id',
            'usuario_id' => 'required|exists:users,id',
            'monto_total' => 'required|numeric',
            'monto_seña' => 'required|numeric',
            'estado' => 'required|string',
        ]);

        $horario = Horario::find($request->horario_id);
        $cancha = Cancha::find($request->cancha_id);

        if (!$horario || !$cancha) {
            return response()->json([
                'message' => 'Horario o Cancha no encontrados',
                'status' => 404
            ], 404);
        }

        $turnoExistente = Turno::where('fecha_turno', $request->fecha_turno)
                            ->where('horario_id', $horario->id)
                            ->where('cancha_id', $cancha->id)
                            ->first();

        if ($turnoExistente) {
            return response()->json([
                'message' => 'Ya existe un turno para esa cancha en esta fecha y horario',
                'status' => 400
            ], 400);
        }

        // Eliminar bloqueo temporal si existe
        BloqueoTemporal::where('fecha', $request->fecha_turno)
            ->where('horario_id', $request->horario_id)
            ->where('cancha_id', $request->cancha_id)
            ->delete();

        // Crear una nueva reserva
        $turno = Turno::create([
            'fecha_turno' => $request->fecha_turno,
            'fecha_reserva' => now(),
            'horario_id' => $request->horario_id,
            'cancha_id' => $request->cancha_id,
            'usuario_id' => $request->usuario_id,
            'monto_total' => $request->monto_total,
            'monto_seña' => $request->monto_seña,
            'estado' => $request->estado,
            'tipo' => 'unico'
        ]);

        if (!$turno) {
            return response()->json([
                'message' => 'Error al crear el turno',
                'status' => 500
            ], 500);
        }

        return response()->json([
            'message' => 'Turno creado correctamente',
            'turno' => $turno,
            'status' => 201
        ], 201);
    }

    public function storeTurnoFijo(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->tokenCan('turnos:createTurnoFijo') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $validator = Validator::make($request->all(), [
            'fecha_turno' => 'required|date',
            'cancha_id' => 'required|exists:canchas,id',
            'horario_id' => 'required|exists:horarios,id',
            'usuario_id' => 'required|exists:users,id',
            'monto_total' => 'required|numeric',
            'monto_seña' => 'required|numeric',
            'estado' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validacion',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $horario = Horario::find($request->horario_id);
        $cancha = Cancha::find($request->cancha_id);

        if (!$horario || !$cancha) {
            return response()->json([
                'message' => 'Horario o Cancha no encontrados',
                'status' => 404
            ], 404);
        }

        DB::beginTransaction();

        try {
            for ($i = 0; $i < 4; $i++) {
                $fecha_turno = now()->addWeeks($i)->toDateString();
                $turnoExistente = Turno::where('fecha_turno', $fecha_turno)
                                        ->where('horario_id', $horario->id)
                                        ->where('cancha_id', $cancha->id)
                                        ->first();

                if ($turnoExistente) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Ya existe un turno para esa cancha en la fecha ' . $fecha_turno,
                        'status' => 400
                    ], 400);
                }

                // Eliminar bloqueo temporal si existe
                BloqueoTemporal::where('fecha', $fecha_turno)
                    ->where('horario_id', $request->horario_id)
                    ->where('cancha_id', $request->cancha_id)
                    ->delete();

                $turno = Turno::create([
                    'fecha_turno' => $fecha_turno,
                    'fecha_reserva' => now(),
                    'horario_id' => $request->horario_id,
                    'cancha_id' => $request->cancha_id,
                    'usuario_id' => $request->usuario_id,
                    'monto_total' => $request->monto_total,
                    'monto_seña' => $request->monto_seña,
                    'estado' => $request->estado,
                    'tipo' => 'fijo'
                ]);

                if (!$turno) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Error al crear el turno',
                        'status' => 500
                    ], 500);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Turnos creados correctamente',
                'status' => 201
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear los turnos',
                'status' => 500,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
