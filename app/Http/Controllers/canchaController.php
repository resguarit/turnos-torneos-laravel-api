<?php

namespace App\Http\Controllers;

use App\Models\Cancha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class CanchaController extends Controller
{
    public function index()
    {
        // $user = Auth::user();

        // abort_unless( $user->tokenCan('canchas:show') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        $canchas = Cancha::all();

        $data = [
            'canchas' => $canchas,
            'status' => 200
        ];

        return response()->json($data, 200);
    }

    public function show ($id){
        
       // $user = Auth::user();

        // abort_unless($user->tokenCan('cancha:showOne') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

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

    public function store(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->tokenCan('canchas:create') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $validator = Validator::make($request->all(), [
            'nro' => 'required|unique:canchas',
            'tipo_cancha' => 'required|max:200',
            'precio_por_hora' => 'required|numeric',
            'seña' => 'required|numeric', // Changed from senia to seña
            'activa' => 'required|boolean'
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
            'activa' => $request->activa
        ]);

        return response()->json([
            'message' => 'Cancha creada correctamente',
            'cancha' => $cancha,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        
        $user = Auth::user();

        abort_unless( $user->tokenCan('canchas:update') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        $cancha = Cancha::find($id);

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
            'tipo_cancha' => 'sometimes|max:200',
            'precio_por_hora' => 'sometimes|numeric',
            'seña' => 'sometimes|numeric', // Changed from senia to seña
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

        if($request->has('tipo_cancha')){
            $cancha->tipo_cancha = $request->tipo_cancha;
        }

        if($request->has('precio_por_hora')){
            $cancha->precio_por_hora = $request->precio_por_hora;
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
        $user = Auth::user();

        abort_unless( $user->tokenCan('canchas:destroy') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');
        
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
