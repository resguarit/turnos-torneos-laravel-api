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
        return Equipo::with('jugadores', 'zonas')->get();
    }

    public function getById($id)
    {
        return Equipo::with('jugadores', 'zonas')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    $equipoExistente = Equipo::where('nombre', $value)->exists();
                    if ($equipoExistente) {
                        $fail('El nombre del equipo ya existe.');
                    }
                },
            ],
            'escudo' => 'nullable|string',
            'zona_id' => 'nullable|exists:zonas,id', // Validar que la zona exista si se proporciona
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

        // Asociar el equipo a la zona si se proporciona zona_id
        if ($request->has('zona_id')) {
            $equipo->zonas()->attach($request->zona_id); // Crear el registro en la tabla pivote equipo_zona
        }

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
            return response()->json([
                'message' => 'Equipo no encontrado',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($request, $id) {
                    if ($request->input('zona_id')) {
                        $equiposEnZona = Equipo::whereHas('zonas', function ($query) use ($request, $id) {
                            $query->where('zonas.id', $request->input('zona_id'));
                        })->where('nombre', $value)
                          ->where('id', '!=', $id)
                          ->exists();
                        
                        if ($equiposEnZona) {
                            $fail('El nombre del equipo ya existe en esta zona.');
                        }
                    }
                },
            ],
            'escudo' => 'nullable|string',
            'zona_id' => 'nullable|exists:zonas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $equipo->update([
            'nombre' => $request->nombre,
            'escudo' => $request->escudo
        ]);

        // Actualizar la zona si se proporciona
        if ($request->has('zona_id')) {
            $equipo->zonas()->attach($request->zona_id);
        }

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

        // Eliminar las relaciones con zonas antes de eliminar el equipo
        $equipo->zonas()->detach();
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
        })->with('jugadores')->get();
    }

    public function getExcludeZona($zonaId)
    {
        return Equipo::whereDoesntHave('zonas', function ($query) use ($zonaId) {
            $query->where('zonas.id', $zonaId);
        })->with('jugadores')->get();
    }
}