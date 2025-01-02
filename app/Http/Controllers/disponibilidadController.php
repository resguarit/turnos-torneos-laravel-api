<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reserva;
use App\Models\Cancha;
use App\Models\Horario;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class disponibilidadController extends Controller
{
    public function getHorariosNoDisponibles()
    {
        $fechaInicio = now();
        $fechaFin = now()->addDays(30);

        $canchasCount = Cancha::count();
        $horarios = Horario::all();

        $reservas = Reserva::whereBetween('fecha_turno', [$fechaInicio, $fechaFin])
                            ->with('horarioCancha.horario')
                            ->get();

        $noDisponibles = [];

        foreach ($reservas as $reserva) {
            $fecha = $reserva->fecha_turno->format('Y-m-d');
            $horario = $reserva->horarioCancha->horario;
            $intervalo = $horario->horaInicio . '-' . $horario->horaFin;

            if (!isset($noDisponibles[$fecha])) {
                $noDisponibles[$fecha] = [];
            }

            if (!isset($noDisponibles[$fecha][$intervalo])) {
                $noDisponibles[$fecha][$intervalo] = 0;
            }

            $noDisponibles[$fecha][$intervalo]++;
        }

        $result = [];

        foreach ($noDisponibles as $fecha => $horarios) {
            foreach ($horarios as $intervalo => $count) {
                if ($count >= $canchasCount) {
                    if (!isset($result[$fecha])) {
                        $result[$fecha] = [];
                    }
                    $result[$fecha][] = $intervalo;
                }
            }
        }

        return response()->json($result, 200);
    }

    public function getHorariosNoDisponiblesPorFecha(Request $request)
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

        $canchasCount = Cancha::count();
        $horarios = Horario::where('activo', true)->get();

        $reservas = Reserva::whereDate('fecha_turno', $fecha)
                            ->with('horarioCancha.horario')
                            ->get();

        $noDisponibles = [];

        foreach ($reservas as $reserva) {
            $horario = $reserva->horarioCancha->horario;
            $intervalo = $horario->horaInicio . '-' . $horario->horaFin;

            if (!isset($noDisponibles[$intervalo])) {
                $noDisponibles[$intervalo] = 0;
            }

            $noDisponibles[$intervalo]++;
        }

        $result = [];

        foreach ($horarios as $horario) {
            $intervalo = $horario->horaInicio . '-' . $horario->horaFin;
            $disponible = !isset($noDisponibles[$intervalo]) || $noDisponibles[$intervalo] < $canchasCount;

            $result[] = [
                'id' => $horario->id,
                'horaInicio' => $horario->horaInicio,
                'horaFin' => $horario->horaFin,
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

        $reservas = Reserva::whereDate('fecha_turno', $fecha)
                            ->where('horarioCanchaID', $horario->id)
                            ->with('horarioCancha.cancha')
                            ->get();

        $canchas = Cancha::all();

        $noDisponibles = [];

        foreach ($reservas as $reserva) {
            $cancha = $reserva->horarioCancha->cancha;
            $noDisponibles[] = $cancha->id;
        }

        $result = [];

        foreach ($canchas as $cancha) {
            $disponible = !in_array($cancha->id, $noDisponibles);

            $result[] = [
                'id' => $cancha->id,
                'nro' => $cancha->nro,
                'tipo' => $cancha->tipoCancha,
                'disponible' => $disponible,
            ];
        }


        return response()->json(['canchas' => $result, 'status' => 200], 200);
    }
}
