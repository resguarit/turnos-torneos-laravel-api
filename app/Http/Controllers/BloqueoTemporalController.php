<?php

namespace App\Http\Controllers;

use App\Models\BloqueoTemporal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Turno;
use Illuminate\Support\Facades\Validator;
use App\Jobs\EliminarBloqueo;

class BloqueoTemporalController extends Controller
{
    public function bloquearHorario(Request $request)
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('turnos:bloqueo') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');


        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'horario_id' => 'required|exists:horarios,id',
            'cancha_id' => 'required|exists:canchas,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        
        try {
            DB::beginTransaction(); // Inicia la transacción

            // Bloqueo exclusivo para evitar condiciones de carrera
            $ya_reservado = Turno::where('fecha_turno', $request->fecha)
                ->where('horario_id', $request->horario_id)
                ->where('cancha_id', $request->cancha_id)
                ->exists();

            $ya_bloqueado = BloqueoTemporal::where('fecha', $request->fecha)
                ->where('horario_id', $request->horario_id)
                ->where('cancha_id', $request->cancha_id)
                ->exists();

            if ($ya_reservado || $ya_bloqueado) {
                DB::rollBack();
                return response()->json(['message' => 'El Turno ya no está disponible.'], 400);
            }

            // Crear el bloqueo temporal
            $bloqueo = BloqueoTemporal::create([
                'usuario_id' => $user->id,
                'horario_id' => $request->horario_id,
                'cancha_id' => $request->cancha_id,
                'fecha' => $request->fecha,
                'expira_en' => now()->addMinutes(1),
            ]);

            EliminarBloqueo::dispatch($bloqueo->id)->delay(now()->addMinutes(2));

            DB::commit(); // Confirma la transacción

            return response()->json(['message' => 'Bloqueo temporal creado con éxito.', 'bloqueo' => $bloqueo], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Revierte la transacción en caso de error
            return response()->json(['message' => 'Error al bloquear el horario.', 'error' => $e->getMessage()], 500);
        }
    }
}