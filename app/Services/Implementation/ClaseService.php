<?php

namespace App\Services\Implementation;

use App\Models\Clase;
use App\Services\Interface\ClaseServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use \Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ClaseService implements ClaseServiceInterface
{
    public function getAll()
    {
        $clases = Clase::with('profesor', 'cancha')->get();

        // Agrega los datos completos de los horarios a cada clase
        $clases->transform(function ($clase) {
            $clase->horarios = $clase->horarios; // Esto usa el accesor del modelo
            return $clase;
        });

        return $clases;
    }

    public function getById($id)
    {
        return Clase::with('profesor', 'cancha')->find($id);
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
            'cupo_maximo' => 'required|integer|min:1',
            'precio_mensual' => 'required|numeric|min:0',
            'activa' => 'required|boolean',
            'tipo' => 'in:unica',
            'deporte_id' => 'required|exists:deportes,id',
            'dia' => 'required|string|in:lunes,martes,miércoles,jueves,viernes,sábado,domingo',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $horaInicio = $request->hora_inicio . ':00';
        $horaFin = $request->hora_fin . ':00';

        $horarios = \App\Models\Horario::where('dia', ucfirst($request->dia))
            ->where('deporte_id', $request->deporte_id)
            ->where('activo', true)
            ->where('hora_inicio', '>=', $horaInicio)
            ->where('hora_fin', '<=', $horaFin)
            ->orderBy('hora_inicio')
            ->get()
            ->filter(function($horario) use ($horaInicio, $horaFin) {
                if ($horario->hora_fin === '00:00:00' && $horaFin !== '00:00:00') {
                    return false;
                }
                return $horario->hora_inicio >= $horaInicio && $horario->hora_fin <= $horaFin;
            });

        if ($horarios->isEmpty()) {
            return response()->json([
                'message' => "No hay horarios disponibles para {$request->dia} en el rango {$horaInicio}-{$horaFin}",
                'status' => 400
            ], 400);
        }

        // Verificar rango completo (sin huecos)
        $rangoCompleto = $this->verificarRangoCompleto($horarios, $horaInicio, $horaFin);
        if (!$rangoCompleto) {
            return response()->json([
                'message' => "Los horarios de {$request->dia} no cubren completamente el rango {$horaInicio}-{$horaFin}",
                'status' => 400
            ], 400);
        }

        $esUnaHora = $horarios->count() === 1;

        DB::beginTransaction();
        try {
            $clase = Clase::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
                'profesor_id' => $request->profesor_id,
                'cancha_id' => $request->cancha_id,
                'horario_ids' => $horarios->pluck('id')->toArray(),
                'cupo_maximo' => $request->cupo_maximo,
                'precio_mensual' => $request->precio_mensual,
                'activa' => $request->activa,
                'tipo' => 'unica',
                'duracion' => $horarios->count(),
            ]);

            foreach ($horarios as $horario) {
                \App\Models\BloqueoDisponibilidadTurno::create([
                    'fecha' => $request->fecha_inicio,
                    'cancha_id' => $request->cancha_id,
                    'horario_id' => $horario->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Clase creada y turno bloqueado correctamente',
                'clase' => $clase,
                'status' => 201
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la clase',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
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
            'dias_horarios' => 'required|array|min:1',
            'dias_horarios.*.dia' => 'required|string|in:lunes,martes,miércoles,jueves,viernes,sábado,domingo',
            'dias_horarios.*.hora_inicio' => 'required|date_format:H:i',
            'dias_horarios.*.hora_fin' => 'required|date_format:H:i|after:dias_horarios.*.hora_inicio',
            'deporte_id' => 'required|exists:deportes,id',
            'cancha_id' => 'required|exists:canchas,id',
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
        $deporteId = $request->deporte_id;
        $canchaId = $request->cancha_id;

        // Mapear días a números
        $diasMap = [
            'domingo' => 0, 'lunes' => 1, 'martes' => 2, 'miércoles' => 3,
            'jueves' => 4, 'viernes' => 5, 'sábado' => 6,
        ];
        $diasHorarios = collect($request->dias_horarios);

        // Recopilar fechas a crear por día
        $fechasPorDia = [];
        $current = $fechaInicio->copy();
        while ($current->lessThanOrEqualTo($fechaFin)) {
            foreach ($diasHorarios as $dh) {
                if ($current->dayOfWeek === $diasMap[$dh['dia']]) {
                    $fechasPorDia[] = [
                        'fecha' => $current->copy(),
                        'dia' => $dh['dia'],
                        'hora_inicio' => $dh['hora_inicio'] . ':00',
                        'hora_fin' => $dh['hora_fin'] . ':00'
                    ];
                }
            }
            $current->addDay();
        }

        // Obtener horarios requeridos por fecha/día
        $erroresValidacion = [];
        $clasesCreadas = [];
        $bloqueosCreados = [];

        DB::beginTransaction();
        try {
            foreach ($fechasPorDia as $item) {
                $horarios = \App\Models\Horario::where('dia', ucfirst($item['dia']))
                    ->where('deporte_id', $deporteId)
                    ->where('activo', true)
                    ->where('hora_inicio', '>=', $item['hora_inicio'])
                    ->where('hora_fin', '<=', $item['hora_fin'])
                    ->orderBy('hora_inicio')
                    ->get()
                    ->filter(function($horario) use ($item) {
                        if ($horario->hora_fin === '00:00:00' && $item['hora_fin'] !== '00:00:00') {
                            return false;
                        }
                        return $horario->hora_inicio >= $item['hora_inicio'] && $horario->hora_fin <= $item['hora_fin'];
                    });

                if ($horarios->isEmpty()) {
                    $erroresValidacion[] = "No hay horarios disponibles para {$item['dia']} en el rango {$item['hora_inicio']}-{$item['hora_fin']} ({$item['fecha']->toDateString()})";
                    continue;
                }

                $clase = Clase::create([
                    'nombre' => $request->nombre,
                    'descripcion' => $request->descripcion,
                    'fecha_inicio' => $item['fecha']->toDateString(),
                    'fecha_fin' => $item['fecha']->toDateString(),
                    'profesor_id' => $request->profesor_id,
                    'cancha_id' => $canchaId,
                    'horario_ids' => $horarios->pluck('id')->toArray(),
                    'cupo_maximo' => $request->cupo_maximo,
                    'precio_mensual' => $request->precio_mensual,
                    'activa' => $request->activa,
                    'tipo' => 'fija',
                    'duracion' => $horarios->count(),
                ]);
                $clasesCreadas[] = $clase;

                foreach ($horarios as $horario) {
                    $bloqueo = \App\Models\BloqueoDisponibilidadTurno::create([
                        'fecha' => $item['fecha']->toDateString(),
                        'cancha_id' => $canchaId,
                        'horario_id' => $horario->id,
                    ]);
                    $bloqueosCreados[] = $bloqueo;
                }
            }

            if (!empty($erroresValidacion)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Errores en los horarios solicitados',
                    'errores' => $erroresValidacion,
                    'status' => 400
                ], 400);
            }

            DB::commit();

            return response()->json([
                'message' => 'Clases fijas creadas y turnos bloqueados correctamente',
                'clases' => $clasesCreadas,
                'bloqueos' => count($bloqueosCreados),
                'total_clases' => count($clasesCreadas),
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin->toDateString(),
                'status' => 201
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear las clases',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    /**
     * Obtiene los horarios que cubren el rango solicitado para cada día
     */
    private function obtenerHorariosRango($diasSemana, $horaInicio, $horaFin, $deporteId)
    {
        $horariosRequeridos = [];
        $erroresValidacion = [];

        foreach ($diasSemana as $dia) {
            $diaCapitalizado = ucfirst($dia);
            
            // Buscar horarios que cubran el rango solicitado
            $horarios = \App\Models\Horario::where('dia', $diaCapitalizado)
                ->where('deporte_id', $deporteId)
                ->where('activo', true)
                ->where('hora_inicio', '>=', $horaInicio)
                ->where('hora_fin', '<=', $horaFin)
                ->orderBy('hora_inicio')
                ->get();

            if ($horarios->isEmpty()) {
                $erroresValidacion[] = "No hay horarios disponibles para {$dia} en el rango {$horaInicio}-{$horaFin}";
                continue;
            }

            // Verificar que los horarios cubran completamente el rango
            $rangoCompleto = $this->verificarRangoCompleto($horarios, $horaInicio, $horaFin);
            if (!$rangoCompleto) {
                $erroresValidacion[] = "Los horarios de {$dia} no cubren completamente el rango {$horaInicio}-{$horaFin}";
                continue;
            }

            $horariosRequeridos[$dia] = $horarios;
        }

        return [
            'horarios' => $horariosRequeridos,
            'errores' => $erroresValidacion
        ];
    }

    /**
     * Verifica que los horarios cubran completamente el rango solicitado
     */
    private function verificarRangoCompleto($horarios, $horaInicio, $horaFin)
    {
        if ($horarios->isEmpty()) return false;
        
        $horarios = $horarios->sortBy('hora_inicio');
        
        $inicioEsperado = $horaInicio;
        
        foreach ($horarios as $horario) {
            if ($horario->hora_inicio !== $inicioEsperado) {
                return false; // Hay un gap
            }
            $inicioEsperado = $horario->hora_fin;
        }
        
        return $inicioEsperado === $horaFin;
    }
}