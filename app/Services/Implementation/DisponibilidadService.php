<?php

namespace App\Services\Implementation;

use App\Models\Turno;
use App\Models\Cancha;
use App\Models\Horario;
use \App\Models\EventoHorarioCancha;
use \App\Enums\EventoEstado;
use App\Services\Interface\DisponibilidadServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Deporte;
use App\Models\BloqueoDisponibilidadTurno;

class DisponibilidadService implements DisponibilidadServiceInterface
{
    public function getHorariosNoDisponibles()
    {
        $fecha_inicio = now()->startOfDay();
        $fecha_fin = now()->addDays(30)->endOfDay();

        $canchas_count = Cancha::where('activa', true)->count();

        $turnos = Turno::select(
            'fecha_turno',
            'horario_id',
            DB::raw('COUNT(*) as total_reservas')
        )
        ->whereBetween('fecha_turno', [$fecha_inicio, $fecha_fin])
        ->where('estado', '!=', 'Cancelado')
        ->groupBy('fecha_turno', 'horario_id')
        ->having('total_reservas', '>=', $canchas_count) 
        ->with(['horario:id,hora_inicio,hora_fin'])
        ->get();

        $result = $turnos->groupBy(function($turno) {
            return $turno->fecha_turno->format('Y-m-d');
        })->map(function($grupoTurnos) use ($canchas_count) {
            $horarios = $grupoTurnos->map(function($turno) use ($canchas_count) {
                return [
                    'hora_inicio' => $turno->horario->hora_inicio,
                    'hora_fin' => $turno->horario->hora_fin,
                    'reservas' => $turno->total_reservas,
                    'disponibles' => $canchas_count - $turno->total_reservas
                ];
            });

            return $horarios;
        })->toArray();

        return response()->json([
            'horarios_no_disponibles' => $result,
            'canchas_totales' => $canchas_count,
            'status' => 200
        ], 200);
    }

    public function getHorariosDisponiblesPorFecha(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date_format:Y-m-d',
            'deporte_id' => 'required|exists:deportes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $fecha = Carbon::createFromFormat('Y-m-d', $request->fecha);
        $diaSemana = $this->getNombreDiaSemana($fecha->dayOfWeek);
        $deporte = Deporte::find($request->deporte_id);

        $canchasDeporte = Cancha::where('activa', true)->where('deporte_id', $deporte->id);
        $canchasCount = $canchasDeporte->count();
        $canchasDisponibles = $canchasDeporte->get();
        $canchasIds = $canchasDisponibles->pluck('id')->toArray();

        $horarios = Horario::where('activo', true)
                            ->where('dia', $diaSemana)
                            ->where('deporte_id', $deporte->id)
                            ->orderBy('hora_inicio')
                            ->get();

        // Turnos reservados
        $reservas = Turno::whereDate('fecha_turno', $fecha)
                        ->whereIn('cancha_id', $canchasIds)
                        ->where('estado', '!=', 'Cancelado')
                        ->with('horario')
                        ->get();

        // Eventos reservados
        $eventosReservados = EventoHorarioCancha::whereIn('cancha_id', $canchasIds)
            ->where('estado', EventoEstado::RESERVADO->value)
            ->whereIn('horario_id', $horarios->pluck('id')->toArray())
            ->whereHas('evento', function($q) use ($fecha) {
                $q->where('fecha', $fecha->format('Y-m-d'));
            })
            ->get();

        // --- BLOQUEOS DE DISPONIBILIDAD ---
        $bloqueos = BloqueoDisponibilidadTurno::where('fecha', $fecha->format('Y-m-d'))
            ->whereIn('cancha_id', $canchasIds)
            ->whereIn('horario_id', $horarios->pluck('id')->toArray())
            ->get();
        $bloqueosPorHorario = $bloqueos->groupBy('horario_id');
        $bloqueosPorCancha = $bloqueos->groupBy('cancha_id');

        // Agrupar por horario_id para contar reservas por horario
        $reservasPorHorario = $reservas->groupBy('horario_id');
        $eventosPorHorario = $eventosReservados->groupBy('horario_id');

        $result = [];
        $horariosNoDisponibles = [];

        foreach ($horarios as $horario) {
            // Contar reservas para este horario específico
            $reservasCount = isset($reservasPorHorario[$horario->id]) ? count($reservasPorHorario[$horario->id]) : 0;
            $eventosCount = isset($eventosPorHorario[$horario->id]) ? count($eventosPorHorario[$horario->id]) : 0;
            $bloqueosCount = isset($bloqueosPorHorario[$horario->id]) ? count($bloqueosPorHorario[$horario->id]) : 0;
            $totalOcupadas = $reservasCount + $eventosCount + $bloqueosCount;

            // Un horario no está disponible si todas las canchas están reservadas, bloqueadas o con eventos
            $disponible = $totalOcupadas < $canchasCount;

            if (!$disponible) {
                $horariosNoDisponibles[] = $horario->id;
            }

            $result[] = [
                'id' => $horario->id,
                'hora_inicio' => $horario->hora_inicio,
                'hora_fin' => $horario->hora_fin,
                'disponible' => $disponible
            ];
        }

        // Ahora verificamos los solapamientos entre todos los horarios
        // Creamos un array de horarios con información de solapamiento
        $horariosConSolapamientos = [];
        
        // Para cada par de horarios, verificamos si se solapan
        foreach ($horarios as $horario1) {
            foreach ($horarios as $horario2) {
                if ($horario1->id != $horario2->id) {
                    // Verificar solapamiento: hora_inicio1 < hora_fin2 Y hora_fin1 > hora_inicio2
                    if ($horario1->hora_inicio < $horario2->hora_fin && $horario1->hora_fin > $horario2->hora_inicio) {
                        if (!isset($horariosConSolapamientos[$horario1->id])) {
                            $horariosConSolapamientos[$horario1->id] = [];
                        }
                        $horariosConSolapamientos[$horario1->id][] = $horario2->id;
                    }
                }
            }
        }
        
        // Verificamos si hay canchas suficientes para horarios solapados
        foreach ($horarios as $index => $horario) {
            if (isset($horariosConSolapamientos[$horario->id])) {
                $solapados = $horariosConSolapamientos[$horario->id];

                // IDs de canchas reservadas para este horario (turnos + eventos)
                $canchasReservadasParaEsteHorario = collect(
                    (isset($reservasPorHorario[$horario->id]) ? $reservasPorHorario[$horario->id]->pluck('cancha_id')->toArray() : [])
                )->merge(
                    (isset($eventosPorHorario[$horario->id]) ? $eventosPorHorario[$horario->id]->pluck('cancha_id')->toArray() : [])
                )->unique()->toArray();

                foreach ($solapados as $solapadoId) {
                    // IDs de canchas reservadas para el horario solapado (turnos + eventos)
                    $canchasReservadasParaSolapado = collect(
                        (isset($reservasPorHorario[$solapadoId]) ? $reservasPorHorario[$solapadoId]->pluck('cancha_id')->toArray() : [])
                    )->merge(
                        (isset($eventosPorHorario[$solapadoId]) ? $eventosPorHorario[$solapadoId]->pluck('cancha_id')->toArray() : [])
                    )->unique()->toArray();

                    $todasCanchasReservadas = array_unique(array_merge(
                        $canchasReservadasParaEsteHorario,
                        $canchasReservadasParaSolapado
                    ));

                    if (count($todasCanchasReservadas) >= $canchasCount) {
                        foreach ($result as &$horarioResult) {
                            if ($horarioResult['id'] == $horario->id) {
                                $horarioResult['disponible'] = false;
                                break;
                            }
                        }
                        break; // Salimos del ciclo de solapados, ya sabemos que este horario no está disponible
                    }
                }
            }
        }

        return response()->json(['horarios' => $result], 200);
    }

