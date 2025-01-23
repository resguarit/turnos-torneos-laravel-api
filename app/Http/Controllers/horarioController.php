<?php

namespace App\Http\Controllers;

use App\Models\Horario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class horarioController extends Controller
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

    public function deshabilitarFranjaHoraria(Request $request)
    {
        $user = Auth::user();
        $diasValidos = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    
        abort_unless(
            $user->tokenCan('horarios:update') || 
            $user->rol === 'admin',
            403, 
            'No tienes permisos para realizar esta acción'
        );
    
        $validator = Validator::make($request->all(), [
            'dia' => ['required', 'string', Rule::in($diasValidos)],
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }
    
        try {
            DB::beginTransaction();
            
            $horaInicio = Carbon::createFromFormat('H:i', $request->hora_inicio);
            $horaFin = Carbon::createFromFormat('H:i', $request->hora_fin);
    
            // Manejar el caso en que la hora de fin es "00:00"
            if ($horaFin->eq(Carbon::createFromFormat('H:i', '00:00'))) {
                $horaFin = $horaFin->addDay();
            }
    
            // Verificar si ya existe una franja horaria deshabilitada en el rango especificado
            $horariosDeshabilitados = Horario::where('dia', $request->dia)
                ->where('activo', false)
                ->where(function($query) use ($horaInicio, $horaFin) {
                    $query->whereBetween('hora_inicio', [$horaInicio->format('H:i:s'), $horaFin->format('H:i:s')])
                          ->orWhereBetween('hora_fin', [$horaInicio->format('H:i:s'), $horaFin->format('H:i:s')])
                          ->orWhere(function($query) use ($horaInicio, $horaFin) {
                              $query->where('hora_inicio', '<', $horaFin->format('H:i:s'))
                                    ->where('hora_fin', '>', $horaInicio->format('H:i:s'));
                          });
                })
                ->get();
    
            if (!$horariosDeshabilitados->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Ya existe una franja horaria deshabilitada en ese rango para el día especificado',
                    'horarios' => $horariosDeshabilitados,
                    'status' => 409
                ], 409);
            }
    
            // Deshabilitar los horarios en el rango especificado
            $horarios = Horario::where('dia', $request->dia)
                ->where(function($query) use ($horaInicio, $horaFin) {
                    $query->whereBetween('hora_inicio', [$horaInicio->format('H:i:s'), $horaFin->format('H:i:s')])
                          ->orWhereBetween('hora_fin', [$horaInicio->format('H:i:s'), $horaFin->format('H:i:s')])
                          ->orWhere(function($query) use ($horaInicio, $horaFin) {
                              $query->where('hora_inicio', '<', $horaFin->format('H:i:s'))
                                    ->where('hora_fin', '>', $horaInicio->format('H:i:s'));
                          });
                })
                ->get();
    
            if ($horarios->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No se encontraron horarios en ese rango para el día especificado',
                    'status' => 404
                ], 404);
            }
    
            foreach ($horarios as $horario) {
                $horario->activo = false;
                $horario->save();
            }
    
            DB::commit();
    
            return response()->json([
                'message' => 'Franja horaria deshabilitada correctamente para el día ' . $request->dia,
                'horarios' => $horarios,
                'status' => 200
            ], 200);
    
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al deshabilitar la franja horaria',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function showFranjasHorariasNoDisponibles(){

        $user=Auth::user();

        abort_unless($user->tokenCan('franjasNoDisponible:show') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        $horarios = Horario::where('activo', false)->get();

        $data = [
            'horarios' => $horarios,
            'status' => 200
        ];

        return response()->json($data, 200);
    }
}
