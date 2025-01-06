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

        $fecha_inicio = now()->startOfDay();
        $fecha_fin = now()->addDays(30)->endOfDay();

        $canchas_count = Cancha::count();
        $horarios = Horario::all();

        $turnos = Turno::whereBetween('fecha_turno', [$fecha_inicio, $fecha_fin])
                            ->with('horario')
                            ->get();

        $no_disponibles = [];

        foreach ($turnos as $turnos) {
            $fecha = $turnos->fecha_turno->format('Y-m-d');
            $horario = $turnos->horario;
            $intervalo = $horario->hora_inicio . '-' . $horario->hora_fin;

            if (!isset($no_disponibles[$fecha])) {
                $no_disponibles[$fecha] = [];
            }

            if (!isset($no_disponibles[$fecha][$intervalo])) {
                $no_disponibles[$fecha][$intervalo] = 0;
            }

            $no_disponibles[$fecha][$intervalo]++;
        }

        $result = [];

        foreach ($no_disponibles as $fecha => $horarios) {
            foreach ($horarios as $intervalo => $count) {
                if ($count >= $canchas_count) {
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

        $canchas_count = Cancha::count();
        $horarios = Horario::where('activo', true)->get();

        $turnos = Turno::whereDate('fecha_turno', $fecha)
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
                            ->where('horario', $horario->id)
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
