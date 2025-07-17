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
        $clases = Clase::with('profesor')->get();

        // Agregar datos completos de horarios y canchas
        $clases->transform(function ($clase) {
            $clase->horarios = $clase->horarios;
            $clase->canchas = $clase->canchas;
            return $clase;
        });

        return $clases;
    }

    public function getById($id)
    {
        $clase = Clase::with('profesor')->find($id);
        if ($clase) {
            $clase->horarios = $clase->horarios;
            $clase->canchas = $clase->canchas;
        }
        return $clase;
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
            'profesor_id' => 'required|exists:profesores,id',
            'cancha_ids' => 'required|array|min:1',
            'cancha_ids.*' => 'exists:canchas,id',
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

        $rangoCompleto = $this->verificarRangoCompleto($horarios, $horaInicio, $horaFin);
        if (!$rangoCompleto) {
            return response()->json([
                'message' => "Los horarios de {$request->dia} no cubren completamente el rango {$horaInicio}-{$horaFin}",
                'status' => 400
            ], 400);
        }

        DB::beginTransaction();
        try {
            $clase = Clase::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
                'profesor_id' => $request->profesor_id,
                'cancha_ids' => $request->cancha_ids,
                'horario_ids' => $horarios->pluck('id')->toArray(),
                'cupo_maximo' => $request->cupo_maximo,
                'precio_mensual' => $request->precio_mensual,
                'activa' => $request->activa,
                'tipo' => 'unica',
                'duracion' => $horarios->count(),
            ]);

            // Crear bloqueos para todas las canchas
            foreach ($request->cancha_ids as $canchaId) {
                foreach ($horarios as $horario) {
                    \App\Models\BloqueoDisponibilidadTurno::create([
                        'fecha' => $request->fecha_inicio,
                        'cancha_id' => $canchaId,
                        'horario_id' => $horario->id,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Clase creada y turnos bloqueados correctamente',
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

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string|max:255',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'sometimes|date',
            'profesor_id' => 'sometimes|exists:profesores,id',
            'cancha_ids' => 'sometimes|array|min:1',
            'cancha_ids.*' => 'exists:canchas,id',
            'cupo_maximo' => 'sometimes|integer|min:1',
            'precio_mensual' => 'sometimes|numeric|min:0',
            'activa' => 'sometimes|boolean',
            'horario_ids' => 'sometimes|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Eliminar bloqueos anteriores para todas las canchas asociadas
            $canchaIdsAnteriores = $clase->cancha_ids ?? [];
            foreach ($canchaIdsAnteriores as $canchaId) {
                \App\Models\BloqueoDisponibilidadTurno::where('cancha_id', $canchaId)
                    ->whereIn('horario_id', $clase->horario_ids ?? [])
                    ->where('fecha', '>=', $clase->fecha_inicio)
                    ->where('fecha', '<=', $clase->fecha_fin)
                    ->delete();
            }

            $horario_ids = $request->horario_ids ?? $clase->horario_ids;
            $cancha_ids = $request->cancha_ids ?? $clase->cancha_ids;

            // Actualizar la clase
            $clase->update([
                'nombre' => $request->nombre ?? $clase->nombre,
                'descripcion' => $request->descripcion ?? $clase->descripcion,
                'fecha_inicio' => $request->fecha_inicio ?? $clase->fecha_inicio,
                'fecha_fin' => $request->fecha_fin ?? $clase->fecha_fin,
                'profesor_id' => $request->profesor_id ?? $clase->profesor_id,
                'cancha_ids' => $cancha_ids,
                'horario_ids' => $horario_ids,
                'cupo_maximo' => $request->cupo_maximo ?? $clase->cupo_maximo,
                'precio_mensual' => $request->precio_mensual ?? $clase->precio_mensual,
                'activa' => $request->activa ?? $clase->activa,
                'duracion' => count($horario_ids),
            ]);

            // Crear nuevos bloqueos para todas las canchas
            foreach ($cancha_ids as $canchaId) {
                foreach ($horario_ids as $horarioId) {
                    $fechaActual = $request->fecha_inicio ?? $clase->fecha_inicio;
                    $fechaFin = $request->fecha_fin ?? $clase->fecha_fin;
                    while ($fechaActual <= $fechaFin) {
                        \App\Models\BloqueoDisponibilidadTurno::create([
                            'fecha' => $fechaActual,
                            'cancha_id' => $canchaId,
                            'horario_id' => $horarioId,
                        ]);
                        $fechaActual = date('Y-m-d', strtotime($fechaActual . ' +1 day'));
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Clase actualizada correctamente',
                'clase' => $clase,
                'status' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar la clase',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
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

        // Eliminar bloqueos para todas las canchas asociadas
        foreach ($clase->cancha_ids ?? [] as $canchaId) {
            \App\Models\BloqueoDisponibilidadTurno::where('cancha_id', $canchaId)
                ->whereIn('horario_id', $clase->horario_ids ?? [])
                ->where('fecha', '>=', $clase->fecha_inicio)
                ->where('fecha', '<=', $clase->fecha_fin)
                ->delete();
        }

        $clase->delete();

        return response()->json([
            'message' => 'Clase eliminada correctamente',
            'status' => 200
        ], 200);
    }

    public function deleteMany(Request $request)
    {
        $ids = $request->input('ids', []);
        \Log::info('deleteMany - IDs recibidos:', $ids);

        if (!is_array($ids) || empty($ids)) {
            return response()->json([
                'message' => 'Debes enviar un array de IDs',
                'status' => 400
            ], 400);
        }

        $eliminadas = [];
        $noEncontradas = [];

        $clases = Clase::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $id) {
            $clase = $clases->get($id);
            if (!$clase) {
                $noEncontradas[] = $id;
                continue;
            }

            // Eliminar bloqueos para todas las canchas asociadas
            foreach ($clase->cancha_ids ?? [] as $canchaId) {
                \App\Models\BloqueoDisponibilidadTurno::where('cancha_id', $canchaId)
                    ->whereIn('horario_id', $clase->horario_ids ?? [])
                    ->where('fecha', '>=', $clase->fecha_inicio)
                    ->where('fecha', '<=', $clase->fecha_fin)
                    ->delete();
            }

            $clase->delete();
            $eliminadas[] = $id;
        }

        return response()->json([
            'message' => 'Eliminación masiva finalizada',
            'eliminadas' => $eliminadas,
            'no_encontradas' => $noEncontradas,
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
            'cancha_ids' => 'required|array|min:1',
            'cancha_ids.*' => 'exists:canchas,id',
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
        $canchaIds = $request->cancha_ids;

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
                    'cancha_ids' => $canchaIds,
                    'horario_ids' => $horarios->pluck('id')->toArray(),
                    'cupo_maximo' => $request->cupo_maximo,
                    'precio_mensual' => $request->precio_mensual,
                    'activa' => $request->activa,
                    'tipo' => 'fija',
                    'duracion' => $horarios->count(),
                ]);
                $clasesCreadas[] = $clase;

                // Crear bloqueos para todas las canchas
                foreach ($canchaIds as $canchaId) {
                    foreach ($horarios as $horario) {
                        $bloqueo = \App\Models\BloqueoDisponibilidadTurno::create([
                            'fecha' => $item['fecha']->toDateString(),
                            'cancha_id' => $canchaId,
                            'horario_id' => $horario->id,
                        ]);
                        $bloqueosCreados[] = $bloqueo;
                    }
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

    private function verificarRangoCompleto($horarios, $horaInicio, $horaFin)
    {
        if ($horarios->isEmpty()) return false;
        
        $horarios = $horarios->sortBy('hora_inicio');
        
        $inicioEsperado = $horaInicio;
        
        foreach ($horarios as $horario) {
            if ($horario->hora_inicio !== $inicioEsperado) {
                return false;
            }
            $inicioEsperado = $horario->hora_fin;
        }
        
        return $inicioEsperado === $horaFin;
    }

    public function getClasesFijasGrilla($fechaInicio = null, $fechaFin = null)
    {
        \Log::info("getClasesFijasGrilla - fechaInicio: $fechaInicio, fechaFin: $fechaFin");

        $dias = ['lunes','martes','miércoles','jueves','viernes','sábado','domingo'];

        // Genera los bloques horarios de 1 hora (00:00 a 23:00)
        $horas = [];
        for ($h = 0; $h < 24; $h++) {
            $horaInicio = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00:00';
            $horas[] = $horaInicio;
        }

        // Prepara la grilla vacía
        $grilla = [];
        foreach ($horas as $hora) {
            foreach ($dias as $dia) {
                $grilla[$hora][$dia] = [];
            }
        }

        $query = Clase::with('profesor')->where('tipo', 'fija');
        if ($fechaInicio) {
            $query->where('fecha_inicio', '>=', $fechaInicio);
        }
        if ($fechaFin) {
            $query->where('fecha_fin', '<=', $fechaFin);
        }
        $clases = $query->get();

        \Log::info("getClasesFijasGrilla - Clases encontradas: " . $clases->count());

        // Array para controlar duplicados por día/horario/clase
        $clasesUnicas = [];

        foreach ($clases as $clase) {
            // Obtener canchas de la clase
            $canchas = $clase->canchas;
            $canchasStr = $canchas->pluck('nro')->implode(', ');

            foreach ($clase->horarios as $horario) {
                $diaLower = strtolower($horario->dia);

                // Encuentra el bloque horario donde debe ir la clase
                $horaClase = $horario->hora_inicio;
                $h = intval(substr($horaClase, 0, 2));
                $bloque = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00:00';

                if (in_array($bloque, $horas) && in_array($diaLower, $dias)) {
                    // Crear clave única para evitar duplicados: nombre + profesor + canchas + horario + día
                    $claveUnica = $clase->nombre . '|' . ($clase->profesor->nombre ?? '') . '|' . $canchasStr . '|' . $horario->hora_inicio . '|' . $horario->hora_fin . '|' . $diaLower;
                    
                    // Solo agregar si no existe esta combinación
                    if (!isset($clasesUnicas[$bloque][$diaLower][$claveUnica])) {
                        $grilla[$bloque][$diaLower][] = [
                            'id' => $clase->id,
                            'nombre' => $clase->nombre,
                            'profesor' => $clase->profesor->nombre ?? '',
                            'canchas' => $canchasStr,
                            'hora_inicio' => $horario->hora_inicio,
                            'hora_fin' => $horario->hora_fin,
                            'fecha' => $clase->fecha_inicio, // Puedes mostrar la primera fecha o quitar este campo
                        ];
                        
                        // Marcar como agregada
                        $clasesUnicas[$bloque][$diaLower][$claveUnica] = true;
                    }
                }
            }
        }

        \Log::info("getClasesFijasGrilla - Grilla generada: " . json_encode($grilla));

        return $grilla;
    }
}