<?php
// app/Services/Implementation/PartidoService.php

namespace App\Services\Implementation;

use App\Models\Partido;
use App\Services\Interface\PartidoServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PartidoService implements PartidoServiceInterface
{
    public function getAll()
    {
        return Partido::with('fecha', 'equipos', 'estadisticas', 'horario', 'cancha', 'ganador')->get();
    }

    public function getById($id)
    {
        return Partido::with('fecha', 'equipos', 'estadisticas', 'horario', 'cancha', 'ganador')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'horario_id' => 'required|exists:horarios,id',
            'cancha_id' => 'required|exists:canchas,id',
            'estado' => 'required|string|max:255',
            'marcador_local' => 'nullable|integer',
            'marcador_visitante' => 'nullable|integer',
            'ganador_id' => 'nullable|exists:equipos,id',
            'fecha_id' => 'required|exists:fechas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $partido = Partido::create($request->all());

        return response()->json([
            'message' => 'Partido creado correctamente',
            'partido' => $partido,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $partido = Partido::find($id);

        if (!$partido) {
            return response()->json([
                'message' => 'Partido no encontrado',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'horario_id' => 'required|exists:horarios,id',
            'cancha_id' => 'required|exists:canchas,id',
            'estado' => 'required|string|max:255',
            'marcador_local' => 'nullable|integer',
            'marcador_visitante' => 'nullable|integer',
            'ganador_id' => 'nullable|exists:equipos,id',
            'fecha_id' => 'required|exists:fechas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $partido->update($request->all());

        return response()->json([
            'message' => 'Partido actualizado correctamente',
            'partido' => $partido,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $partido = Partido::find($id);

        if (!$partido) {
            return response()->json([
                'message' => 'Partido no encontrado',
                'status' => 404
            ], 404);
        }

        $partido->delete();

        return response()->json([
            'message' => 'Partido eliminado correctamente',
            'status' => 200
        ], 200);
    }

    public function getByFecha($fechaId)
    {
        return Partido::where('fecha_id', $fechaId)->with('equipos', 'estadisticas', 'horario', 'cancha', 'ganador')->get();
    }

    public function getByEquipo($equipoId)
    {
        return Partido::whereHas('equipos', function ($query) use ($equipoId) {
            $query->where('equipo_id', $equipoId);
        })->with('fecha', 'estadisticas', 'horario', 'cancha', 'ganador')->get();
    }
}