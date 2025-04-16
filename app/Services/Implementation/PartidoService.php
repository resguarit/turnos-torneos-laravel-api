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

    public function getByEquipo($equipoId)
    {
        return Partido::whereHas('equipos', function ($query) use ($equipoId) {
            $query->where('equipo_id', $equipoId);
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

        // Obtener las fechas de la zona
        $fechas = Fecha::where('zona_id', $zonaId)->with(['partidos' => function ($query) {
            $query->whereNull('horario_id')->whereNull('cancha_id'); // Filtrar partidos sin horario ni cancha
        }])->get();

        if ($fechas->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron fechas para la zona especificada',
                'status' => 404
            ], 404);
        }

        foreach ($fechas as $fecha) {
            $fecha->fecha_inicio = Carbon::parse($fecha->fecha_inicio); // Convertir a Carbon
            $partidos = $fecha->partidos;

            if ($partidos->isEmpty()) {
                continue; // Si no hay partidos en la fecha, pasar a la siguiente
            }

            $horarioActual = $horarioInicio->copy();

            foreach ($partidos->chunk($partidosALaVez) as $partidosGrupo) {
                // Verificar disponibilidad de canchas para el horario y la fecha
                $diaSemana = $this->getNombreDiaSemana($fecha->fecha_inicio->dayOfWeek);

                $horario = Horario::where('hora_inicio', $horarioActual->format('H:i'))
                    ->where('dia', $diaSemana)
                    ->first();

                if (!$horario) {
                    // Si no se encuentra el horario, continuar con el siguiente grupo
                    continue;
                }

                $canchasDisponiblesResponse = $this->disponibilidadService->getCanchasPorHorarioFecha(new Request([
                    'fecha' => $fecha->fecha_inicio->format('Y-m-d'),
                    'horario_id' => $horario->id
                ]));

                $canchasDisponibles = json_decode(json_encode($canchasDisponiblesResponse->getData()), true);

                if (empty($canchasDisponibles['canchas'])) {
                    // Si no hay canchas disponibles, continuar con el siguiente grupo
                    continue;
                }

                // Iterar sobre los partidos y asignar canchas secuencialmente
                $canchas = $canchasDisponibles['canchas'];
                foreach ($partidosGrupo as $index => $partido) {
                    if (isset($canchas[$index])) {
                        $partido->horario_id = $horario->id;
                        $partido->cancha_id = $canchas[$index]['id']; // Asignar una cancha diferente
                        $partido->save();
                    } else {
                        // Si no hay más canchas disponibles, dejar el partido sin asignar
                        $partido->horario_id = $horario->id;
                        $partido->cancha_id = null;
                        $partido->save();
                    }
                }

                // Incrementar el horario para el siguiente grupo de partidos
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