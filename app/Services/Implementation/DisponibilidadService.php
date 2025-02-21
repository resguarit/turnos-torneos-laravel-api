<?php

namespace App\Services\Implementation;

use App\Models\Turno;
use App\Models\Cancha;
use App\Models\Horario;
use App\Services\Interface\DisponibilidadServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DisponibilidadService implements DisponibilidadServiceInterface
{
    public function getHorariosNoDisponibles()
    {
        $fecha_inicio = now()->startOfDay();
        $fecha_fin = now()->addDays(30)->endOfDay();

        $canchas_count = Cancha::where('activa', true)->count();

        $turnos = Turno::select(
            'fecha_turno',
            'horario_id',
            DB::raw('COUNT(*) as total_reservas')
        )
        ->whereBetween('fecha_turno', [$fecha_inicio, $fecha_fin])
        ->where('estado', '!=', 'Cancelado')
        ->groupBy('fecha_turno', 'horario_id')
        ->having('total_reservas', '>=', $canchas_count) 
        ->with(['horario:id,hora_inicio,hora_fin'])
        ->get();

        $result = $turnos->groupBy(function($turno) {
            return $turno->fecha_turno->format('Y-m-d');
        })->map(function($grupoTurnos) use ($canchas_count) {
            $horarios = $grupoTurnos->map(function($turno) use ($canchas_count) {
                return [
                    'hora_inicio' => $turno->horario->hora_inicio,
                    'hora_fin' => $turno->horario->hora_fin,
                    'reservas' => $turno->total_reservas,
                    'disponibles' => $canchas_count - $turno->total_reservas
                ];
            });

            return $horarios;
        })->toArray();

        return response()->json([
            'horarios_no_disponibles' => $result,
            'canchas_totales' => $canchas_count,
            'status' => 200
        ], 200);
    }

    public function getHorariosDisponiblesPorFecha(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $fecha = Carbon::createFromFormat('Y-m-d', $request->fecha);
        $diaSemana = $this->getNombreDiaSemana($fecha->dayOfWeek);

        $canchasCount = Cancha::where('activa', true)->count();
        $horarios = Horario::where('activo', true)
                            ->where('dia', $diaSemana)
                            ->orderBy('hora_inicio')
                            ->get();

        $reservas = Turno::whereDate('fecha_turno', $fecha)
                            ->where('estado', '!=', 'Cancelado')
                            ->with('horario')
                            ->get();

        $noDisponibles = [];

        foreach ($reservas as $reserva) {
            $horario = $reserva->horario;
            $intervalo = $horario->hora_inicio . '-' . $horario->hora_fin;

            if (!isset($noDisponibles[$intervalo])) {
                $noDisponibles[$intervalo] = 0;
            }

            $noDisponibles[$intervalo]++;
        }

        $result = [];

        foreach ($horarios as $horario) {
            $intervalo = $horario->hora_inicio . '-' . $horario->hora_fin;
            $disponible = !isset($noDisponibles[$intervalo]) || $noDisponibles[$intervalo] < $canchasCount;

            $result[] = [
                'id' => $horario->id,
                'hora_inicio' => $horario->hora_inicio,
                'hora_fin' => $horario->hora_fin,
                'disponible' => $disponible
            ];
        }

        return response()->json(['horarios' => $result], 200);
    }

    public function getCanchasPorHorarioFecha(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date_format:Y-m-d',
            'horario_id' => 'required|exists:horarios,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $fecha = Carbon::createFromFormat('Y-m-d', $request->fecha);
        $diaSemana = $this->getNombreDiaSemana($fecha->dayOfWeek);

        $horario = Horario::where('id', $request->horario_id)
                          ->where('dia', $diaSemana)
                          ->first();

        if (!$horario) {
            return response()->json([
                'message' => 'Horario no encontrado para el día especificado',
                'status' => 404
            ], 404);
        }

        $turnos = Turno::whereDate('fecha_turno', $fecha)
                        ->where('horario_id', $horario->id)
                        ->where('estado', '!=', 'Cancelado')
                        ->with('cancha')
                        ->get();

        $canchas = Cancha::where('activa', true)->get();

        $noDisponibles = [];

        foreach ($turnos as $turno) {
            $cancha = $turno->cancha;
            $noDisponibles[] = $cancha->id;
        }

        $result = [];

        foreach ($canchas as $cancha) {
            $disponible = !in_array($cancha->id, $noDisponibles);

            $result[] = [
                'id' => $cancha->id,
                'nro' => $cancha->nro,
                'tipo' => $cancha->tipo_cancha,
                'disponible' => $disponible,
                'precio_por_hora' => $cancha->precio_por_hora,
                'seña' => $cancha->seña
            ];
        }

        return response()->json(['canchas' => $result, 'status' => 200], 200);
    }

    public function getDiasNoDisponibles()
    {
        $inactiveDays = [];

        for ($i = 0; $i < 7; $i++) {
            $horarios = Horario::where('dia', $this->getNombreDiaSemana($i))->get();

            if ($horarios->isEmpty() || $horarios->every(function ($horario) {
                return !$horario->activo;
            })) {
                $inactiveDays[] = $i;
            }
        }

        return response()->json(['inactiveDays' => $inactiveDays, 'status' => 200], 200);
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