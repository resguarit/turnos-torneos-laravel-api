<?php

namespace App\Http\Controllers;

use App\Models\Horario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class horarioController extends Controller
{

    public function index()
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('horarios:show') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');
        
        $horarios = Horario::all();
        
        $data = [

            'horarios' => $horarios,
            'status' => 200

        ];

        return response()->json($data,200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('horarios:create') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        $validator = Validator::make($request->all(), [
            'horaInicio' => 'required|date_format:H:i|unique:horarios,horaInicio',  
            'horaFin' => 'required|date_format:H:i|after:horaInicio|unique:horarios,horaFin',
            'activo' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            $data = [
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 422
            ];
            return response()->json($data, 422);
        }

        $horario = Horario::create([
            'horaInicio' => $request->horaInicio,
            'horaFin' => $request->horaFin,
            'activo' => $request->activo,
        ]);

        if (!$horario) {
            $data = [
                'message' => 'Error al crear el horario',
                'status' => 500
            ];
            return response()->json($data, 500);
        }
        $data = [
            'message' => 'Horario creado correctamente',
            'horario' => $horario,
            'status' => 201
        ];

        return response()->json($data, 201);
    }

    public function show($id)
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('horarios:showOne') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        try {
            $horario = Horario::findOrFail($id);

            $data = [
                'horario' => $horario,
                'status' => 200
            ];
            return response()->json($data, 200);
        } catch (ModelNotFoundException $e) {
            $data = [
                'message' => 'Horario no encontrado',
                'status' => 404
            ];
            return response()->json($data, 404);
        }
    }
    
    

    public function destroy($id)
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('horarios:delete') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        try {
            $horario = Horario::findOrFail($id);
            $horario->delete();

            $data = [
                'message' => 'Horario eliminado correctamente',
                'status' => 200
            ];
            return response()->json($data, 200);
        } catch (ModelNotFoundException $e) {
            $data = [
                'message' => 'Horario no encontrado',
                'status' => 404
            ];
            return response()->json($data, 404);
        }
    }
}