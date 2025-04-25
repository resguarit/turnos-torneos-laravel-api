<?php
// app/Services/Implementation/EstadisticaService.php

namespace App\Services\Implementation;

use App\Models\Estadistica;
use App\Models\Jugador;
use App\Services\Interface\EstadisticaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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
            'nro_camiseta' => 'nullable|integer',
            'goles' => 'nullable|integer',
            'asistencias' => 'nullable|integer',
            'rojas' => 'nullable|integer',
            'amarillas' => 'nullable|integer',
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
            'nro_camiseta' => 'nullable|integer',
            'goles' => 'sometimes|integer',
            'asistencias' => 'sometimes|integer',
            'rojas' => 'sometimes|integer',
            'amarillas' => 'sometimes|integer',
            'partido_id' => 'sometimes|exists:partidos,id',
            'jugador_id' => 'sometimes|exists:jugadores,id',
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

    public function getJugadoresStatsByZona($zonaId)
    {
        $stats = Estadistica::select(
            'jugador_id',
            DB::raw('SUM(goles) as total_goles'),
            DB::raw('SUM(amarillas) as total_amarillas'),
            DB::raw('SUM(rojas) as total_rojas')
        )
        ->whereHas('partido.fecha', function ($query) use ($zonaId) {
            $query->where('zona_id', $zonaId);
        })
        ->groupBy('jugador_id')
        ->with(['jugador' => function ($query) {
            $query->select('id', 'nombre', 'apellido', 'equipo_id') // Select necessary fields
                  ->with(['equipo' => function ($query) {
                      $query->select('id', 'nombre'); // Select necessary fields from equipo
                  }]);
        }])
        ->get();

        // Goleadores
        $goleadores = $stats->filter(function ($item) {
            return $item->total_goles > 0;
        })->map(function ($item) {
            return [
                'nombre_completo' => $item->jugador->nombre . ' ' . $item->jugador->apellido,
                'equipo' => $item->jugador->equipo->nombre ?? 'Sin equipo',
                'goles' => (int) $item->total_goles,
            ];
        })->sortByDesc('goles')->values();

        // Amonestados
        $amonestados = $stats->filter(function ($item) {
            return $item->total_amarillas > 0;
        })->map(function ($item) {
            return [
                'nombre_completo' => $item->jugador->nombre . ' ' . $item->jugador->apellido,
                'equipo' => $item->jugador->equipo->nombre ?? 'Sin equipo',
                'amarillas' => (int) $item->total_amarillas,
            ];
        })->sortByDesc('amarillas')->values();

        // Expulsados
        $expulsados = $stats->filter(function ($item) {
            return $item->total_rojas > 0;
        })->map(function ($item) {
            return [
                'nombre_completo' => $item->jugador->nombre . ' ' . $item->jugador->apellido,
                'equipo' => $item->jugador->equipo->nombre ?? 'Sin equipo',
                'rojas' => (int) $item->total_rojas,
            ];
        })->sortByDesc('rojas')->values();


        return response()->json([
            'goleadores' => $goleadores,
            'amonestados' => $amonestados,
            'expulsados' => $expulsados,
        ], 200);
    }
}