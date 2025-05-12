<?php

namespace App\Services\Implementation;

use App\Models\Sancion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Zona;
use App\Models\Equipo;
use App\Models\Jugador;
use Illuminate\Http\Request;

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

        // Obtener el registro de la tabla pivote
        $equipoJugador = \DB::table('equipo_jugador')->where('id', $sancion->equipo_jugador_id)->first();

        $equipo = $equipoJugador ? Equipo::find($equipoJugador->equipo_id) : null;
        $jugador = $equipoJugador ? Jugador::find($equipoJugador->jugador_id) : null;

        return [
            'sancion' => $sancion,
            'status' => 200
        ];
    }

    public function updateSancion($data, $id)
    {
        $sancion = Sancion::find($id);

        if (!$sancion) {
            return [
                'message' => 'Sanción no encontrada',
                'status' => 404
            ];
        }

        $validator = Validator::make($data, [
            'equipo_jugador_id' => 'sometimes|exists:equipo_jugador,id',
            'motivo' => 'sometimes|string|max:255',
            'tipo_sancion' => 'sometimes|in:expulsión,advertencia,suspensión,multa',
            'cantidad_fechas' => 'nullable|integer|min:1',
            'fecha_inicio' => 'nullable|exists:fechas,id',
            'fecha_fin' => 'nullable|exists:fechas,id',
            'partido_id' => 'nullable|exists:partidos,id',
            'estado' => 'sometimes|in:activa,cumplida,apelada,anulada',
        ]);

        if ($validator->fails()) {
            return [
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ];
        }

        $sancion->update($data);

        return [
            'message' => 'Sanción actualizada correctamente',
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

    public function getSancionesPorZona($zonaId)
    {
        // Obtener los equipos de la zona
        $equipos = Zona::find($zonaId)?->equipos ?? collect();

        // Obtener los ids de equipo_jugador de todos los jugadores de esos equipos
        $equipoJugadorIds = \DB::table('equipo_jugador')
            ->whereIn('equipo_id', $equipos->pluck('id'))
            ->pluck('id');

        // Obtener las sanciones asociadas a esos equipo_jugador
        $sanciones = Sancion::with(['fechaInicio', 'fechaFin', 'partido'])
            ->whereIn('equipo_jugador_id', $equipoJugadorIds)
            ->get();

        // Adjuntar equipo y jugador a cada sanción
        $sanciones = $sanciones->map(function ($sancion) {
            $equipoJugador = \DB::table('equipo_jugador')->where('id', $sancion->equipo_jugador_id)->first();
            $equipo = $equipoJugador ? Equipo::find($equipoJugador->equipo_id) : null;
            $jugador = $equipoJugador ? Jugador::find($equipoJugador->jugador_id) : null;

            return [
                'sancion' => $sancion,
                'equipo' => $equipo,
                'jugador' => $jugador,
            ];
        });

        return [
            'zona_id' => $zonaId,
            'sanciones' => $sanciones,
            'status' => 200
        ];
    }
}