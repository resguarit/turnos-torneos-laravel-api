<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Turno;
use App\Models\Cancha;
use App\Models\Horario;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class disponibilidadController extends Controller
{
    public function getHorariosNoDisponibles()
    {
        $fecha_inicio = now()->startOfDay();
        $fecha_fin = now()->addDays(30)->endOfDay();

        $canchas_count = Cancha::count();

        $turnos = Turno::select(
            'fecha_turno',
            'horario_id',
            DB::raw('COUNT(*) as total_reservas')
        )
        ->whereBetween('fecha_turno', [$fecha_inicio, $fecha_fin])
        ->groupBy('fecha_turno', 'horario_id')
        ->having('total_reservas', '>=', $canchas_count)
        ->with(['horario:id,hora_inicio,hora_fin'])
        ->where('estado', "!=", "Cancelado")
        ->get();

        // Group by date and merge consecutive times
        $result = $turnos->groupBy(function($turno) {
            return $turno->fecha_turno->format('Y-m-d');
        })->map(function($grupoTurnos) {
            $horarios = $grupoTurnos->sortBy(function($turno) {
                return $turno->horario->hora_inicio;
            });

            $merged = [];
            $current = null;

            foreach ($horarios as $turno) {
                $horaInicio = $turno->horario->hora_inicio;
                $horaFin = $turno->horario->hora_fin;

                if ($current === null) {
                    $current = ['inicio' => $horaInicio, 'fin' => $horaFin];
                } else {
                    // If current end time equals this start time, extend the range
                    if ($current['fin'] === $horaInicio) {
                        $current['fin'] = $horaFin;
                    } else {
                        // Add completed range and start new one
                        $merged[] = $current['inicio'] . '-' . $current['fin'];
                        $current = ['inicio' => $horaInicio, 'fin' => $horaFin];
                    }
                }
            }

            if ($current !== null) {
                $merged[] = $current['inicio'] . '-' . $current['fin'];
            }
            return $merged;
        })->toArray();

        return $result;
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

        $canchas_count = Cancha::count();
        $horarios = Horario::where('activo', true)->get();

        // Modificar la consulta para excluir turnos cancelados
        $turnos = Turno::whereDate('fecha_turno', $fecha)
                        ->where('estado', '!=', 'Cancelado')
                        ->with('horario')
                        ->get();

        $no_disponibles = [];

        foreach ($turnos as $turno) {
            $horario = $turno->horario;
            $intervalo = $horario->hora_inicio . '-' . $horario->hora_fin;

            if (!isset($no_disponibles[$intervalo])) {
                $no_disponibles[$intervalo] = 0;
            }

            $no_disponibles[$intervalo]++;
        }

        $result = [];

        foreach ($horarios as $horario) {
            $intervalo = $horario->hora_inicio . '-' . $horario->hora_fin;
            $disponible = !isset($no_disponibles[$intervalo]) || $no_disponibles[$intervalo] < $canchas_count;

            $result[] = [
                'id' => $horario->id,
                'hora_inicio' => $horario->hora_inicio,
                'hora_fin' => $horario->hora_fin,
                'disponible' => $disponible
            ];
        }

        return response()->json(['horarios' => $result], 200);
    }


    public function getCanchasPorHorarioFecha(Request $request){

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

        $horario = Horario::find($request->horario_id);

        $turnos = Turno::whereDate('fecha_turno', $fecha)
                            ->where('horario_id', $horario->id)
                            ->where('estado', "!=", "Cancelado")
                            ->with('cancha')
                            ->get();

        $canchas = Cancha::all();

        $no_disponibles = [];

        foreach ($turnos as $turno) {
            $cancha = $turno->cancha;
            $no_disponibles[] = $cancha->id;
        }

        $result = [];

        foreach ($canchas as $cancha) {
            $disponible = !in_array($cancha->id, $no_disponibles);

            $result[] = [
                'id' => $cancha->id,
                'nro' => $cancha->nro,
                'tipo' => $cancha->tipo_cancha,
                'disponible' => $disponible,
            ];
        }

        return response()->json(['canchas' => $result, 'status' => 200], 200);
    }

}