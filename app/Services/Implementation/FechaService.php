<?php
// app/Services/Implementation/FechaService.php

namespace App\Services\Implementation;

use App\Models\Fecha;
use App\Services\Interface\FechaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FechaService implements FechaServiceInterface
{
    public function getAll()
    {
        return Fecha::with('zona', 'partidos')->get();
    }

    public function getById($id)
    {
        return Fecha::with('zona', 'partidos')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
            'estado' => 'required|string|max:255',
            'zona_id' => 'required|exists:zonas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $fecha = Fecha::create($request->all());

        return response()->json([
            'message' => 'Fecha creada correctamente',
            'fecha' => $fecha,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $fecha = Fecha::find($id);

        if (!$fecha) {
            return response()->json([
                'message' => 'Fecha no encontrada',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
            'estado' => 'required|string|max:255',
            'zona_id' => 'required|exists:zonas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $fecha->update($request->all());

        return response()->json([
            'message' => 'Fecha actualizada correctamente',
            'fecha' => $fecha,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $fecha = Fecha::find($id);

        if (!$fecha) {
            return response()->json([
                'message' => 'Fecha no encontrada',
                'status' => 404
            ], 404);
        }

        $fecha->delete();

        return response()->json([
            'message' => 'Fecha eliminada correctamente',
            'status' => 200
        ], 200);
    }

    public function getByZona($zonaId)
    {
        return Fecha::where('zona_id', $zonaId)->with('partidos')->get();
    }
}