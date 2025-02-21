<?php

namespace App\Services\Implementation;

use App\Models\BloqueoTemporal;
use App\Models\Turno;
use App\Services\Interface\BloqueoTemporalServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Jobs\EliminarBloqueo;
use Illuminate\Support\Facades\Auth;

class BloqueoTemporalService implements BloqueoTemporalServiceInterface
{
    public function bloquearHorario(Request $request)
    {
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
            DB::beginTransaction();

            $ya_reservado = Turno::where('fecha_turno', $request->fecha)
                ->where('horario_id', $request->horario_id)
                ->where('cancha_id', $request->cancha_id)
                ->where('estado', '!=', 'Cancelado')
                ->exists();

            $ya_bloqueado = BloqueoTemporal::where('fecha', $request->fecha)
                ->where('horario_id', $request->horario_id)
                ->where('cancha_id', $request->cancha_id)
                ->exists();

            if ($ya_reservado || $ya_bloqueado) {
                DB::rollBack();
                return response()->json(['message' => 'El Turno ya no está disponible.'], 400);
            }

            $bloqueo = BloqueoTemporal::create([
                'usuario_id' => Auth::id(),
                'horario_id' => $request->horario_id,
                'cancha_id' => $request->cancha_id,
                'fecha' => $request->fecha,
                'expira_en' => now()->setTimezone('America/Argentina/Buenos_Aires')->addMinutes(1),
            ]);

            EliminarBloqueo::dispatch($bloqueo->id)
                ->delay(now()->setTimezone('America/Argentina/Buenos_Aires')->addMinutes(1));

            DB::commit();

            return response()->json([
                'message' => 'Bloqueo temporal creado con éxito.',
                'bloqueo' => $bloqueo,
                'status' => 201
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al bloquear el horario.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function cancelarBloqueo($id)
    {
        try {
            DB::beginTransaction();
            
            $bloqueo = BloqueoTemporal::lockForUpdate()
                ->where('id', $id)
                ->where('usuario_id', Auth::id())
                ->first();

            if (!$bloqueo) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No se encontró el bloqueo temporal',
                    'status' => 404
                ], 404);
            }

            $deleted = $bloqueo->delete();

            if (!$deleted) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Error al eliminar el bloqueo temporal',
                    'status' => 500
                ], 500);
            }

            DB::commit();
            return response()->json([
                'message' => 'Bloqueo temporal cancelado exitosamente',
                'status' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al cancelar el bloqueo temporal',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }
}