    public function getCanchasPorHorarioFecha(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date_format:Y-m-d',
            'horario_id' => 'required|exists:horarios,id',
            'deporte_id' => 'required|exists:deportes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $fecha = Carbon::createFromFormat('Y-m-d', $request->fecha);
        $diaSemana = $this->getNombreDiaSemana($fecha->dayOfWeek);
        $deporte = Deporte::find($request->deporte_id);

        $horario = Horario::where('id', $request->horario_id)
                          ->where('dia', $diaSemana)
                          ->where('deporte_id', $deporte->id)
                          ->first();

        if (!$horario) {
            return response()->json([
                'message' => 'Horario no encontrado para el día especificado',
                'status' => 404
            ], 404);
        }

        $canchas = Cancha::where('activa', true)->where('deporte_id', $deporte->id)->get();
        $canchasIds = $canchas->pluck('id')->toArray();

        // Buscar horarios solapados con el horario solicitado
        $horariosSolapados = Horario::where('dia', $diaSemana)
                           ->where('deporte_id', $deporte->id)
                           ->where('activo', true)
                           ->where(function($query) use ($horario) {
                               $query->where(function($q) use ($horario) {
                                   $q->where('hora_inicio', '>=', $horario->hora_inicio)
                                     ->where('hora_inicio', '<', $horario->hora_fin);
                               })->orWhere(function($q) use ($horario) {
                                   $q->where('hora_fin', '>', $horario->hora_inicio)
                                     ->where('hora_fin', '<=', $horario->hora_fin);
                               })->orWhere(function($q) use ($horario) {
                                   $q->where('hora_inicio', '<=', $horario->hora_inicio)
                                     ->where('hora_fin', '>=', $horario->hora_fin);
                               });
                           })
                           ->pluck('id')
                           ->toArray();

        // Obtener todas las reservas para el horario solicitado y los solapados
        $turnos = Turno::whereDate('fecha_turno', $fecha)
                        ->whereIn('cancha_id', $canchasIds)
                        ->whereIn('horario_id', $horariosSolapados)
                        ->where('estado', '!=', 'Cancelado')
                        ->with('cancha')
                        ->get();

        // --- AGREGADO: Verificar eventos reservados ---
        $eventosReservados = EventoHorarioCancha::whereIn('cancha_id', $canchasIds)
        ->whereIn('horario_id', $horariosSolapados)
        ->where('estado', EventoEstado::RESERVADO->value)
        ->whereHas('evento', function($q) use ($fecha) {
            $q->where('fecha', $fecha->format('Y-m-d'));
        })
        ->get();

        // --- AGREGADO: Verificar bloqueos de disponibilidad en horarios solapados ---
        $bloqueosSolapados = BloqueoDisponibilidadTurno::where('fecha', $fecha->format('Y-m-d'))
            ->whereIn('cancha_id', $canchasIds)
            ->whereIn('horario_id', $horariosSolapados)
            ->get();
        $bloqueosPorCanchaSolapados = $bloqueosSolapados->groupBy('cancha_id');

        // Agrupar reservas por cancha_id para facilitar la verificación
        $reservasPorCancha = $turnos->groupBy('cancha_id');
        // Agrupar eventos reservados por cancha_id
        $eventosPorCancha = $eventosReservados->groupBy('cancha_id');

        $bloqueosPorCancha = $bloqueosSolapados->groupBy('cancha_id');

        $result = [];

        foreach ($canchas as $cancha) {
            // Una cancha está disponible si no tiene reservas, ni eventos, ni bloqueos en ningún horario solapado
            $disponible = !isset($reservasPorCancha[$cancha->id])
                && !isset($eventosPorCancha[$cancha->id])
                && !isset($bloqueosPorCanchaSolapados[$cancha->id]);

            $result[] = [
                'id' => $cancha->id,
                'nro' => $cancha->nro,
                'tipo' => $cancha->tipo_cancha,
                'disponible' => $disponible,
                'precio_por_hora' => $cancha->precio_por_hora,
                'seña' => $cancha->seña,
                'descripcion' => $cancha->descripcion
            ];
        }

        return response()->json(['canchas' => $result, 'status' => 200], 200);
    }

