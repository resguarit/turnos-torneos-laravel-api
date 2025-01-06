<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Turno;
use App\Models\Cancha;
use App\Models\Horario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class disponibilidadController extends Controller
{
    public function getHorariosNoDisponibles()
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('horariosNoDisponible:show') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        $fechaInicio = now()->startOfDay();
        $fechaFin = now()->addDays(30)->endOfDay();

        $canchasCount = Cancha::count();
        $horarios = Horario::all();

        $turnos = Turno::whereBetween('fecha_turno', [$fechaInicio, $fechaFin])
                            ->with('horarioCancha.horario')
                            ->get();

        $noDisponibles = [];

        foreach ($turnos as $turnos) {
            $fecha = $turnos->fecha_turno->format('Y-m-d');
            $horario = $turnos->horarioCancha->horario;
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

    public function getHorariosDisponiblesPorFecha(Request $request)
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('horarios:fecha') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

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

        $turnos = Turno::whereDate('fecha_turno', $fecha)
                            ->with('horarioCancha.horario')
                            ->get();

        $noDisponibles = [];

        foreach ($turnos as $turno) {
            $horario = $turno->horarioCancha->horario;
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

        $user = Auth::user();

        abort_unless( $user->tokenCan('disponibilidad:canchas') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

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
                            ->where('horarioCanchaID', $horario->id)
                            ->with('horarioCancha.cancha')
                            ->get();

        $canchas = Cancha::all();

        $noDisponibles = [];

        foreach ($turnos as $turno) {
            $cancha = $turno->horarioCancha->cancha;
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
