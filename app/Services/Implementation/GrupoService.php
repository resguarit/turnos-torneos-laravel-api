<?php
// app/Services/Implementation/GrupoService.php

namespace App\Services\Implementation;

use App\Models\Grupo;
use App\Models\Zona;
use App\Services\Interface\GrupoServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GrupoService implements GrupoServiceInterface
{
    public function getAll()
    {
        return Grupo::with('equipos', 'zona')->get();
    }

    public function getById($id)
    {
        return Grupo::with('equipos', 'zona')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'zona_id' => 'required|exists:zonas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $grupo = Grupo::create($request->all());

        return response()->json([
            'message' => 'Grupo creado correctamente',
            'grupo' => $grupo,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $grupo = Grupo::find($id);

        if (!$grupo) {
            return response()->json([
                'message' => 'Grupo no encontrado',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'zona_id' => 'required|exists:zonas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $grupo->update($request->all());

        return response()->json([
            'message' => 'Grupo actualizado correctamente',
            'grupo' => $grupo,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $grupo = Grupo::find($id);

        if (!$grupo) {
            return response()->json([
                'message' => 'Grupo no encontrado',
                'status' => 404
            ], 404);
        }

        $grupo->delete();

        return response()->json([
            'message' => 'Grupo eliminado correctamente',
            'status' => 200
        ], 200);
    }

    public function getByZona($zonaId)
    {
        return Grupo::where('zona_id', $zonaId)->with('equipos')->get();
    }
}