    public function getDiasNoDisponibles()
    {
        $inactiveDays = [];

        for ($i = 0; $i < 7; $i++) {
            $horarios = Horario::where('dia', $this->getNombreDiaSemana($i))->get();

            if ($horarios->isEmpty() || $horarios->every(function ($horario) {
                return !$horario->activo;
            })) {
                $inactiveDays[] = $i;
            }
        }

        return response()->json(['inactiveDays' => $inactiveDays, 'status' => 200], 200);
    }

    public function getHorariosDisponiblesTurnosFijos(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $fechaInicio = Carbon::createFromFormat('Y-m-d', $request->fecha_inicio);
        $diaSemana = $this->getNombreDiaSemana($fechaInicio->dayOfWeek);
        $canchasCount = Cancha::where('activa', true)->count();

        // Obtener los horarios activos para ese día de la semana
        $horarios = Horario::where('activo', true)
                            ->where('dia', $diaSemana)
                            ->orderBy('hora_inicio')
                            ->get();

        // Verificar disponibilidad para las próximas 4 semanas
        $fechas = [];
        for ($i = 0; $i < 4; $i++) {
            $fecha = $fechaInicio->copy()->addWeeks($i);
            $fechas[] = $fecha->format('Y-m-d');
        }

        // Obtener todas las reservas existentes para esas fechas
        $reservas = Turno::whereIn('fecha_turno', $fechas)
                        ->where('estado', '!=', 'Cancelado')
                        ->get()
                        ->groupBy(function($turno) {
                            return $turno->fecha_turno->format('Y-m-d') . '_' . $turno->horario_id;
                        });

        // Preparar resultado
        $result = [];
        foreach ($horarios as $horario) {
            $disponibleTodasLasSemanas = true;
            $disponibilidadPorFecha = [];

            foreach ($fechas as $fecha) {
                $key = $fecha . '_' . $horario->id;
                $reservasEnHorario = isset($reservas[$key]) ? count($reservas[$key]) : 0;
                $disponible = $reservasEnHorario < $canchasCount;

                if (!$disponible) {
                    $disponibleTodasLasSemanas = false;
                }

                $disponibilidadPorFecha[$fecha] = $disponible;
            }

            // Solo incluir el horario si está disponible en todas las semanas
            if ($disponibleTodasLasSemanas) {
                $result[] = [
                    'id' => $horario->id,
                    'hora_inicio' => $horario->hora_inicio,
                    'hora_fin' => $horario->hora_fin,
                    'disponible' => $disponibleTodasLasSemanas,
                    'disponibilidad_por_fecha' => $disponibilidadPorFecha
                ];
            }
        }

        return response()->json([
            'horarios' => $result,
            'fechas' => $fechas,
            'status' => 200
        ], 200);
    }

    private function getNombreDiaSemana($diaSemana)
    {
        $dias = [
            0 => 'domingo',
            1 => 'lunes',
            2 => 'martes',
            3 => 'miércoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sábado'
        ];

        return $dias[$diaSemana];
    }
}