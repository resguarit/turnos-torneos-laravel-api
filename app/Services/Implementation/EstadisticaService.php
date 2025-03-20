<?php
// app/Services/Implementation/EstadisticaService.php

namespace App\Services\Implementation;

use App\Models\Estadistica;
use App\Models\Jugador;
use App\Services\Interface\EstadisticaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EstadisticaService implements EstadisticaServiceInterface
{
    public function getAll()
    {
        return Estadistica::with('partido', 'jugador')->get();
    }

    public function getById($id)
    {
        return Estadistica::with('partido', 'jugador')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'goles' => 'required|integer',
            'asistencias' => 'required|integer',
            'rojas' => 'required|integer',
            'amarillas' => 'required|integer',
            'partido_id' => 'required|exists:partidos,id',
            'jugador_id' => 'required|exists:jugadores,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $estadistica = Estadistica::create($request->all());

        return response()->json([
            'message' => 'Estadística creada correctamente',
            'estadistica' => $estadistica,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $estadistica = Estadistica::find($id);

        if (!$estadistica) {
            return response()->json([
                'message' => 'Estadística no encontrada',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'goles' => 'required|integer',
            'asistencias' => 'required|integer',
            'rojas' => 'required|integer',
            'amarillas' => 'required|integer',
            'partido_id' => 'required|exists:partidos,id',
            'jugador_id' => 'required|exists:jugadores,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $estadistica->update($request->all());

        return response()->json([
            'message' => 'Estadística actualizada correctamente',
            'estadistica' => $estadistica,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $estadistica = Estadistica::find($id);

        if (!$estadistica) {
            return response()->json([
                'message' => 'Estadística no encontrada',
                'status' => 404
            ], 404);
        }

        $estadistica->delete();

        return response()->json([
            'message' => 'Estadística eliminada correctamente',
            'status' => 200
        ], 200);
    }

    public function getByPartido($partidoId)
    {
        return Estadistica::where('partido_id', $partidoId)->with('jugador')->get();
    }

    public function getByEquipo($equipoId)
    {
        return Estadistica::whereHas('jugador', function ($query) use ($equipoId) {
            $query->where('equipo_id', $equipoId);
        })->with('partido', 'jugador')->get();
    }

    public function getByJugador($jugadorId)
    {
        return Estadistica::where('jugador_id', $jugadorId)->with('partido')->get();
    }

    public function getByZona($zonaId)
    {
        return Estadistica::whereHas('partido.fecha', function ($query) use ($zonaId) {
            $query->where('zona_id', $zonaId);
        })->with('partido', 'jugador')->get();
    }
}