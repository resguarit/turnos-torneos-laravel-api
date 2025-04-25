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

    public function createOrUpdateMultiple(Request $request, $partidoId)
    {
        $validator = Validator::make($request->all(), [
            'estadisticas' => 'required|array',
            'estadisticas.*.nro_camiseta' => 'required|integer|min:1',
            'estadisticas.*.goles' => 'nullable|integer|min:0',
            'estadisticas.*.asistencias' => 'nullable|integer|min:0',
            'estadisticas.*.rojas' => 'nullable|integer|min:0',
            'estadisticas.*.amarillas' => 'nullable|integer|min:0',
            'estadisticas.*.jugador_id' => 'required|exists:jugadores,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación de una o más estadísticas',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $estadisticasData = $request->input('estadisticas');
        $jugadorIdsEnviados = array_column($estadisticasData, 'jugador_id');
        $results = [];

        $jugadorIdsExistentes = Estadistica::where('partido_id', $partidoId)
                                          ->pluck('jugador_id')
                                          ->toArray();

        DB::beginTransaction();
        try {
            foreach ($estadisticasData as $data) {
                $estadistica = Estadistica::updateOrCreate(
                    [
                        'partido_id' => $partidoId,
                        'jugador_id' => $data['jugador_id']
                    ],
                    [
                        'nro_camiseta' => $data['nro_camiseta'],
                        'goles' => $data['goles'] ?? 0,
                        'asistencias' => $data['asistencias'] ?? 0,
                        'amarillas' => $data['amarillas'] ?? 0,
                        'rojas' => $data['rojas'] ?? 0,
                    ]
                );
                $results[] = $estadistica;
            }

            $jugadorIdsParaBorrar = array_diff($jugadorIdsExistentes, $jugadorIdsEnviados);

            if (!empty($jugadorIdsParaBorrar)) {
                Estadistica::where('partido_id', $partidoId)
                           ->whereIn('jugador_id', $jugadorIdsParaBorrar)
                           ->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Estadísticas procesadas correctamente',
                'estadisticas_actualizadas' => $results,
                'jugadores_eliminados' => $jugadorIdsParaBorrar,
                'status' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al procesar las estadísticas',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
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