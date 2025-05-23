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
        return Estadistica::with('partido', 'jugador.equipos')->get();
    }

    public function getById($id)
    {
        return Estadistica::with('partido', 'jugador.equipos')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'partido_id' => 'required|exists:partidos,id',
            'jugador_id' => 'required|exists:jugadores,id',
            'nro_camiseta' => 'required|integer|min:1', // Assuming jersey numbers are between 1 and 99
            'goles' => 'required|integer|min:0',
            'amarillas' => 'required|integer|min:0|max:2', // Assuming max 2 yellows
            'rojas' => 'required|integer|min:0|max:1',     // Assuming max 1 red
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
            'estadistica' => $estadistica->load('jugador.equipos'), // Load relation
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
            'partido_id' => 'sometimes|required|exists:partidos,id',
            'jugador_id' => 'sometimes|required|exists:jugadores,id',
            'nro_camiseta' => 'sometimes|required|integer|min:1',
            'goles' => 'sometimes|required|integer|min:0',
            'amarillas' => 'sometimes|required|integer|min:0|max:2',
            'rojas' => 'sometimes|required|integer|min:0|max:1',
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
            'estadistica' => $estadistica->load('jugador.equipos'), // Load relation
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
        // Include player's teams in the response
        return Estadistica::where('partido_id', $partidoId)->with('jugador.equipos')->get();
    }

    public function getByEquipo($equipoId)
    {
        // Find statistics for players who are part of the specified team
        return Estadistica::whereHas('jugador.equipos', function ($query) use ($equipoId) {
            $query->where('equipos.id', $equipoId);
        })->with(['partido', 'jugador.equipos'])->get();
    }

    public function getByJugador($jugadorId)
    {
        // Include player's teams
        return Estadistica::where('jugador_id', $jugadorId)->with(['partido', 'jugador.equipos'])->get();
    }

    public function getByZona($zonaId)
    {
        // Find statistics where the related match's date belongs to the zone
        // Include player's teams, potentially filtered by the zone context if needed
        return Estadistica::whereHas('partido.fecha', function ($query) use ($zonaId) {
            $query->where('zona_id', $zonaId);
        })->with(['partido', 'jugador.equipos' => function($q) use ($zonaId) {
             // Load only the team(s) relevant to this zone for the player
             $q->whereHas('zonas', function($zq) use ($zonaId) {
                $zq->where('zonas.id', $zonaId);
             });
        }])->get();
    }

    public function createOrUpdateMultiple(Request $request, $partidoId)
    {
        $validator = Validator::make($request->all(), [
            'estadisticas' => 'required|array',
            'estadisticas.*.jugador_id' => 'required|exists:jugadores,id',
            'estadisticas.*.nro_camiseta' => 'required|integer|min:1',
            'estadisticas.*.goles' => 'required|integer|min:0',
            'estadisticas.*.amarillas' => 'required|integer|min:0|max:2',
            'estadisticas.*.rojas' => 'required|integer|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
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
                        'goles' => $data['goles'],
                        'amarillas' => $data['amarillas'],
                        'rojas' => $data['rojas'],
                    ]
                );
                $results[] = $estadistica->load('jugador.equipos'); // Load relation
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
        // Eager load jugador and their teams, filtering teams by the current zone
        ->with(['jugador' => function ($query) use ($zonaId) {
            $query->select('id', 'nombre', 'apellido') // Removed equipo_id
                  ->with(['equipos' => function ($q) use ($zonaId) {
                      $q->select('equipos.id', 'equipos.nombre') // Select necessary fields from equipo
                        ->whereHas('zonas', function($zq) use ($zonaId){
                            $zq->where('zonas.id', $zonaId); // Filter teams by zone
                        });
                  }]);
        }])
        ->get();

        // Helper function to get the team name for the current zone context
        $getTeamName = function ($jugador) {
            // Since we filtered eager loading, there should ideally be only one team.
            // Add fallback logic if needed.
            return $jugador->equipos->first()->nombre ?? 'Equipo Desconocido';
        };


        // Goleadores
        $goleadores = $stats->filter(function ($item) {
            // Ensure player and team data is loaded before accessing
            return $item->total_goles > 0 && $item->jugador && $item->jugador->equipos->isNotEmpty();
        })->map(function ($item) use ($getTeamName) {
            return [
                'nombre_completo' => $item->jugador->nombre . ' ' . $item->jugador->apellido,
                'equipo' => $getTeamName($item->jugador), // Use helper
                'goles' => (int) $item->total_goles,
            ];
        })->sortByDesc('goles')->values();

        // Amonestados
        $amonestados = $stats->filter(function ($item) {
             return $item->total_amarillas > 0 && $item->jugador && $item->jugador->equipos->isNotEmpty();
        })->map(function ($item) use ($getTeamName) {
            return [
                'nombre_completo' => $item->jugador->nombre . ' ' . $item->jugador->apellido,
                'equipo' => $getTeamName($item->jugador), // Use helper
                'amarillas' => (int) $item->total_amarillas,
            ];
        })->sortByDesc('amarillas')->values();

        // Expulsados
        $expulsados = $stats->filter(function ($item) {
             return $item->total_rojas > 0 && $item->jugador && $item->jugador->equipos->isNotEmpty();
        })->map(function ($item) use ($getTeamName) {
            return [
                'nombre_completo' => $item->jugador->nombre . ' ' . $item->jugador->apellido,
                'equipo' => $getTeamName($item->jugador), // Use helper
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