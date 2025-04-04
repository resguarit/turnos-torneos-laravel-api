<?php

namespace App\Services\Implementation;

use App\Models\Horario;
use App\Services\Interface\HorarioServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HorarioService implements HorarioServiceInterface
{
    public function getHorarios()
    {
        $horarios = Horario::all();
        
        return response()->json([
            'horarios' => $horarios,
            'status' => 200
        ], 200);
    }

    public function showHorario($id)
    {
        try {
            $horario = Horario::findOrFail($id);
            return response()->json([
                'horario' => $horario,
                'status' => 200
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Horario no encontrado',
                'status' => 404
            ], 404);
        }
    }

    public function storeHorario(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'hora_inicio' => 'required|date_format:H:i|unique:horarios,hora_inicio',  
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio|unique:horarios,hora_fin',
            'dia' => 'required|in:l,m,x,j,v,s,d',
            'activo' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 422
            ], 422);
        }

        $horario = Horario::create([
            'hora_inicio' => $request->hora_inicio,
            'hora_fin' => $request->hora_fin,
            'dia'=> $request->dia,
            'activo' => $request->activo,
        ]);

        if (!$horario) {
            return response()->json([
                'message' => 'Error al crear el horario',
                'status' => 500
            ], 500);
        }

        return response()->json([
            'message' => 'Horario creado correctamente',
            'horario' => $horario,
            'status' => 201
        ], 201);
    }

    public function deleteHorario($id)
    {
        try {
            $horario = Horario::findOrFail($id);
            $horario->delete();

            return response()->json([
                'message' => 'Horario eliminado correctamente',
                'status' => 200
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Horario no encontrado',
                'status' => 404
            ], 404);
        }
    }

    public function getHorariosPorDiaSemana(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 422
            ], 422);
        }

        $fecha = Carbon::createFromFormat('Y-m-d', $request->fecha);
        $diaSemana = $this->getNombreDiaSemana($fecha->dayOfWeek);

        $horarios = Horario::where('dia', $diaSemana)
            ->where('activo', true)
            ->get();

        return response()->json([
            'horarios' => $horarios,
            'status' => 200
        ], 200);
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
        $inactivos = Horario::where('activo', false)->get();
        $agrupadosPorDia = $inactivos->groupBy('dia');

        $result = $agrupadosPorDia->map(function ($itemsInactivos, $dia) {
            $totalDia = Horario::where('dia', $dia)->count();
            $inactivosDia = $itemsInactivos->count();

            if ($inactivosDia === $totalDia) {
                return [[
                    'dia' => $dia,
                    'hora_inicio' => $itemsInactivos->min('hora_inicio'),
                    'hora_fin' => $itemsInactivos->max('hora_fin'),
                    'completamente_inactivo' => true
                ]];
            } else {
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
        $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        
        $result = collect($dias)->map(function($dia) {
            $horariosActivos = Horario::where('dia', $dia)
                ->where('activo', true)
                ->get();
    
            if ($horariosActivos->isNotEmpty()) {
                return [
                    'dia' => $dia,
                    'hora_inicio' => $horariosActivos->min('hora_inicio'),
                    'hora_fin' => $horariosActivos->max('hora_fin'),
                    'inactivo' => false
                ];
            }
    
            $ultimosHorarios = Horario::where('dia', $dia)
                ->orderBy('updated_at', 'desc')
                ->orderBy('hora_inicio', 'asc')
                ->get()
                ->groupBy('updated_at')
                ->first();
    
            if ($ultimosHorarios && $ultimosHorarios->isNotEmpty()) {
                return [
                    'dia' => $dia,
                    'hora_inicio' => $ultimosHorarios->min('hora_inicio'),
                    'hora_fin' => $ultimosHorarios->max('hora_fin'),
                    'inactivo' => true
                ];
            }
    
            return [
                'dia' => $dia,
                'hora_inicio' => null,
                'hora_fin' => null,
                'inactivo' => null
            ];
        });
    
        return response()->json([
            'horarios_extremos' => $result,
            'status' => 200
        ], 200);
    }
}