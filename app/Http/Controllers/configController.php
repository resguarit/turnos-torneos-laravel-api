<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Horario;
use App\Models\Cancha;
use App\Models\HorarioCancha;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class configController extends Controller
{
    public function configurarHorarios(Request $request)
    {
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

        $horaApertura = Carbon::createFromFormat('H:i', $request->hora_apertura);
        $horaCierre = Carbon::createFromFormat('H:i', $request->hora_cierre);
        $intervalo = $request->intervalo;

        $horariosExistentes = Horario::orderBy('horaInicio')->get();

        if ($horariosExistentes->isEmpty()) {
            $this->crearHorarios($horaApertura, $horaCierre, $intervalo);
        } else {
            $horaAperturaExistente = Carbon::createFromFormat('H:i:s', $horariosExistentes->first()->horaInicio);
            $horaCierreExistente = Carbon::createFromFormat('H:i:s', $horariosExistentes->last()->horaFin);

            if ($horaApertura->lt($horaAperturaExistente)) {
                $this->crearHorarios($horaApertura, $horaAperturaExistente, $intervalo);
            } elseif ($horaApertura->gt($horaAperturaExistente)) {
                Horario::where('horaInicio', '<', $horaApertura->format('H:i:s'))->update(['activo' => false]);
            }

            if ($horaCierre->lt($horaCierreExistente)) {
                Horario::where('horaFin', '>', $horaCierre->format('H:i:s'))->orWhere('horaInicio', '>=', $horaCierre->format('H:i:s'))->update(['activo' => false]);
            } elseif ($horaCierre->gt($horaCierreExistente)) {
                $this->crearHorarios($horaCierreExistente, $horaCierre, $intervalo);
            }

            Horario::whereBetween('horaInicio', [$horaApertura->format('H:i:s'), $horaCierre->subMinutes(60)->format('H:i:s')])
                ->update(['activo' => true]);
        }

        $this->actualizarHorariosCancha();

        return response()->json([
            'message' => 'Horarios configurados correctamente',
            'status' => 201
        ], 201);
    }

    private function crearHorarios($horaInicio, $horaFin, $intervalo)
    {
        $horaActual = $horaInicio;

        while ($horaActual->lt($horaFin)) {
            $horaInicioTurno = $horaActual->format('H:i');
            $horaActual->addMinutes($intervalo * 60);
            $horaFinTurno = $horaActual->format('H:i');

            $horario = Horario::firstOrCreate(
                ['horaInicio' => $horaInicioTurno, 'horaFin' => $horaFinTurno],
                ['activo' => true]
            );

            $canchas = Cancha::all();
            foreach ($canchas as $cancha) {
                HorarioCancha::firstOrCreate([
                    'cancha_id' => $cancha->id,
                    'horario_id' => $horario->id,
                    'activo' => true
                ]);
            }
        }
    }

    private function actualizarHorariosCancha()
    {
        $horarios = Horario::where('activo', true)->get();
        $canchas = Cancha::all();

        HorarioCancha::whereIn('horario_id', $horarios->pluck('id'))->update(['activo' => true]);
        foreach ($canchas as $cancha) {
            foreach ($horarios as $horario) {
                HorarioCancha::firstOrCreate([
                    'cancha_id' => $cancha->id,
                    'horario_id' => $horario->id,
                ], [
                    'activo' => true
                ]);
            }
        }

        $horariosNoActivos = Horario::where('activo', false)->get();
        foreach ($horariosNoActivos as $horario) {
            HorarioCancha::where('horario_id', $horario->id)->update(['activo' => false]);
        }
    }
}
