<?php

namespace App\Services\Implementation;

use App\Models\Clase;
use App\Services\Interface\ClaseServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use \Carbon\Carbon;
use App\Services\Implementation\DisponibilidadService;

class ClaseService implements ClaseServiceInterface
{
    public function getAll()
    {
        return Clase::with('profesor', 'cancha', 'horario')->get();
    }

    public function getById($id)
    {
        return Clase::with('profesor', 'cancha', 'horario')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
            'profesor_id' => 'required|exists:profesores,id',
            'cancha_id' => 'required|exists:canchas,id',
            'duracion' => 'required|integer|min:1',
            'cupo_maximo' => 'required|integer|min:1',
            'precio_mensual' => 'required|numeric|min:0',
            'activa' => 'required|boolean',
            'tipo' => 'in:unica',
            'horario_id' => 'required_without:horario_ids|exists:horarios,id',
            'horario_ids' => 'nullable|array',
            'horario_ids.*' => 'exists:horarios,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $disponibilidadService = app(DisponibilidadService::class);

        // Si la duración es 1 hora, solo se verifica un horario
        if ($request->duracion == 1) {
            $disponibilidadResponse = $disponibilidadService->getCanchasPorHorarioFecha(new Request([
                'fecha' => $request->fecha_inicio,
                'horario_id' => $request->horario_id,
                'deporte_id' => \App\Models\Horario::find($request->horario_id)?->deporte_id,
            ]));

            $data = $disponibilidadResponse->getData(true);
            $canchasDisponibles = collect(isset($data['canchas']) ? $data['canchas'] : [])->where('disponible', true);

            if ($canchasDisponibles->isEmpty()) {
                return response()->json([
                    'message' => 'No hay canchas disponibles para la fecha y horario seleccionados',
                    'status' => 409
                ], 409);
            }

            $canchaId = $canchasDisponibles->first()['id'];
            $horarioId = $request->horario_id;
            $horarioIds = null;
        } else {
            // Si la duración es mayor a 1, se verifica para todos los horarios
            $horarioIds = $request->horario_ids;
            $canchaId = null;

            foreach ($horarioIds as $horarioId) {
                $disponibilidadResponse = $disponibilidadService->getCanchasPorHorarioFecha(new Request([
                    'fecha' => $request->fecha_inicio,
                    'horario_id' => $horarioId,
                    'deporte_id' => \App\Models\Horario::find($horarioId)?->deporte_id,
                ]));

                $data = $disponibilidadResponse->getData(true);
                $canchasDisponibles = collect(isset($data['canchas']) ? $data['canchas'] : [])->where('disponible', true);

                if ($canchasDisponibles->isEmpty()) {
                    return response()->json([
                        'message' => 'No hay canchas disponibles para uno de los horarios seleccionados',
                        'status' => 409
                    ], 409);
                }

                // Toma la primera cancha disponible del primer horario
                if (!$canchaId) {
                    $canchaId = $canchasDisponibles->first()['id'];
                }
            }
        }

        $clase = Clase::create([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
            'profesor_id' => $request->profesor_id,
            'cancha_id' => $canchaId,
            'horario_id' => $request->duracion == 1 ? $horarioId : null,
            'horario_ids' => $request->duracion > 1 ? $horarioIds : null,
            'cupo_maximo' => $request->cupo_maximo,
            'precio_mensual' => $request->precio_mensual,
            'activa' => $request->activa,
            'tipo' => 'unica',
            'duracion' => $request->duracion,
        ]);

        return response()->json([
            'message' => 'Clase creada correctamente',
            'clase' => $clase,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $clase = Clase::find($id);

        if (!$clase) {
            return response()->json([
                'message' => 'Clase no encontrada',
                'status' => 404
            ], 404);
        }

        $clase->update($request->all());

        return response()->json([
            'message' => 'Clase actualizada correctamente',
            'clase' => $clase,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $clase = Clase::find($id);

        if (!$clase) {
            return response()->json([
                'message' => 'Clase no encontrada',
                'status' => 404
            ], 404);
        }

        $clase->delete();

        return response()->json([
            'message' => 'Clase eliminada correctamente',
            'status' => 200
        ], 200);
    }

    public function crearClasesFijas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:255',
            'profesor_id' => 'required|exists:profesores,id',
            'cupo_maximo' => 'required|integer|min:1',
            'precio_mensual' => 'required|numeric|min:0',
            'activa' => 'required|boolean',
            'fecha_inicio' => 'required|date',
            'duracion_meses' => 'required|integer|min:1',
            'dias_semana' => 'required|array|min:1',
            'dias_semana.*' => 'required|string|in:lunes,martes,miércoles,jueves,viernes,sábado,domingo',
            'duracion' => 'required|integer|min:1',
            'hora_inicio' => 'required|date_format:H:i',
            'deporte_id' => 'required|exists:deportes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $disponibilidadService = app(DisponibilidadService::class);

        $fechaInicio = Carbon::parse($request->fecha_inicio)->startOfDay();
        $fechaFin = $fechaInicio->copy()->addMonths($request->duracion_meses);
        $diasSemana = $request->dias_semana; // Ej: ['lunes', 'miércoles']

        $horaInicio = $request->hora_inicio;
        $duracion = $request->duracion;
        $horaFin = \Carbon\Carbon::createFromFormat('H:i', $horaInicio)->addHours($duracion)->format('H:i:s');
        $deporteId = $request->deporte_id;

        // Mapear días a números para comparación (0=domingo, 1=lunes, ..., 6=sábado)
        $diasMap = [
            'domingo' => 0,
            'lunes' => 1,
            'martes' => 2,
            'miércoles' => 3,
            'jueves' => 4,
            'viernes' => 5,
            'sábado' => 6,
        ];
        $diasNumeros = array_map(fn($d) => $diasMap[$d], $diasSemana);

        // Ajustar $current al primer día válido
        $current = $fechaInicio->copy();
        while (!in_array($current->dayOfWeek, $diasNumeros) && $current->lessThanOrEqualTo($fechaFin)) {
            $current->addDay();
        }

        // Ahora el bucle principal
        while ($current->lessThanOrEqualTo($fechaFin)) {
            $diaNombre = strtolower($current->locale('es')->isoFormat('dddd'));
            if (in_array($diaNombre, $diasSemana)) {
                // Buscar el horario_id para este día, hora_inicio, hora_fin y deporte
                $horario = \App\Models\Horario::where('dia', ucfirst($diaNombre))
                    ->where('hora_inicio', $horaInicio . ':00')
                    ->where('hora_fin', $horaFin)
                    ->where('deporte_id', $deporteId)
                    ->first();

                if (!$horario) {
                    continue;
                }

                $disponibilidadResponse = $disponibilidadService->getCanchasPorHorarioFecha(new Request([
                    'fecha' => $current->toDateString(),
                    'horario_id' => $horario->id,
                    'deporte_id' => $deporteId,
                ]));

                $data = $disponibilidadResponse->getData(true);
                $canchasDisponibles = collect($data['canchas'] ?? [])->where('disponible', true);

                if ($canchasDisponibles->isEmpty()) continue;

                $canchaId = $canchasDisponibles->first()['id'];

                $clase = Clase::create([
                    'nombre' => $request->nombre,
                    'descripcion' => $request->descripcion,
                    'fecha_inicio' => $current->toDateString(),
                    'fecha_fin' => $current->toDateString(),
                    'profesor_id' => $request->profesor_id,
                    'cancha_id' => $canchaId,
                    'horario_id' => $horario->id,
                    'cupo_maximo' => $request->cupo_maximo,
                    'precio_mensual' => $request->precio_mensual,
                    'activa' => $request->activa,
                    'tipo' => 'fija',
                    'duracion' => $duracion,
                ]);
                $clasesCreadas[] = $clase;
            }
            $current->addDay();
        }

        return response()->json([
            'message' => 'Clases fijas creadas correctamente',
            'clases' => $clasesCreadas,
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaFin->toDateString(),
            'status' => 201
        ], 201);
    }
}