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
        return Equipo::with('jugadores')->get();
    }

    public function getById($id)
    {
        return Equipo::with('jugadores')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($request) {
                    $exists = Equipo::where('nombre', $value)
                        ->where('zona_id', $request->input('zona_id'))
                        ->exists();
                    if ($exists) {
                        $fail('El nombre del equipo ya existe en esta zona.');
                    }
                },
            ],
            'escudo' => 'nullable|string',
            'zona_id' => 'required|exists:zonas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $equipo = Equipo::create($request->all());

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
                    $exists = Equipo::where('nombre', $value)
                        ->where('zona_id', $request->input('zona_id'))
                        ->where('id', '!=', $id)
                        ->exists();
                    if ($exists) {
                        $fail('El nombre del equipo ya existe en esta zona.');
                    }
                },
            ],
            'escudo' => 'nullable|string',
            'zona_id' => 'required|exists:zonas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $equipo->update($request->all());

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

        $equipo->delete();

        return response()->json([
            'message' => 'Equipo eliminado correctamente',
            'status' => 200
        ], 200);
    }

    public function getByZona($zonaId)
    {
        return Equipo::where('zona_id', $zonaId)->with('jugadores')->get();
    }
}