<?php

namespace App\Http\Controllers;

use App\Models\Horario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class HorarioController extends Controller
{

    public function index()
    {
        // $user = Auth::user();

        // abort_unless( $user->tokenCan('horarios:show') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');
        
        $horarios = Horario::all();
        
        $data = [

            'horarios' => $horarios,
            'status' => 200

        ];

        return response()->json($data,200);
    }

    public function store(Request $request)
    {
        // $user = Auth::user();

        // abort_unless( $user->tokenCan('horarios:create') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        $validator = Validator::make($request->all(), [
            'hora_inicio' => 'required|date_format:H:i|unique:horarios,hora_inicio',  
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio|unique:horarios,hora_fin',
            'dia' => 'requiered|in:l,m,x,j,v,s,d',
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
            'hora_inicio' => $request->hora_inicio,
            'hora_fin' => $request->hora_fin,
            'dia'=> $request->dia,
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
        // $user = Auth::user();

        // abort_unless( $user->tokenCan('horarios:showOne') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

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
        // $user = Auth::user();

        // abort_unless( $user->tokenCan('horarios:delete') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

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

    public function getPorDiaSemana(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            $data = [
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 422
            ];
            return response()->json($data, 422);
        }

        $fecha = Carbon::createFromFormat('Y-m-d', $request->fecha);
        $diaSemana = $this->getNombreDiaSemana($fecha->dayOfWeek);

        $horarios = Horario::where('dia', $diaSemana)->where('activo', true)->get();

        $data = [
            'horarios' => $horarios,
            'status' => 200
        ];

        return response()->json($data, 200);
    }

    private function getNombreDiaSemana($diaSemana)
    {
        $dias = [
            0 => 'domingo',
            1 => 'lunes',
            2 => 'martes',
            3 => 'miércoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sábado'
        ];

        return $dias[$diaSemana];
    }
}