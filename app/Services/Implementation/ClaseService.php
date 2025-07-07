<?php

namespace App\Services\Implementation;

use App\Models\Clase;
use App\Services\Interface\ClaseServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use \Carbon\Carbon;

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
            'fecha' => 'required|date',
            'profesor_id' => 'required|exists:profesores,id',
            'cancha_id' => 'required|exists:canchas,id',
            'horario_id' => 'required|exists:horarios,id',
            'cupo_maximo' => 'required|integer|min:1',
            'precio_mensual' => 'required|numeric|min:0',
            'activa' => 'required|boolean',
            'tipo' => 'required|string|in:unica,fija',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        // Obtener el horario base para extraer hora_inicio, hora_fin y deporte_id
        $horarioBase = \App\Models\Horario::find($request->horario_id);
        if (!$horarioBase) {
            return response()->json([
                'message' => 'Horario base no encontrado',
                'status' => 404
            ], 404);
        }

        $horaInicio = $horarioBase->hora_inicio;
        $horaFin = $horarioBase->hora_fin;
        $deporteId = $horarioBase->deporte_id;

        // Calcular el día de la semana de la fecha de la clase
        $fechaClase = \Carbon\Carbon::parse($request->fecha);
        $diaNombre = ucfirst($fechaClase->locale('es')->isoFormat('dddd'));

        // Buscar el horario_id correcto para ese día
        $horario = \App\Models\Horario::where('dia', $diaNombre)
            ->where('hora_inicio', $horaInicio)
            ->where('hora_fin', $horaFin)
            ->where('deporte_id', $deporteId)
            ->first();

        if (!$horario) {
            return response()->json([
                'message' => 'No existe un horario para ese día, rango horario y deporte',
                'status' => 404
            ], 404);
        }

        $clase = Clase::create([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'fecha' => $request->fecha,
            'profesor_id' => $request->profesor_id,
            'cancha_id' => $request->cancha_id,
            'horario_id' => $horario->id,
            'cupo_maximo' => $request->cupo_maximo,
            'precio_mensual' => $request->precio_mensual,
            'activa' => $request->activa,
            'tipo' => $request->tipo,
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
            'cancha_id' => 'required|exists:canchas,id',
            'horario_id' => 'required|exists:horarios,id',
            'cupo_maximo' => 'required|integer|min:1',
            'precio_mensual' => 'required|numeric|min:0',
            'activa' => 'required|boolean',
            'fecha_inicio' => 'required|date',
            'duracion_meses' => 'required|integer|min:1',
            'dias_semana' => 'required|array|min:1',
            'dias_semana.*' => 'required|string|in:lunes,martes,miércoles,jueves,viernes,sábado,domingo',
            'tipo' => 'required|string|in:fija',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $fechaInicio = Carbon::parse($request->fecha_inicio)->startOfDay();
        $fechaFin = $fechaInicio->copy()->addMonths($request->duracion_meses);
        $diasSemana = $request->dias_semana;

        // Obtener el horario base para extraer hora_inicio, hora_fin y deporte_id
        $horarioBase = \App\Models\Horario::find($request->horario_id);
        if (!$horarioBase) {
            return response()->json([
                'message' => 'Horario base no encontrado',
                'status' => 404
            ], 404);
        }

        $horaInicio = $horarioBase->hora_inicio;
        $horaFin = $horarioBase->hora_fin;
        $deporteId = $horarioBase->deporte_id;

        $clasesCreadas = [];
        $current = $fechaInicio->copy();

        while ($current->lessThan($fechaFin)) {
            // Día de la semana con mayúscula inicial (ej: 'Lunes')
            $diaNombre = ucfirst($current->locale('es')->isoFormat('dddd'));
            if (in_array(strtolower($diaNombre), $diasSemana)) {
                // Buscar el horario_id correcto para este día
                $horario = \App\Models\Horario::where('dia', $diaNombre)
                    ->where('hora_inicio', $horaInicio)
                    ->where('hora_fin', $horaFin)
                    ->where('deporte_id', $deporteId)
                    ->first();

                if (!$horario) {
                    // Si no existe, puedes omitir o lanzar error, aquí lo omitimos
                    $current->addDay();
                    continue;
                }

                $clase = Clase::create([
                    'nombre' => $request->nombre,
                    'descripcion' => $request->descripcion,
                    'fecha' => $current->toDateString(),
                    'profesor_id' => $request->profesor_id,
                    'cancha_id' => $request->cancha_id,
                    'horario_id' => $horario->id,
                    'cupo_maximo' => $request->cupo_maximo,
                    'precio_mensual' => $request->precio_mensual,
                    'activa' => $request->activa,
                    'tipo' => 'fija',
                ]);
                $clasesCreadas[] = $clase;
            }
            $current->addDay();
        }

        return response()->json([
            'message' => 'Clases fijas creadas correctamente',
            'clases' => $clasesCreadas,
            'status' => 201
        ], 201);
    }
}