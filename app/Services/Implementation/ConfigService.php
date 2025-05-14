<?php

namespace App\Services\Implementation;

use App\Models\Horario;
use App\Services\Interface\ConfigServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\Deporte;
class ConfigService implements ConfigServiceInterface
{
    public function configurarHorarios(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dias' => 'required|array',
            'dias.*.hora_apertura' => 'nullable|date_format:H:i',
            'dias.*.hora_cierre' => 'nullable|date_format:H:i|after:dias.*.hora_apertura',
            'deporte_id' => 'required|exists:deportes,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $dias = $request->input('dias');

        $deporte = Deporte::find($request->input('deporte_id'));
        $duracionTurno = $deporte->duracion_turno;

        foreach ($dias as $dia => $horas) {
            $resumen['dias_afectados'][] = $dia;

            $diaResumen = [
                'horarios_creados' => 0,
                'horarios_modificados' => 0,
                'horarios_deshabilitados' => 0,
            ];

            if (is_null($horas['hora_apertura']) && is_null($horas['hora_cierre'])) {
                Horario::where('dia', $dia)
                ->where('deporte_id', $deporte->id)
                ->update(['activo' => false]);
                continue;
            }

            if (isset($horas['hora_apertura']) && isset($horas['hora_cierre'])) {
                $horaApertura = Carbon::createFromFormat('H:i', $horas['hora_apertura']);
                $horaCierre = Carbon::createFromFormat('H:i', $horas['hora_cierre']);

                $horariosExistentes = Horario::where('dia', $dia)
                                            ->where('deporte_id', $deporte->id)
                                            ->orderBy('hora_inicio')
                                            ->get();

                if ($horariosExistentes->isEmpty()) {
                    $this->crearHorarios($horaApertura, $horaCierre, $dia, $deporte->id, $deporte->duracion_turno);
                } else {
                    $this->actualizarHorariosExistentes($horariosExistentes, $horaApertura, $horaCierre, $dia, $deporte->id, $deporte->duracion_turno);
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

    private function crearHorarios(Carbon $horaInicio, Carbon $horaFin, string $dia, int $deporteId, int $duracionTurno)
    {
        $horaActual = $horaInicio->copy();

        while ($horaActual->lt($horaFin)) {
            $horaInicioTurno = $horaActual->format('H:i');
            $horaActual->addMinutes($duracionTurno);
            $horaFinTurno = $horaActual->format('H:i');

            if ($horaActual->gt($horaFin)) {
                break;
            }

            Horario::firstOrCreate(
                [
                    'hora_inicio' => $horaInicioTurno,
                    'hora_fin' => $horaFinTurno,
                    'dia' => $dia,
                    'deporte_id' => $deporteId
                ],
                [
                    'activo' => true
                ]
            );

            //$deporte = Deporte::find($deporteId);
            
            //$deporteNombre = strtolower($deporte->nombre);
            //if ($deporteNombre == 'futbol' || $deporteNombre == 'fútbol') {
            //    $horaActual->subMinutes(30);
            //}
        }
    }

    private function actualizarHorariosExistentes($horariosExistentes, Carbon $horaApertura, Carbon $horaCierre, string $dia, int $deporteId, int $duracionTurno)
    {
        $horaAperturaExistente = Carbon::createFromFormat('H:i:s', $horariosExistentes->first()->hora_inicio);
        $horaCierreExistente = Carbon::createFromFormat('H:i:s', $horariosExistentes->last()->hora_fin);

        if ($horaApertura->lt($horaAperturaExistente)) {
            $this->crearHorarios($horaApertura, $horaAperturaExistente, $dia, $deporteId, $duracionTurno);
        } elseif ($horaApertura->gt($horaAperturaExistente)) {
            Horario::where('dia', $dia)
                   ->where('deporte_id', $deporteId)
                   ->where('hora_inicio', '<', $horaApertura->format('H:i:s'))
                   ->update(['activo' => false]);
        }

        if ($horaCierre->lt($horaCierreExistente)) {
            Horario::where('dia', $dia)
                   ->where('deporte_id', $deporteId)
                   ->where('hora_fin', '>', $horaCierre->format('H:i:s'))
                   ->update(['activo' => false]);
        } elseif ($horaCierre->gt($horaCierreExistente)) {
            $this->crearHorarios($horaCierreExistente, $horaCierre, $dia, $deporteId, $duracionTurno);
        }

        Horario::where('dia', $dia)
               ->where('deporte_id', $deporteId)
               ->whereBetween('hora_inicio', [$horaApertura->format('H:i:s'), $horaCierre->format('H:i:s')])
               ->update(['activo' => true]);
    }
}