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
        return Partido::with('fecha', 'equipos', 'estadisticas', 'horario', 'cancha', 'ganador')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'horario_id' => 'required|exists:horarios,id',
            'cancha_id' => 'required|exists:canchas,id',
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

        return response()->json([
            'message' => 'Partido creado correctamente',
            'partido' => $partido,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $partido = Partido::find($id);

        if (!$partido) {
            return response()->json([
                'message' => 'Partido no encontrado',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'horario_id' => 'required|exists:horarios,id',
            'cancha_id' => 'required|exists:canchas,id',
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
        return Partido::where('fecha_id', $fechaId)->with('equipos', 'estadisticas', 'horario', 'cancha', 'ganador')->get();
    }

    public function getByEquipo($equipoId)
    {
        return Partido::whereHas('equipos', function ($query) use ($equipoId) {
            $query->where('equipo_id', $equipoId);
        })->with('fecha', 'estadisticas', 'horario', 'cancha', 'ganador')->get();
    }

    /* public function asignarHoraYCancha(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'partidos_a_la_vez' => 'required|integer|min:1',
            'horario_id' => 'required|exists:horarios,id',
            'fecha_id' => 'required|exists:fechas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $partidosALaVez = $request->partidos_a_la_vez;
        $horarioId = $request->horario_id;
        $fecha = Fecha::with('partidos')->find($request->fecha_id);

        if (!$fecha) {
            return response()->json([
                'message' => 'Fecha no encontrada',
                'status' => 404
            ], 404);
        }

        $partidos = $fecha->partidos;

        if ($partidos->isEmpty()) {
            return response()->json([
                'message' => 'No hay partidos asociados a esta fecha',
                'status' => 404
            ], 404);
        }

        // Verificar disponibilidad de canchas
        $canchasDisponiblesResponse = $this->disponibilidadService->getCanchasPorHorarioFecha(new Request(['fecha' => Carbon::parse($fecha->fecha_inicio)->format('Y-m-d'), 'horario_id' => $horarioId]));
        $canchasDisponibles = json_decode(json_encode($canchasDisponiblesResponse->getData()), true);

        if (empty($canchasDisponibles) || count($canchasDisponibles) < $partidosALaVez) {
            return response()->json([
                'message' => 'No hay suficientes canchas disponibles para los partidos a la vez',
                'status' => 400
            ], 400);
        }

        // Asignar horarios y canchas a los partidos
        foreach ($partidos as $partido) {
            if (empty($canchasDisponibles)) {
                return response()->json([
                    'message' => 'No hay suficientes canchas disponibles para asignar a todos los partidos',
                    'status' => 400
                ], 400);
            }

            $partido->horario_id = $horarioId;
            $partido->cancha_id = $canchasDisponibles[0]['id'];
            $partido->save();

            // Eliminar la cancha asignada de la lista disponible
            array_shift($canchasDisponibles);

            // Si se han asignado todos los partidos a la vez, reiniciar la lista de canchas disponibles
            if (count($canchasDisponibles) < $partidosALaVez) {
                $canchasDisponiblesResponse = $this->disponibilidadService->getCanchasPorHorarioFecha(new Request(['fecha' => Carbon::parse($fecha->fecha_inicio)->format('Y-m-d'), 'horario_id' => $horarioId]));
                $canchasDisponibles = json_decode(json_encode($canchasDisponiblesResponse->getData()), true);
            }
        }

        return response()->json([
            'message' => 'Horarios y canchas asignados correctamente',
            'status' => 200
        ], 200);
    } */
}