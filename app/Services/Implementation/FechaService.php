<?php
// app/Services/Implementation/FechaService.php

namespace App\Services\Implementation;

use App\Models\Fecha;
use App\Services\Interface\FechaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Enums\FechaEstado;

class FechaService implements FechaServiceInterface
{
    public function getAll()
    {
        return Fecha::with('zona', 'partidos')->get();
    }

    public function getById($id)
    {
        return Fecha::with('zona', 'partidos')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
            'estado' => ['required', 'string', 'max:255', 'in:' . implode(',', FechaEstado::values())],
            'zona_id' => 'required|exists:zonas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $fecha = Fecha::create($request->all());

        return response()->json([
            'message' => 'Fecha creada correctamente',
            'fecha' => $fecha,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $fecha = Fecha::find($id);

        if (!$fecha) {
            return response()->json([
                'message' => 'Fecha no encontrada',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
            'estado' => ['required', 'string', 'max:255', 'in:' . implode(',', FechaEstado::values())],
            'zona_id' => 'required|exists:zonas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $fecha->update($request->all());

        return response()->json([
            'message' => 'Fecha actualizada correctamente',
            'fecha' => $fecha,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $fecha = Fecha::find($id);

        if (!$fecha) {
            return response()->json([
                'message' => 'Fecha no encontrada',
                'status' => 404
            ], 404);
        }

        $fecha->delete();

        return response()->json([
            'message' => 'Fecha eliminada correctamente',
            'status' => 200
        ], 200);
    }

    public function getByZona($zonaId)
    {
        return Fecha::where('zona_id', $zonaId)->with('partidos.equipos')->get();
    }

    public function postergarFechas($fechaId)
    {
        $fecha = Fecha::find($fechaId);

        if (!$fecha) {
            return response()->json([
                'message' => 'Fecha no encontrada',
                'status' => 404
            ], 404);
        }

        $zonaId = $fecha->zona_id;
        $fechasPosteriores = Fecha::where('zona_id', $zonaId)
            ->where('fecha_inicio', '>=', $fecha->fecha_inicio) // Incluir la fecha especificada
            ->orderBy('fecha_inicio')
            ->get();

        foreach ($fechasPosteriores as $fechaPosterior) {
            $fechaPosterior->fecha_inicio = Carbon::parse($fechaPosterior->fecha_inicio)->addWeek();
            $fechaPosterior->fecha_fin = Carbon::parse($fechaPosterior->fecha_fin)->addWeek();
            $fechaPosterior->save();

            // Actualizar las fechas de los partidos asociados
            foreach ($fechaPosterior->partidos as $partido) {
                $partido->fecha = Carbon::parse($partido->fecha)->addWeek();
                $partido->save();
            }
        }

        return response()->json([
            'message' => 'Fechas postergadas correctamente',
            'status' => 200
        ], 200);
    }

    public function verificarEstadoFecha($fechaId)
    {
        $fecha = Fecha::with('partidos')->findOrFail($fechaId);
        $todosFinalizados = true;
        
        // No actualizar si ya está finalizada
        if ($fecha->estado === 'Finalizada') {
            return response()->json([
                'message' => 'La fecha ya estaba marcada como Finalizada',
                'fecha' => $fecha
            ]);
        }
        
        // Verificar que haya partidos y que todos estén finalizados
        if ($fecha->partidos->isEmpty()) {
            return response()->json([
                'message' => 'La fecha no tiene partidos asociados',
                'fecha' => $fecha
            ]);
        }
        
        foreach ($fecha->partidos as $partido) {
            if ($partido->estado !== 'Finalizado') {
                $todosFinalizados = false;
                break;
            }
        }
        
        if ($todosFinalizados) {
            $fecha->estado = 'Finalizada';
            $fecha->save();
            return response()->json([
                'message' => 'Fecha actualizada a Finalizada',
                'fecha' => $fecha
            ]);
        }
        
        return response()->json([
            'message' => 'No se actualizó el estado de la fecha porque hay partidos sin finalizar',
            'fecha' => $fecha
        ]);
    }
}