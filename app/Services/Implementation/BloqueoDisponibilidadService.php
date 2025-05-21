<?php

namespace App\Services\Implementation;

use App\Models\BloqueoDisponibilidadTurno;
use App\Models\Cancha;
use App\Models\Horario;
use App\Services\Interface\BloqueoDisponibilidadServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;  

class BloqueoDisponibilidadService implements BloqueoDisponibilidadServiceInterface
{
    public function bloquearDisponibilidad(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'deportes' => 'required|array',
            'deportes.*' => 'exists:deportes,id',
            'canchas' => 'required|array',
            'canchas.*' => 'exists:canchas,id',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        // Filtrar solo las canchas que corresponden a los deportes seleccionados
        $canchasValidas = Cancha::whereIn('id', $request->canchas)
            ->whereIn('deporte_id', $request->deportes)
            ->get();

        if ($canchasValidas->isEmpty()) {
            return response()->json([
                'message' => 'No hay canchas válidas para los deportes seleccionados'
            ], 400);
        }

        $fecha = Carbon::createFromFormat('Y-m-d', $request->fecha);
        $dia = $this->getNombreDiaSemana($fecha->dayOfWeek);

        try {
            DB::beginTransaction();

            // Obtener los horarios que están dentro del rango y corresponden a los deportes
            $horarios = Horario::whereIn('deporte_id', $request->deportes)
                ->where('activo', true)
                ->where('dia', $dia)
                ->where(function($query) use ($request) {
                    $query->where('hora_inicio', '>=', $request->hora_inicio)
                        ->where('hora_fin', '<=', $request->hora_fin);
                })
                ->get();

            if ($horarios->isEmpty()) {
                return response()->json([
                    'message' => 'No hay horarios disponibles en el rango especificado'
                ], 400);
            }

            $bloqueos = [];
            foreach ($canchasValidas as $cancha) {
                $horarioCancha = $horarios->where('deporte_id', $cancha->deporte_id);
                
                foreach ($horarioCancha as $horario) {
                    $bloqueo = BloqueoDisponibilidadTurno::firstOrCreate([
                        'fecha' => $request->fecha,
                        'cancha_id' => $cancha->id,
                        'horario_id' => $horario->id
                    ]);
                    $bloqueos[] = $bloqueo;
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Turnos bloqueados exitosamente',
                'data' => $bloqueos
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al bloquear los turnos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function desbloquearDisponibilidad(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'deportes' => 'required|array',
            'deportes.*' => 'exists:deportes,id',
            'canchas' => 'required|array',
            'canchas.*' => 'exists:canchas,id',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Filtrar solo las canchas que corresponden a los deportes seleccionados
            $canchasValidas = Cancha::whereIn('id', $request->canchas)
                ->whereIn('deporte_id', $request->deportes)
                ->pluck('id');

            if ($canchasValidas->isEmpty()) {
                return response()->json([
                    'message' => 'No hay canchas válidas para los deportes seleccionados'
                ], 400);
            }

            // Obtener los horarios que están dentro del rango y corresponden a los deportes
            $horarios = Horario::whereIn('deporte_id', $request->deportes)
                ->where('activo', true)
                ->where(function($query) use ($request) {
                    $query->whereBetween('hora_inicio', [$request->hora_inicio, $request->hora_fin])
                        ->orWhereBetween('hora_fin', [$request->hora_inicio, $request->hora_fin])
                        ->orWhere(function($q) use ($request) {
                            $q->where('hora_inicio', '<=', $request->hora_inicio)
                                ->where('hora_fin', '>=', $request->hora_fin);
                        });
                })
                ->pluck('id');

            $deleted = BloqueoDisponibilidadTurno::where('fecha', $request->fecha)
                ->whereIn('cancha_id', $canchasValidas)
                ->whereIn('horario_id', $horarios)
                ->delete();

            DB::commit();

            return response()->json([
                'message' => 'Turnos desbloqueados exitosamente',
                'deleted_count' => $deleted
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al desbloquear los turnos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAll()
    {
        $fechaHoy = Carbon::now()->format('Y-m-d');

        $turnosBloqueados = BloqueoDisponibilidadTurno::with('cancha', 'horario')
            ->where('fecha', '>=', $fechaHoy)
            ->orderBy('fecha', 'asc')
            ->get();

        return response()->json([
            'message' => 'Turnos bloqueados obtenidos exitosamente',
            'data' => $turnosBloqueados
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
} 