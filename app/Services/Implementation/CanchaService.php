<?php

namespace App\Services\Implementation;

use App\Models\Cancha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\Interface\CanchaServiceInterface;

class CanchaService implements CanchaServiceInterface
{
    public function getCanchas()
    {
        $canchas = Cancha::all();

        return response()->json([
            'canchas' => $canchas,
            'status' => 200
        ], 200);
    }

    public function showCancha($id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer|exists:canchas,id'
        ]);

        if($validator->fails()){
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $canchaToShow = Cancha::find($id);

        if (!$canchaToShow) {
            return response()->json([
                'message' => 'Cancha no encontrada',
                'status' => 404
            ], 404);
        }

        return response()->json([
            'cancha' => $canchaToShow,
            'status' => 200
        ], 200);
    }

    public function storeCancha(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nro' => 'required|unique:canchas',
            'tipo_cancha' => 'required|max:200',
            'precio_por_hora' => 'required|numeric',
            'seña' => 'required|numeric',
            'activa' => 'required|boolean',
            'descripcion' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validacion',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $cancha = Cancha::create([
            'nro' => $request->nro,
            'tipo_cancha' => $request->tipo_cancha,
            'precio_por_hora' => $request->precio_por_hora,
            'seña' => $request->seña,
            'activa' => $request->activa,
            'descripcion' => $request->descripcion
        ]);

        return response()->json([
            'message' => 'Cancha creada correctamente',
            'cancha' => $cancha,
            'status' => 201
        ], 201);
    }

    public function updateCancha(Request $request, $id)
    {
        $cancha = Cancha::find($id);

        if (!$cancha) {
            return response()->json([
                'message' => 'No hay cancha encontrada',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nro' => 'sometimes|unique:canchas,nro,' . $id,
            'tipo_cancha' => 'sometimes|max:200',
            'precio_por_hora' => 'sometimes|numeric',
            'seña' => 'sometimes|numeric',
            'activa' => 'sometimes|boolean',
            'descripcion' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validacion',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        if($request->has('nro')){
            $cancha->nro = $request->nro;
        }

        if($request->has('tipo_cancha')){
            $cancha->tipo_cancha = $request->tipo_cancha;
        }

        if($request->has('precio_por_hora')){
            $cancha->precio_por_hora = $request->precio_por_hora;
        }

        if($request->has('seña')){
            $cancha->seña = $request->seña;
        }

        if($request->has('activa')){
            $cancha->activa = $request->activa;
        }

        if($request->has('descripcion')){
            $cancha->descripcion = $request->descripcion;
        }

        $cancha->save();

        return response()->json([
            'message' => 'Cancha actualizada correctamente',
            'cancha' => $cancha,
            'status' => 200
        ], 200);
    }

    public function deleteCancha($id)
    {
        try {
            $cancha = Cancha::findOrFail($id);
            $cancha->delete();

            return response()->json([
                'message' => 'Cancha eliminada correctamente',
                'status' => 200
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Cancha no encontrada',
                'status' => 404
            ], 404);
        }
    }
}