<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Turno;
use App\Models\Cancha;
use App\Models\Horario;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use  Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class DisponibilidadController extends Controller
{   
    public function getHorariosNoDisponibles()
    {
        $fecha_inicio = now()->startOfDay();
        $fecha_fin = now()->addDays(30)->endOfDay();
        
        // Obtenemos el conteo de canchas una sola vez
        $canchas_count = Cache::remember('canchas_count', now()->addDay(), function() {
            return Cancha::count();
        });
    
        // Usamos SQL para agrupar y contar directamente en la base de datos
        $turnos = Turno::select(
                'fecha_turno',
                'horario_id',
                DB::raw('COUNT(*) as total_reservas')
            )
            ->whereBetween('fecha_turno', [$fecha_inicio, $fecha_fin])
            ->groupBy('fecha_turno', 'horario_id')
            ->having('total_reservas', '>=', $canchas_count)
            ->with(['horario:id,hora_inicio,hora_fin'])
            ->get();
    
        // Transformamos los resultados directamente al formato deseado
        $result = $turnos->groupBy(function($turno) {
            return $turno->fecha_turno->format('Y-m-d');
        })->map(function($grupoTurnos) {
            return $grupoTurnos->map(function($turno) {
                return $turno->horario->hora_inicio . '-' . $turno->horario->hora_fin;
            })->values()->all();
        })->toArray();
    
        return $result;
    }
    /**
     * Obtiene los horarios disponibles para una fecha específica
     * @param Request $request Contiene la fecha a consultar
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHorariosDisponiblesPorFecha(Request $request)
    {
        // Validación de la fecha recibida
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date_format:Y-m-d',
        ]);

        // Si la validación falla, retorna error
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        // Crear objeto Carbon con la fecha
        $fecha = Carbon::createFromFormat('Y-m-d', $request->fecha);
        // Definir clave única para el caché
        $cacheKey = "horarios_disponibles_{$fecha->format('Y-m-d')}";

        // Retornar datos cacheados o generarlos si no existen
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($fecha) {
            // Cachear el total de canchas por 24 horas
            $canchas_count = Cache::remember('canchas_count', now()->addDay(), function() {
                return Cancha::count();
            });

            // Cachear horarios activos por 12 horas
            $horarios = Cache::remember('horarios_activos', now()->addHours(2), function() {
                return Horario::where('activo', true)
                    ->select(['id', 'hora_inicio', 'hora_fin'])
                    ->get();
            });

            // Obtener conteo de turnos agrupados por horario
            $turnos_count = DB::table('turnos')
                ->select('horario_id', DB::raw('COUNT(*) as total'))
                ->whereDate('fecha_turno', $fecha)
                ->groupBy('horario_id')
                ->pluck('total', 'horario_id')
                ->toArray();

            // Mapear horarios con su disponibilidad
            $result = $horarios->map(function($horario) use ($turnos_count, $canchas_count) {
                return [
                    'id' => $horario->id,
                    'hora_inicio' => $horario->hora_inicio,
                    'hora_fin' => $horario->hora_fin,
                    'disponible' => !isset($turnos_count[$horario->id]) || 
                                    $turnos_count[$horario->id] < $canchas_count
                ];
            });

            return response()->json(['horarios' => $result], 200);
        });
    }

    /**
     * Obtiene las canchas disponibles para una fecha y horario específicos
     * @param Request $request Contiene fecha y horario_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCanchasPorHorarioFecha(Request $request)
{
    // Validación de entrada
    $validator = Validator::make($request->all(), [
        'fecha' => 'required|date_format:Y-m-d',
        'horario_id' => 'required|exists:horarios,id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Error en la validación',
            'errors' => $validator->errors(),
            'status' => 400
        ], 400);
    }

    // Preparar datos
    $fecha = Carbon::createFromFormat('Y-m-d', $request->fecha);
    $horarioId = $request->horario_id;
    
    // Clave única para caché
    $cacheKey = "canchas_disponibles_{$fecha->format('Y-m-d')}_{$horarioId}";

    return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($fecha, $horarioId) {
        // Obtener canchas ocupadas (solo estados bloqueantes)
        $canchas_ocupadas = DB::table('turnos')
            ->where('fecha_turno', $fecha)
            ->where('horario_id', $horarioId)
            ->whereIn('estado', ['Pendiente', 'Señado', 'Pagado']) // Estados en upper camel case
            ->pluck('cancha_id')
            ->toArray();


        // Obtener todas las canchas activas
        $canchas = Cache::remember('todas_canchas', now()->addDay(), function() {
            return Cancha::select(['id', 'nro', 'tipo_cancha'])
                        ->where('activa', true)
                        ->get();
        });

        // Mapear canchas con su disponibilidad
        $result = $canchas->map(function($cancha) use ($canchas_ocupadas) {
            return [
                'id' => $cancha->id,
                'nro' => $cancha->nro,
                'tipo' => $cancha->tipo_cancha,
                'disponibilidad' => !in_array($cancha->id, $canchas_ocupadas)
            ];
        });

        // Devolver respuesta con información de debug
        return response()->json([
            'canchas' => $result,
            'status' => 200
        ], 200);
    });
}




    /**
     * Método privado para limpiar el caché relacionado cuando se modifica un turno
     * @param string $fecha Fecha del turno
     * @param int $horarioId ID del horario
     */
    private function limpiarCacheRelacionado($fecha, $horarioId)
    {
        Cache::forget("horarios_disponibles_{$fecha}");
        Cache::forget("canchas_disponibles_{$fecha}_{$horarioId}");
    }
}