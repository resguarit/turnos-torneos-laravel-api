<?php

namespace App\Http\Controllers;

use App\Models\Horario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class horarioController extends Controller
{

    public function index()
    {
        $horarios = Horario::all();
        
        $data = [

            'horarios' => $horarios,
            'status' => 200

        ];

        return response()->json($data,200);
    }

    public function store(Request $request)
    {
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