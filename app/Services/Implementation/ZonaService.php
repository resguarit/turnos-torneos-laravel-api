<?php
// app/Services/Implementation/ZonaService.php

namespace App\Services\Implementation;

use App\Models\Zona;
use App\Services\Interface\ZonaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ZonaService implements ZonaServiceInterface
{
    public function getAll()
    {
        return Zona::with('equipos', 'fechas')->get();
    }

    public function getById($id)
    {
        return Zona::with('equipos', 'fechas')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'formato' => 'required|string|max:255',
            'año' => 'required|integer',
            'torneo_id' => 'required|exists:torneos,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $zona = Zona::create($request->all());

        return response()->json([
            'message' => 'Zona creada correctamente',
            'zona' => $zona,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $zona = Zona::find($id);

        if (!$zona) {
            return response()->json([
                'message' => 'Zona no encontrada',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'formato' => 'required|string|max:255',
            'año' => 'required|integer',
            'torneo_id' => 'required|exists:torneos,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $zona->update($request->all());

        return response()->json([
            'message' => 'Zona actualizada correctamente',
            'zona' => $zona,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $zona = Zona::find($id);

        if (!$zona) {
            return response()->json([
                'message' => 'Zona no encontrada',
                'status' => 404
            ], 404);
        }

        $zona->delete();

        return response()->json([
            'message' => 'Zona eliminada correctamente',
            'status' => 200
        ], 200);
    }
}