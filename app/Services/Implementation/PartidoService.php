<?php
// app/Services/Implementation/PartidoService.php

namespace App\Services\Implementation;

use App\Models\Partido;
use App\Models\Fecha;
use App\Models\Cancha;
use App\Models\Horario;
use App\Services\Interface\PartidoServiceInterface;
use App\Services\Interface\DisponibilidadServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Enums\PartidoEstado;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Models\Zona;
use App\Services\Implementation\TurnoService;
use Illuminate\Support\Facades\Log;

class PartidoService implements PartidoServiceInterface
{
    protected $disponibilidadService;

    public function __construct(DisponibilidadServiceInterface $disponibilidadService)
    {
        $this->disponibilidadService = $disponibilidadService;
    }

    public function getAll()
    {
        return Partido::with('fecha.zona.torneo', 'equipos', 'estadisticas', 'horario', 'cancha', 'ganador')->get();
    }

    public function getById($id)
    {
        return Partido::with('fecha', 'equipos.jugadores', 'estadisticas', 'horario', 'cancha', 'ganador')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'horario_id' => 'nullable|exists:horarios,id',
            'cancha_id' => 'nullable|exists:canchas,id',
            'estado' => ['required', 'string', Rule::in(PartidoEstado::values())],
            'marcador_local' => 'nullable|integer',
            'marcador_visitante' => 'nullable|integer',
            'ganador_id' => 'nullable|exists:equipos,id',
            'fecha_id' => 'required|exists:fechas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $partido = Partido::create($request->all());

        // Asociar los equipos al partido en la tabla pivote
        if ($request->has('equipo_local_id') && $request->has('equipo_visitante_id')) {
            $partido->equipos()->attach([$request->equipo_local_id, $request->equipo_visitante_id]);
        }

        if ($partido->horario_id && $partido->cancha_id && $partido->fecha) {
            app(\App\Services\Implementation\TurnoService::class)->crearTurnoTorneo($partido);
        }

        return response()->json([
            'message' => 'Partido creado correctamente',
            'partido' => $partido,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $partido = Partido::with('equipos')->find($id);

        if (!$partido) {
            return response()->json([
                'message' => 'Partido no encontrado',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'fecha' => 'sometimes|date',
            'horario_id' => 'nullable|exists:horarios,id',
            'cancha_id' => 'nullable|exists:canchas,id',
            'estado' => ['sometimes', 'string', Rule::in(PartidoEstado::values())],
            'marcador_local' => 'nullable|integer',
            'marcador_visitante' => 'nullable|integer',
            'ganador_id' => 'nullable|exists:equipos,id',
            'fecha_id' => 'sometimes|exists:fechas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $partido->update($request->all());

        $partido->refresh();
        if ($partido->horario_id && $partido->cancha_id && $partido->fecha) {
            app(\App\Services\Implementation\TurnoService::class)->crearTurnoTorneo($partido);
        }

        return response()->json([
            'message' => 'Partido actualizado correctamente',
            'partido' => $partido,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $partido = Partido::find($id);

        if (!$partido) {
            return response()->json([
                'message' => 'Partido no encontrado',
                'status' => 404
            ], 404);
        }

        $partido->delete();

        return response()->json([
            'message' => 'Partido eliminado correctamente',
            'status' => 200
        ], 200);
    }

    public function getByFecha($fechaId)
    {
        return Partido::where('fecha_id', $fechaId)->with( 'equipoLocal', 'equipoVisitante', 'estadisticas', 'horario', 'cancha', 'ganador')->get();
    }

    public function getByEquipo($equipoId, $zonaId)
    {
        return Partido::whereHas('equipos', function ($query) use ($equipoId) {
            $query->where('equipo_id', $equipoId);
        })->whereHas('fecha', function ($query) use ($zonaId) {
            $query->where('zona_id', $zonaId);
        })->with('fecha', 'estadisticas', 'horario', 'cancha', 'ganador')->get();
    }

    public function getByEquipoAndZona($equipoId, $zonaId)
    {
        return Partido::whereHas('equipos', function ($query) use ($equipoId) {
            $query->where('equipo_id', $equipoId);
        })->whereHas('fecha', function ($query) use ($zonaId) {
            $query->where('zona_id', $zonaId);
        })->with('fecha', 'estadisticas', 'horario', 'cancha', 'ganador')->get();
    }

    public function getByZona($zonaId)
    {
        return Partido::whereHas('fecha', function ($query) use ($zonaId) {
            $query->where('zona_id', $zonaId);
        })->with('fecha', 'equipoLocal', 'equipoVisitante', 'estadisticas', 'horario', 'cancha', 'ganador')->get();
    }

    public function asignarHoraYCanchaPorZona(Request $request, $zonaId)
    {
        $validator = Validator::make($request->all(), [
            'partidos_a_la_vez' => 'required|integer|min:1',
            'horario_inicio' => 'required|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $partidosALaVez = $request->partidos_a_la_vez;
        $horarioInicio = Carbon::createFromFormat('H:i', $request->horario_inicio);

        // Obtener la zona y el deporte_id del torneo asociado
        $zona = Zona::with('torneo')->find($zonaId);
        if (!$zona || !$zona->torneo) {
            return response()->json([
                'message' => 'Zona o torneo no encontrado',
                'status' => 404
            ], 404);
        }
        $deporteId = $zona->torneo->deporte_id;

        // Obtener las fechas de la zona
        $fechas = Fecha::where('zona_id', $zonaId)->with(['partidos' => function ($query) {
            $query->whereNull('horario_id')->whereNull('cancha_id');
        }])->get();

        if ($fechas->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron fechas para la zona especificada',
                'status' => 404
            ], 404);
        }

        foreach ($fechas as $fecha) {
            $fecha->fecha_inicio = Carbon::parse($fecha->fecha_inicio);
            $partidos = $fecha->partidos;

            if ($partidos->isEmpty()) {
                continue;
            }

            $horarioActual = $horarioInicio->copy();

            $disSemana = $this->getNombreDiaSemana($fecha->fecha_inicio->dayOfWeek);

            foreach ($partidos->chunk($partidosALaVez) as $partidosGrupo) {
                
                $horarioObjeto = Horario::where('hora_inicio', $horarioActual->format('H:i'))
                    ->where('dia', $disSemana)
                    ->where('deporte_id', $deporteId)
                    ->first();

                if (!$horarioObjeto) {
                    $horarioActual->addHour();
                    continue;
                }

                // PASAR deporte_id en el request a disponibilidad
                $canchasDisponiblesResponse = $this->disponibilidadService->getCanchasPorHorarioFecha(new Request([
                    'fecha' => $fecha->fecha_inicio->format('Y-m-d'),
                    'horario_id' => $horarioObjeto->id,
                    'deporte_id' => $deporteId
                ]));

                $canchasDisponiblesData = json_decode(json_encode($canchasDisponiblesResponse->getData()), true);
                $canchasParaEsteHorario = $canchasDisponiblesData['canchas'] ?? [];

                $cantidadCanchasDisponibles = count($canchasParaEsteHorario);
                
                foreach ($partidosGrupo as $index => $partido) {
                    $partido->horario_id = $horarioObjeto->id;
                    
                    if ($cantidadCanchasDisponibles > 0) {
                        $indiceCanchaAUsar = $index % $cantidadCanchasDisponibles;
                        $partido->cancha_id = $canchasParaEsteHorario[$indiceCanchaAUsar]['id'];
                    } else {
                        $partido->cancha_id = null;
                    }
                    
                    $partido->save();

                    app(TurnoService::class)->crearTurnoTorneo($partido);
                }

                $horarioActual->addHour();
            }
        }

        return response()->json([
            'message' => 'Horarios y canchas asignados correctamente a los partidos de la zona',
            'status' => 200
        ], 200);
    }

    /**
     * Obtener el nombre del día de la semana en español.
     */
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