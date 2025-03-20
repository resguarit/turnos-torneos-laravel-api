<?php
// app/Services/Implementation/DeporteService.php

namespace App\Services\Implementation;

use App\Models\Deporte;
use App\Services\Interface\DeporteServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeporteService implements DeporteServiceInterface
{
    public function getAll()
    {
        return Deporte::all();
    }

    public function getById($id)
    {
        return Deporte::find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $deporte = Deporte::create($request->all());

        return response()->json([
            'message' => 'Deporte creado correctamente',
            'deporte' => $deporte,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $deporte = Deporte::find($id);

        if (!$deporte) {
            return response()->json([
                'message' => 'Deporte no encontrado',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $deporte->update($request->all());

        return response()->json([
            'message' => 'Deporte actualizado correctamente',
            'deporte' => $deporte,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $deporte = Deporte::find($id);

        if (!$deporte) {
            return response()->json([
                'message' => 'Deporte no encontrado',
                'status' => 404
            ], 404);
        }

        $deporte->delete();

        return response()->json([
            'message' => 'Deporte eliminado correctamente',
            'status' => 200
        ], 200);
    }
}