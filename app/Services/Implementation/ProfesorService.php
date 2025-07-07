<?php

namespace App\Services\Implementation;

use App\Models\Profesor;
use App\Services\Interface\ProfesorServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfesorService implements ProfesorServiceInterface
{
    public function getAll()
    {
        return Profesor::all();
    }

    public function getById($id)
    {
        return Profesor::find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'dni' => 'required|string|max:20|unique:profesores,dni',
            'telefono' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $profesor = Profesor::create($request->all());

        return response()->json([
            'message' => 'Profesor creado correctamente',
            'profesor' => $profesor,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $profesor = Profesor::find($id);

        if (!$profesor) {
            return response()->json([
                'message' => 'Profesor no encontrado',
                'status' => 404
            ], 404);
        }

        $profesor->update($request->all());

        return response()->json([
            'message' => 'Profesor actualizado correctamente',
            'profesor' => $profesor,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $profesor = Profesor::find($id);

        if (!$profesor) {
            return response()->json([
                'message' => 'Profesor no encontrado',
                'status' => 404
            ], 404);
        }

        $profesor->delete();

        return response()->json([
            'message' => 'Profesor eliminado correctamente',
            'status' => 200
        ], 200);
    }
}