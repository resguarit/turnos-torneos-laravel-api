<?php

namespace App\Services\Implementation;

use App\Models\Sancion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SancionService
{
    public function createSancion(array $data)
    {
        $validator = Validator::make($data, [
            'equipo_jugador_id' => 'required|exists:equipo_jugador,id',
            'motivo' => 'required|string|max:255',
            'tipo_sancion' => 'required|in:expulsión,advertencia,suspensión,multa',
            'cantidad_fechas' => 'nullable|integer|min:1',
            'fecha_inicio' => 'nullable|exists:fechas,id',
            'fecha_fin' => 'nullable|exists:fechas,id|after_or_equal:fecha_inicio',
            'partido_id' => 'nullable|exists:partidos,id',
            'estado' => 'required|in:activa,cumplida,apelada,anulada',
        ]);

        if ($validator->fails()) {
            return [
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ];
        }

        DB::beginTransaction();
        try {
            $sancion = Sancion::create($data);

            DB::commit();

            return [
                'message' => 'Sanción creada correctamente',
                'sancion' => $sancion,
                'status' => 201
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'message' => 'Error al crear la sanción',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }

    public function getSancionById($id)
    {
        $sancion = Sancion::with(['equipoJugador', 'fechaInicio', 'fechaFin', 'partido'])->find($id);

        if (!$sancion) {
            return [
                'message' => 'Sanción no encontrada',
                'status' => 404
            ];
        }

        return [
            'sancion' => $sancion,
            'status' => 200
        ];
    }

    public function deleteSancion($id)
    {
        $sancion = Sancion::find($id);

        if (!$sancion) {
            return [
                'message' => 'Sanción no encontrada',
                'status' => 404
            ];
        }

        try {
            $sancion->delete();

            return [
                'message' => 'Sanción eliminada correctamente',
                'status' => 200
            ];
        } catch (\Exception $e) {
            return [
                'message' => 'Error al eliminar la sanción',
                'error' => $e->getMessage(),
                'status' => 500
            ];
        }
    }
}