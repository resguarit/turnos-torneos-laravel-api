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
use App\Models\Deporte;use App\Services\Implementation\AuditoriaService;

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
            'deporte_id' => 'required|exists:deportes,id',
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
            'dia' => $request->dia,
            'activo' => $request->activo,
            'deporte_id' => $request->deporte_id,
        ]);

        if (!$horario) {
            return response()->json([
                'message' => 'Error al crear el horario',
                'status' => 500
            ], 500);
        }

        // Registrar auditoría
        AuditoriaService::registrar(
            'crear',
            'horarios',
            $horario->id,
            null,
            $horario->toArray()
        );

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
            $datosAnteriores = $horario->toArray();

            $horario->delete();

            // Registrar auditoría
            AuditoriaService::registrar(
                'eliminar',
                'horarios',
                $id,
                $datosAnteriores,
                null
            );

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
            'deporte_id' => 'required|exists:deportes,id',
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
            ->where('deporte_id', $request->deporte_id)
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
            'deporte_id' => 'required|exists:deportes,id',
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
                ->where('deporte_id', $request->deporte_id)
                ->get();

            if ($horarios->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No se encontraron horarios en ese rango para el día especificado',
                    'status' => 404
                ], 404);
            }

            foreach ($horarios as $horario) {
                $datosAnteriores = $horario->toArray();
                $horario->activo = false;
                $horario->save();

                // Registrar auditoría
                AuditoriaService::registrar(
                    'deshabilitar',
                    'horarios',
                    $horario->id,
                    $datosAnteriores,
                    $horario->fresh()->toArray()
                );
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
            'deporte_id' => 'required|exists:deportes,id',
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
                ->where('deporte_id', $request->deporte_id)
                ->get();

            if ($horarios->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No se encontraron horarios en ese rango para el día especificado',
                    'status' => 404
                ], 404);
            }

            foreach ($horarios as $horario) {
                $datosAnteriores = $horario->toArray();
                $horario->activo = true;
                $horario->save();

                // Registrar auditoría
                AuditoriaService::registrar(
                    'habilitar',
                    'horarios',
                    $horario->id,
                    $datosAnteriores,
                    $horario->fresh()->toArray()
                );
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

    public function showFranjasHorariasNoDisponibles(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'deporte_id' => 'required|exists:deportes,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 422
            ], 422);
        }

        $inactivos = Horario::where('activo', false)->where('deporte_id', $request->deporte_id)->get();
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

    public function getHorariosExtremosActivos(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'deporte_id' => 'required|exists:deportes,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 422
            ], 422);
        }

        $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        
        $result = collect($dias)->map(function($dia) use ($request) {
            $horariosActivos = Horario::where('dia', $dia)
                ->where('activo', true)
                ->where('deporte_id', $request->deporte_id)
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
                ->where('deporte_id', $request->deporte_id)
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

    public function updateHorario(Request $request, $id)
    {
        $horario = Horario::find($id);

        if (!$horario) {
            return response()->json([
                'message' => 'Horario no encontrado',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'hora_inicio' => 'sometimes|date_format:H:i|unique:horarios,hora_inicio,' . $id,
            'hora_fin' => 'sometimes|date_format:H:i|after:hora_inicio|unique:horarios,hora_fin,' . $id,
            'dia' => 'sometimes|in:l,m,x,j,v,s,d',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 422
            ], 422);
        }

        $datosAnteriores = $horario->toArray();

        $horario->update($request->only(['hora_inicio', 'hora_fin', 'dia', 'activo']));

        AuditoriaService::registrar(
            'modificar',
            'horarios',
            $horario->id,
            $datosAnteriores,
            $horario->fresh()->toArray()
        );
        
        return response()->json([
            'message' => 'Horario actualizado correctamente',
            'horario' => $horario,
            'status' => 200
        ], 200);
    }
}