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
            'dias.*.hora_cierre' => [
                'nullable',
                'date_format:H:i',
                function ($attribute, $value, $fail) use ($request) {
                    if (!$value) return; // Si es null, está bien
                    
                    $diaParts = explode('.', $attribute);
                    $diaKey = $diaParts[1];
                    $horaApertura = $request->input("dias.{$diaKey}.hora_apertura");
                    
                    if (!$horaApertura) return; // Si no hay hora de apertura, está bien
                    
                    // Convertir las horas a minutos para facilitar la comparación
                    $minutosApertura = $this->horaAMinutos($horaApertura);
                    $minutosCierre = $this->horaAMinutos($value);
                    
                    // Permitir que hora_cierre sea "00:00" (medianoche = 1440 minutos)
                    if ($value === '00:00') {
                        $minutosCierre = 1440; // 24 * 60 = 1440 minutos
                    }
                    
                    // Validar que la hora de cierre sea mayor que la de apertura
                    if ($minutosCierre <= $minutosApertura) {
                        $fail('La hora de cierre debe ser posterior a la hora de apertura, o 00:00 para indicar medianoche.');
                    }
                }
            ],
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
                
                // Manejar el caso especial de medianoche (00:00)
                if ($horas['hora_cierre'] === '00:00') {
                    // Crear un Carbon para medianoche del día siguiente
                    $horaCierre = Carbon::createFromFormat('H:i', '23:59')->addMinute();
                } else {
                    $horaCierre = Carbon::createFromFormat('H:i', $horas['hora_cierre']);
                }

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

    public function setHorarioSemanaCompleta($horaApertura, $horaCierre, $deporteId)
    {
        $validator = Validator::make([
            'hora_apertura' => $horaApertura,
            'hora_cierre' => $horaCierre,
            'deporte_id' => $deporteId
        ], [
            'hora_apertura' => 'required|date_format:H:i',
            'hora_cierre' => 'required|date_format:H:i',
            'deporte_id' => 'required|exists:deportes,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $diasSemana = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

        $data = [
            'dias' => [],
            'deporte_id' => $deporteId
        ];

        foreach ($diasSemana as $dia) {
            $data['dias'][$dia] = [
                'hora_apertura' => $horaApertura,
                'hora_cierre' => $horaCierre
            ];
        }

        // Pasa el array como un nuevo Request
        return $this->configurarHorarios(new Request($data));
    }

    private function crearHorarios(Carbon $horaInicio, Carbon $horaFin, string $dia, int $deporteId, int $duracionTurno)
    {
        $horaActual = $horaInicio->copy();
        
        // Manejar el caso especial cuando la hora fin es medianoche (00:00)
        $esMedianoche = $horaFin->format('H:i') === '00:00';
        
        while (true) {
            $horaInicioTurno = $horaActual->format('H:i');
            $horaActual->addMinutes($duracionTurno);
            $horaFinTurno = $horaActual->format('H:i');

            // Para medianoche, permitir que se cree el horario que termina exactamente en 00:00
            if ($esMedianoche) {
                // Crear el horario ANTES de verificar si pasamos medianoche
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
                
                // DESPUÉS verificar si ya llegamos a medianoche para salir del bucle
                if ($horaActual->format('H:i') === '00:00') {
                    break;
                }
            } else {
                // Lógica normal: verificar ANTES de crear el horario
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
            }

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
        
        // Manejar correctamente la hora de cierre existente cuando es 00:00:00 (medianoche)
        $ultimaHoraFin = $horariosExistentes->last()->hora_fin;
        if ($ultimaHoraFin === '00:00:00') {
            // Si es medianoche, crear Carbon para el final del día
            $horaCierreExistente = Carbon::createFromFormat('H:i', '23:59')->addMinute();
        } else {
            $horaCierreExistente = Carbon::createFromFormat('H:i:s', $ultimaHoraFin);
        }

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

        // Manejar el rango de activación considerando medianoche
        $inicioRango = $horaApertura->format('H:i:s');
        $finRango = ($horaCierre->format('H:i') === '00:00') ? '00:00:00' : $horaCierre->format('H:i:s');

        Horario::where('dia', $dia)
               ->where('deporte_id', $deporteId)
               ->where(function($query) use ($inicioRango, $finRango) {
                   if ($finRango === '00:00:00') {
                       // Para medianoche, incluir desde hora_inicio hasta el final del día
                       $query->where('hora_inicio', '>=', $inicioRango);
                   } else {
                       // Lógica normal
                       $query->whereBetween('hora_inicio', [$inicioRango, $finRango]);
                   }
               })
               ->update(['activo' => true]);
    }

    /**
     * Convierte una hora en formato H:i a minutos desde medianoche
     */
    private function horaAMinutos($hora)
    {
        list($horas, $minutos) = explode(':', $hora);
        return (int)$horas * 60 + (int)$minutos;
    }
}