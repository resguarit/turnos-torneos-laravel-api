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

        abort_unless($user->tokenCan('horarios:config') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $validator = Validator::make($request->all(), [
            'dias' => 'required|array',
            'dias.*.hora_apertura' => 'nullable|date_format:H:i',
            'dias.*.hora_cierre' => 'nullable|date_format:H:i|after:dias.*.hora_apertura',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $dias = $request->input('dias');

        foreach ($dias as $dia => $horas) {
            // Si ambos valores son NULL, deshabilitar todos los horarios del día
            if (is_null($horas['hora_apertura']) && is_null($horas['hora_cierre'])) {
                // Actualizar todos los horarios del día a inactivo
                $horariosActualizados = Horario::where('dia', $dia)
                    ->update(['activo' => false]);

                continue; 
            }

            // Proceso normal para configurar horarios cuando hay valores
            if (isset($horas['hora_apertura']) && isset($horas['hora_cierre'])) {
                $horaApertura = Carbon::createFromFormat('H:i', $horas['hora_apertura']);
                $horaCierre = Carbon::createFromFormat('H:i', $horas['hora_cierre']);

                $horariosExistentes = Horario::where('dia', $dia)->orderBy('hora_inicio')->get();

                if ($horariosExistentes->isEmpty()) {
                    $this->crearHorarios($horaApertura, $horaCierre, $dia);
                } else {
                    $horaAperturaExistente = Carbon::createFromFormat('H:i:s', $horariosExistentes->first()->hora_inicio);
                    $horaCierreExistente = Carbon::createFromFormat('H:i:s', $horariosExistentes->last()->hora_fin);

                    if ($horaApertura->lt($horaAperturaExistente)) {
                        $this->crearHorarios($horaApertura, $horaAperturaExistente, $dia);
                    } elseif ($horaApertura->gt($horaAperturaExistente)) {
                        Horario::where('dia', $dia)->where('hora_inicio', '<', $horaApertura->format('H:i:s'))->update(['activo' => false]);
                    }

                    if ($horaCierre->lt($horaCierreExistente)) {
                        Horario::where('dia', $dia)->where('hora_fin', '>', $horaCierre->format('H:i:s'))->update(['activo' => false]);
                    } elseif ($horaCierre->gt($horaCierreExistente)) {
                        $this->crearHorarios($horaCierreExistente, $horaCierre, $dia);
                    }

                    Horario::where('dia', $dia)->whereBetween('hora_inicio', [$horaApertura->format('H:i:s'), $horaCierre->format('H:i:s')])
                        ->update(['activo' => true]);
                }
            }
        }

        return response()->json([
            'message' => 'Horarios configurados correctamente',
            'status' => 201
        ], 201);
    }

    private function crearHorarios($horaInicio, $horaFin, $dia)
    {
        $horaActual = $horaInicio;

        while ($horaActual->lt($horaFin)) {
            $horaInicioTurno = $horaActual->format('H:i');
            $horaActual->addMinutes(60);
            $horaFinTurno = $horaActual->format('H:i');

            Horario::firstOrCreate(
                ['hora_inicio' => $horaInicioTurno, 'hora_fin' => $horaFinTurno, 'dia' => $dia],
                ['activo' => true]
            );
        }
    }
}
