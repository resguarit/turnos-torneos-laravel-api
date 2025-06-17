<?php
// app/Services/Implementation/EquipoService.php

namespace App\Services\Implementation;

use App\Models\Equipo;
use App\Models\Jugador;
use App\Services\Interface\EquipoServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EquipoService implements EquipoServiceInterface
{
    public function getAll()
    {
        // Eager load 'jugadores' and 'zonas'
        return Equipo::with('jugadores', 'zonas')->get();
    }

    public function getById($id)
    {
        // Eager load 'jugadores' and 'zonas'
        return Equipo::with('jugadores', 'zonas')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => [
                'required',
                'string',
                'max:255',
                // Consider if uniqueness needs to be global or per tournament/zone
                // For global uniqueness:
                'unique:equipos,nombre',
                // For uniqueness within a specific context, validation needs adjustment
                // or handled differently (e.g., checking before creation based on context).
            ],
            'escudo' => 'nullable|string',
            // zona_id is no longer directly handled here for creation
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $equipo = Equipo::create([
            'nombre' => $request->nombre,
            'escudo' => $request->escudo
        ]);

        // Association with zona is typically handled in ZonaService or when adding teams to a zone

        return response()->json([
            'message' => 'Equipo creado correctamente',
            'equipo' => $equipo,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $equipo = Equipo::find($id);
        if (!$equipo) {
            return response()->json(['message' => 'Equipo no encontrado', 'status' => 404], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'unique:equipos,nombre,' . $id, // Unique check excluding self
            ],
            'escudo' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $equipo->update($request->only(['nombre', 'escudo']));

        return response()->json([
            'message' => 'Equipo actualizado correctamente',
            'equipo' => $equipo,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $equipo = Equipo::find($id);

        if (!$equipo) {
            return response()->json([
                'message' => 'Equipo no encontrado',
                'status' => 404
            ], 404);
        }

        // Detach relationships (optional, cascade might handle)
        $equipo->zonas()->detach();
        $equipo->jugadores()->detach(); // Detach players
        $equipo->delete();

        return response()->json([
            'message' => 'Equipo eliminado correctamente',
            'status' => 200
        ], 200);
    }

    public function getByZona($zonaId)
    {
        return Equipo::whereHas('zonas', function ($query) use ($zonaId) {
            $query->where('zonas.id', $zonaId);
        })->with('jugadores')->get(); // Load players for teams in the zone
    }

    public function getExcludeZona($zonaId)
    {
        return Equipo::whereDoesntHave('zonas', function ($query) use ($zonaId) {
            $query->where('zonas.id', $zonaId);
        })->with('jugadores')->get(); // Load players
    }

    public function desvincularJugadorDeEquipo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jugador_id' => 'required|exists:jugadores,id',
            'equipo_id' => 'required|exists:equipos,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $jugador_id = $request->jugador_id;
        $equipo_id = $request->equipo_id;

        $equipo = Equipo::find($equipo_id);
        if (!$equipo) {
            return response()->json(['message' => 'Equipo no encontrado', 'status' => 404], 404);
        }

        $jugador = Jugador::find($jugador_id);
        if (!$jugador) {
            return response()->json(['message' => 'Jugador no encontrado', 'status' => 404], 404);
        }

        if (!$equipo->jugadores()->where('jugadores.id', $jugador_id)->exists()) {
            return response()->json(['message' => 'Jugador no está vinculado a este equipo', 'status' => 404], 404);
        }

        // No permitir desvincular si es capitán
        $esCapitan = \DB::table('equipo_jugador')
            ->where('equipo_id', $equipo_id)
            ->where('jugador_id', $jugador_id)
            ->value('capitan');
        if ($esCapitan) {
            return response()->json([
                'message' => 'No se puede desvincular al capitán del equipo. Debe cambiar el capitán antes de desvincularlo.',
                'status' => 400
            ], 400);
        }

        $equipo->jugadores()->detach($jugador_id);

        return response()->json([
            'message' => 'Jugador desvinculado del equipo',
            'status' => 200
        ], 200);
    }

    public function getDosEquiposPorId($equipoId1, $equipoId2)
    {
        $equipos = Equipo::with(['jugadores', 'zonas'])
            ->whereIn('id', [$equipoId1, $equipoId2])
            ->get();

        // Para cada equipo, para cada jugador, agregar 'expulsado'
        $equipos = $equipos->map(function ($equipo) {
            $jugadores = $equipo->jugadores->map(function ($jugador) use ($equipo) {
                // Buscar en la tabla pivote el id de equipo_jugador
                $equipoJugador = \DB::table('equipo_jugador')
                    ->where('equipo_id', $equipo->id)
                    ->where('jugador_id', $jugador->id)
                    ->first();

                $expulsado = false;
                if ($equipoJugador) {
                    $expulsado = \App\Models\Sancion::whereIn('equipo_jugador_id', function($query) use ($jugador) {
        $query->select('id')
              ->from('equipo_jugador')
              ->where('jugador_id', $jugador->id);
    })
    ->where('tipo_sancion', \App\Enums\TipoSancion::EXPULSION_PERMANENTE->value)
    ->exists();
                }

                // Devolver los datos del jugador + expulsado
                $jugadorArray = $jugador->toArray();
                $jugadorArray['expulsado'] = $expulsado;
                return $jugadorArray;
            });

            $equipoArray = $equipo->toArray();
            $equipoArray['jugadores'] = $jugadores;
            return $equipoArray;
        });

        return response()->json([
            'equipos' => $equipos,
            'status' => 200
        ], 200);
    }
}