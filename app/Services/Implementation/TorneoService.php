<?php
// app/Services/Implementation/TorneoService.php

namespace App\Services\Implementation;

use App\Models\Torneo;
use App\Services\Interface\TorneoServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TorneoService implements TorneoServiceInterface
{
    public function getAll()
{
    return Torneo::with([
        'deporte',
        'zonas.equipos',
        'zonas.fechas.partidos',
        'zonas.grupos.equipos',
    ])->get();
}

    public function getById($id)
    {
        return Torneo::with('deporte')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'año' => 'required|integer',
            'deporte_id' => 'required|exists:deportes,id',
            'precio_inscripcion' => 'required|numeric|min:0',
            'precio_por_fecha' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $torneo = Torneo::create($request->all());

        return response()->json([
            'message' => 'Torneo creado correctamente',
            'torneo' => $torneo,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $torneo = Torneo::find($id);

        if (!$torneo) {
            return response()->json([
                'message' => 'Torneo no encontrado',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255',
            'año' => 'sometimes|integer',
            'deporte_id' => 'sometimes|exists:deportes,id',
            'precio_inscripcion' => 'sometimes|numeric|min:0',
            'precio_por_fecha' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $torneo->update($request->all());

        return response()->json([
            'message' => 'Torneo actualizado correctamente',
            'torneo' => $torneo,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $torneo = Torneo::find($id);

        if (!$torneo) {
            return response()->json([
                'message' => 'Torneo no encontrado',
                'status' => 404
            ], 404);
        }

        $torneo->delete();

        return response()->json([
            'message' => 'Torneo eliminado correctamente',
            'status' => 200
        ], 200);
    }
}