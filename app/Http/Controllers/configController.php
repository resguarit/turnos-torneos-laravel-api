<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Horario;
use App\Models\Cancha;
use App\Models\Turno;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class configController extends Controller
{
    public function configurarHorarios(Request $request)
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('horarios:config') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        $validator = Validator::make($request->all(), [
            'hora_apertura' => 'required|date_format:H:i',
            'hora_cierre' => 'required|date_format:H:i|after:hora_apertura',
            'intervalo' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $hora_apertura = Carbon::createFromFormat('H:i', $request->hora_apertura);
        $hora_cierre = Carbon::createFromFormat('H:i', $request->hora_cierre);
        $intervalo = $request->intervalo;

        $horarios_existentes = Horario::orderBy('hora_inicio')->get();

        if ($horarios_existentes->isEmpty()) {
            $this->crearHorarios($hora_apertura, $hora_cierre, $intervalo);
        } else {
            $hora_apertura_existente = Carbon::createFromFormat('H:i:s', $horarios_existentes->first()->hora_inicio);
            $hora_cierre_existente = Carbon::createFromFormat('H:i:s', $horarios_existentes->last()->hora_fin);

            if ($hora_apertura->lt($hora_apertura_existente)) {
                $this->crearHorarios($hora_apertura, $hora_apertura_existente, $intervalo);
            } elseif ($hora_apertura->gt($hora_apertura_existente)) {
                Horario::where('hora_inicio', '<', $hora_apertura->format('H:i:s'))->update(['activo' => false]);
            }

            if ($hora_cierre->lt($hora_cierre_existente)) {
                Horario::where('hora_fin', '>', $hora_cierre->format('H:i:s'))->orWhere('hora_inicio', '>=', $hora_cierre->format('H:i:s'))->update(['activo' => false]);
            } elseif ($hora_cierre->gt($hora_cierre_existente)) {
                $this->crearHorarios($hora_cierre_existente, $hora_cierre, $intervalo);
            }

            Horario::whereBetween('hora_inicio', [$hora_apertura->format('H:i:s'), $hora_cierre->subMinutes(60)->format('H:i:s')])
                ->update(['activo' => true]);
        }

        return response()->json([
            'message' => 'Horarios configurados correctamente',
            'status' => 201
        ], 201);
    }

    private function crearHorarios($hora_inicio, $hora_fin, $intervalo)
    {
        $hora_actual = $hora_inicio;

        while ($hora_actual->lt($hora_fin)) {
            $hora_inicio_turno = $hora_actual->format('H:i');
            $hora_actual->addMinutes($intervalo * 60);
            $hora_fin_turno = $hora_actual->format('H:i');

            $horario = Horario::firstOrCreate(
                ['hora_inicio' => $hora_inicio_turno, 'hora_fin' => $hora_fin_turno],
                ['activo' => true]
            );
        }
    }
}
