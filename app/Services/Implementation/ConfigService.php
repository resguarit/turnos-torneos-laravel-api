<?php

namespace App\Services\Implementation;

use App\Models\Horario;
use App\Services\Interface\ConfigServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ConfigService implements ConfigServiceInterface
{
    public function configurarHorarios(Request $request)
    {
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
            if (is_null($horas['hora_apertura']) && is_null($horas['hora_cierre'])) {
                Horario::where('dia', $dia)->update(['activo' => false]);
                continue;
            }

            if (isset($horas['hora_apertura']) && isset($horas['hora_cierre'])) {
                $horaApertura = Carbon::createFromFormat('H:i', $horas['hora_apertura']);
                $horaCierre = Carbon::createFromFormat('H:i', $horas['hora_cierre']);

                $horariosExistentes = Horario::where('dia', $dia)->orderBy('hora_inicio')->get();

                if ($horariosExistentes->isEmpty()) {
                    $this->crearHorarios($horaApertura, $horaCierre, $dia);
                } else {
                    $this->actualizarHorariosExistentes($horariosExistentes, $horaApertura, $horaCierre, $dia);
                }
            }
        }

        return response()->json([
            'message' => 'Horarios configurados correctamente',
            'status' => 201
        ], 201);
    }

    private function crearHorarios(Carbon $horaInicio, Carbon $horaFin, string $dia)
    {
        $horaActual = $horaInicio->copy();

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

    private function actualizarHorariosExistentes($horariosExistentes, Carbon $horaApertura, Carbon $horaCierre, string $dia)
    {
        $horaAperturaExistente = Carbon::createFromFormat('H:i:s', $horariosExistentes->first()->hora_inicio);
        $horaCierreExistente = Carbon::createFromFormat('H:i:s', $horariosExistentes->last()->hora_fin);

        if ($horaApertura->lt($horaAperturaExistente)) {
            $this->crearHorarios($horaApertura, $horaAperturaExistente, $dia);
        } elseif ($horaApertura->gt($horaAperturaExistente)) {
            Horario::where('dia', $dia)
                   ->where('hora_inicio', '<', $horaApertura->format('H:i:s'))
                   ->update(['activo' => false]);
        }

        if ($horaCierre->lt($horaCierreExistente)) {
            Horario::where('dia', $dia)
                   ->where('hora_fin', '>', $horaCierre->format('H:i:s'))
                   ->update(['activo' => false]);
        } elseif ($horaCierre->gt($horaCierreExistente)) {
            $this->crearHorarios($horaCierreExistente, $horaCierre, $dia);
        }

        Horario::where('dia', $dia)
               ->whereBetween('hora_inicio', [$horaApertura->format('H:i:s'), $horaCierre->format('H:i:s')])
               ->update(['activo' => true]);
    }
}