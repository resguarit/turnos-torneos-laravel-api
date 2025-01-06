<?php

namespace App\Http\Controllers;

use App\Models\BloqueoTemporal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Cancha;
use App\Models\Horario;
use App\Models\Turno;

class BloqueoTemporalController extends Controller
{
    public function bloquearHorario(Request $request)
    {
        abort_unless($user = Auth::user(), 401, 'No autorizado');

        abort_unless( $user->tokenCan('turno:bloqueo') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        
        $validated = $request->validate([
            'usuario_id' => 'required|exists:users,id',
            'canchaID' => 'required|exists:canchas,id',
            'horarioID' => 'required|exists:horarios,id',
            'fecha' => 'required|date',
        ]);

        $turno = Cancha::where('cancha_id', $validated['canchaID']) && Horario::where('horario_id', $validated['horarioID']) && Turno::where('fecha_turno', $validated['fecha'])->first();

        if (!$turno) {
            return response()->json([
                'message' => 'Turno no encontrado',
                'status' => 404
            ], 404);
        }

        try {
            DB::beginTransaction(); // Inicia la transacción

            // Bloqueo exclusivo para evitar condiciones de carrera
            $yaReservado = DB::table('turnos')
                ->where('horarioCanchaID', $horarioCancha->id)
                ->where('fecha_turno', $validated['fecha'])
                ->whereIn('estado', ['pendiente', 'confirmada'])
                ->lockForUpdate()
                ->exists();

            $yaBloqueado = DB::table('bloqueo_temporal')
                ->where('horario_cancha_id', $horarioCancha->id)
                ->where('fecha', $validated['fecha'])
                ->where('expira_en', '>', now())
                ->lockForUpdate()
                ->exists();

            if ($yaReservado || $yaBloqueado) {
                DB::rollBack();
                return response()->json(['message' => 'El horario ya no está disponible.'], 400);
            }

            // Crear el bloqueo temporal
            $bloqueo = BloqueoTemporal::create([
                'usuario_id' => $validated['usuario_id'],
                'horario_cancha_id' => $horarioCancha->id,
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
} // Asegúrate de que esta llave de cierre esté presente.
