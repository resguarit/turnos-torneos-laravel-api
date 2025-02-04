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
        $validator = Validator::make($request->all(), [
            'dia' => 'required|string|in:Lunes,Martes,Miércoles,Jueves,Viernes,Sábado,Domingo',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 422
            ], 422);
        }

        DB::beginTransaction();

        try {
            $horarios = Horario::where('dia', $request->dia)
                ->whereTime('hora_inicio', '>=', $request->hora_inicio)
                ->whereTime('hora_fin', '<=', $request->hora_fin)
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

    public function habilitarFranjaHoraria(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dia' => 'required|string|in:Lunes,Martes,Miércoles,Jueves,Viernes,Sábado,Domingo',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 422
            ], 422);
        }

        DB::beginTransaction();

        try {
            $horarios = Horario::where('dia', $request->dia)
                ->whereTime('hora_inicio', '>=', $request->hora_inicio)
                ->whereTime('hora_fin', '<=', $request->hora_fin)
                ->get();

            if ($horarios->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No se encontraron horarios en ese rango para el día especificado',
                    'status' => 404
                ], 404);
            }

            foreach ($horarios as $horario) {
                $horario->activo = true;
                $horario->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Franja horaria habilitada correctamente para el día ' . $request->dia,
                'horarios' => $horarios,
                'status' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al habilitar la franja horaria',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function showFranjasHorariasNoDisponibles()
    {
        $user = Auth::user();

        abort_unless($user->tokenCan('franjasNoDisponible:show') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        // Obtener todos los horarios inactivos
        $inactivos = Horario::where('activo', false)->get();

        // Agrupar los horarios inactivos por día
        $agrupadosPorDia = $inactivos->groupBy('dia');

        // Procesar cada día
        $result = $agrupadosPorDia->map(function ($itemsInactivos, $dia) {
            $totalDia = Horario::where('dia', $dia)->count();            // todos los horarios del día
            $inactivosDia = $itemsInactivos->count();                    // horarios inactivos del día

            if ($inactivosDia === $totalDia) {
                // Si todos los horarios del día están inactivos, devolver solo los extremos
                return [[
                    'dia' => $dia,
                    'hora_inicio' => $itemsInactivos->min('hora_inicio'),
                    'hora_fin' => $itemsInactivos->max('hora_fin'),
                    'completamente_inactivo' => true
                ]];
            } else {
                // Si hay horarios activos, devolver todos los horarios inactivos
                return $itemsInactivos->map(function ($horario) {
                    return [
                        'dia' => $horario->dia,
                        'hora_inicio' => $horario->hora_inicio,
                        'hora_fin' => $horario->hora_fin,
                        'completamente_inactivo' => false
                    ];
                });
            }
        })->flatten(1)->values();

        return response()->json([
            'horarios' => $result,
            'status' => 200
        ], 200);
    }

    public function getHorariosExtremosActivos()
{
    // Get all days in correct order
    $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    
    $result = collect($dias)->map(function($dia) {
        // Get current active schedules for the day
        $horariosActivos = Horario::where('dia', $dia)
            ->where('activo', true)
            ->get();

        // If there are active schedules, return their min and max times
        if ($horariosActivos->isNotEmpty()) {
            return [
                'dia' => $dia,
                'hora_inicio' => $horariosActivos->min('hora_inicio'),
                'hora_fin' => $horariosActivos->max('hora_fin')
            ];
        }

        // If no active schedules, get the last 2 schedules that were active
        $ultimosHorarios = Horario::where('dia', $dia)
            ->orderBy('hora_inicio')
            ->get()
            ->take(2);

        // If there are previous schedules, return their times
        if ($ultimosHorarios->isNotEmpty()) {
            return [
                'dia' => $dia,
                'hora_inicio' => $ultimosHorarios->first()->hora_inicio,
                'hora_fin' => $ultimosHorarios->last()->hora_fin,
                'inactivo' => true // Flag to indicate these are inactive schedules
            ];
        }

        // If no schedules at all, return null values
        return [
            'dia' => $dia,
            'hora_inicio' => null,
            'hora_fin' => null
        ];
    });

    return response()->json([
        'horarios_extremos' => $result,
        'status' => 200
    ], 200);
}
}
