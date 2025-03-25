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
        $resumen = [
            'dias_afectados' => [],
            'horarios_creados' => 0,
            'horarios_modificados' => 0,
            'horarios_deshabilitados' => 0,
        ];

        foreach ($dias as $dia => $horas) {
            $resumen['dias_afectados'][] = $dia;

            $diaResumen = [
                'horarios_creados' => 0,
                'horarios_modificados' => 0,
                'horarios_deshabilitados' => 0,
            ];

            if (is_null($horas['hora_apertura']) && is_null($horas['hora_cierre'])) {
                $horariosDeshabilitados = Horario::where('dia', $dia)->get();
                foreach ($horariosDeshabilitados as $horario) {
                    $horario->update(['activo' => false]);
                }
                $diaResumen['horarios_deshabilitados'] = $horariosDeshabilitados->count();
                $resumen['horarios_deshabilitados'] += $horariosDeshabilitados->count();
            } else {
                $horaApertura = Carbon::createFromFormat('H:i', $horas['hora_apertura']);
                $horaCierre = Carbon::createFromFormat('H:i', $horas['hora_cierre']);

                $horariosExistentes = Horario::where('dia', $dia)->orderBy('hora_inicio')->get();

                if ($horariosExistentes->isEmpty()) {
                    $this->crearHorarios($horaApertura, $horaCierre, $dia);

                    $nuevosHorarios = Horario::where('dia', $dia)
                        ->whereBetween('hora_inicio', [$horaApertura->format('H:i:s'), $horaCierre->format('H:i:s')])
                        ->get();

                    $diaResumen['horarios_creados'] = $nuevosHorarios->count();
                    $resumen['horarios_creados'] += $nuevosHorarios->count();
                } else {
                    $this->actualizarHorariosExistentes($horariosExistentes, $horaApertura, $horaCierre, $dia);

                    $horariosActualizados = Horario::where('dia', $dia)
                        ->whereBetween('hora_inicio', [$horaApertura->format('H:i:s'), $horaCierre->format('H:i:s')])
                        ->get();

                    $diaResumen['horarios_modificados'] = $horariosActualizados->count();
                    $resumen['horarios_modificados'] += $horariosActualizados->count();
                }
            }

            // Registrar una sola auditoría por día
            $id = $id ?? null; // Si no está definido, asignar null
            AuditoriaService::registrar(
                'configurar',
                'horarios',
                $id, // Aquí está el problema
                null,
                ['dia' => $dia, 'resumen' => $diaResumen]
            );
        }

        return response()->json([
            'message' => 'Horarios configurados correctamente',
            'resumen' => $resumen,
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