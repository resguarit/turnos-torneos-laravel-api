<?php
// app/Services/Implementation/EquipoService.php

namespace App\Services\Implementation;

use App\Models\Equipo;
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
}