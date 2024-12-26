<?php

namespace App\Http\Controllers;

use App\Models\Cancha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CanchaController extends Controller
{
    public function index()
    {
        $canchas = Cancha::all();

        $data = [
            'canchas' => $canchas,
            'status' => 200
        ];

        return response()->json($data, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nro' => 'required|unique:canchas',
            'tipoCancha' => 'required|max:200',
            'precioPorHora' => 'required',
            'activa' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            $data = [
                'message' => 'Error en la validacion',
                'errors' => $validator->errors(),
                'status' => 400
            ];
            return response()->json($data, 400);
        }

        $cancha = Cancha::create([
            'nro' => $request->nro,
            'tipoCancha' => $request->tipoCancha,
            'precioPorHora' => $request->precioPorHora
        ]);

        if (!$cancha) {
            $data = [
                'message' => 'Error al crear la cancha',
                'status' => 500
            ];
            return response()->json($data, 500);
        }

        $data = [
            'message' => 'Cancha creada correctamente',
            'cancha' => $cancha,
            'status' => 201
        ];

        return response()->json($data, 201);
    }

    public function update(Request $request, $id)
    {
        // Encontrar la cancha por su ID
        $cancha = Cancha::find($id);

        // Verificar si la cancha existe
        if (!$cancha) {
            $data = [
                'message' => 'No hay cancha encontrada',
                'status' => 404
            ];
            return response()->json($data, 404);
        }

        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'nro' => 'sometimes|unique:canchas,nro,' . $id,
            'tipoCancha' => 'sometimes|max:200',
            'precioPorHora' => 'sometimes|numeric',
            'activa' => 'sometimes|boolean'
        ]);

        // Manejar errores de validación
        if ($validator->fails()) {
            $data = [
                'message' => 'Error en la validacion',
                'errors' => $validator->errors(),
                'status' => 400
            ];
            return response()->json($data, 400);
        }

        // Actualizar los campos de la cancha
        if($request->has('nro')){
            $cancha->nro = $request->nro;
        }

        if($request->has('tipoCancha')){
            $cancha->tipoCancha = $request->tipoCancha;
        }

        if($request->has('precioPorHora')){
            $cancha->precioPorHora = $request->precioPorHora;
        }

        if($request->has('activa')){
            $cancha->activa = $request->activa;
        }

        // Guardar los cambios en la base de datos
        $cancha->save();

        // Respuesta exitosa
        $data = [
            'message' => 'Cancha actualizada correctamente',
            'cancha' => $cancha,
            'status' => 200
        ];

        return response()->json($data, 200);
    }

    public function destroy($id)
    {
        try {
            $cancha = Cancha::findOrFail($id);
            $cancha->delete();

            $data = [
                'message' => 'Cancha eliminada correctamente',
                'status' => 200
            ];

            return response()->json($data, 200);

        } catch (ModelNotFoundException $e) {
            $data = [
                'message' => 'Cancha no encontrada',
                'status' => 404
            ];
            return response()->json($data, 404);
        }
    }
}
