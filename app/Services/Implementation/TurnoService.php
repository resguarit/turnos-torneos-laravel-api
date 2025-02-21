<?php

namespace App\Services\Implementation;

use App\Models\Turno;
use App\Models\TurnoModificacion;
use App\Models\BloqueoTemporal;
use App\Models\Cancha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; 
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\TurnoResource;
use App\Models\Horario;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\TurnoCancelacion;
use App\Services\Interface\TurnoServiceInterface;


class TurnoService implements TurnoServiceInterface
{
    public function getTurnos(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'date|nullable',
            'fecha_inicio' => 'date|nullable',
            'fecha_fin' => 'date|nullable|required_with:fecha_inicio|after_or_equal:fecha_inicio',
            'searchType' => 'string|nullable|in:name,email,dni,telefono',
            'searchTerm' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $fechaHoy = now()->startOfDay();
        $query = Turno::query();

        if ($request->has('fecha')) {
            $query->whereDate('fecha_turno', $request->fecha);
        }

        if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
            $query->whereBetween('fecha_turno', [$request->fecha_inicio, $request->fecha_fin]);
        }

        if (!$request->has('fecha') && !$request->has('fecha_inicio') && !$request->has('fecha_fin') && !$request->has('searchTerm')) {
            $query->whereDate('fecha_turno', '>=', $fechaHoy);
        }

        // Filtrar por searchType y searchTerm si se proporcionan
        if ($request->has('searchType') && $request->has('searchTerm')) {
            $searchType = $request->searchType;
            $searchTerm = $request->searchTerm;

            $query->whereHas('usuario', function ($q) use ($searchType, $searchTerm) {
                $q->where($searchType, 'like', "%{$searchTerm}%");
            });
        }

        $turnos = $query->with(['usuario', 'cancha', 'horario'])
        ->join('horarios', 'turnos.horario_id', '=', 'horarios.id')
        ->orderBy('horarios.hora_inicio', 'asc')
        ->select('turnos.*')
        ->get();

        $data = [
            'turnos' => TurnoResource::collection($turnos),
            'status' => 200,
            'prueba' => 'asd'   
        ];

        return response()->json($data, 200);
    }

    public function getAllTurnos()
    {
        $turnos = Turno::with([
            'usuario',
            'cancha',
            'horario',
        ])->get();

        $data = [
            'turnos' => TurnoResource::collection($turnos),
            'status' => 200
        ];

        return response()->json($data, 200);
    }

    public function storeTurnoUnico(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'fecha_turno' => 'required|date',
            'cancha_id' => 'required|exists:canchas,id',
            'horario_id' => 'required|exists:horarios,id',
            'estado' => 'required|in:Pendiente,Señado,Pagado,Cancelado',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $horario = Horario::find($request->horario_id);
        $cancha = Cancha::find($request->cancha_id);

        if (!$horario || !$cancha) {
            return response()->json([
                'message' => 'Horario o Cancha no encontrados',
                'status' => 404
            ], 404);
        }

        $monto_total = $cancha->precio_por_hora;        
        $monto_seña = $cancha->seña; // Ensure this is not null

        if (is_null($monto_seña)) {
            return response()->json([
                'message' => 'El monto de la seña no puede ser nulo',
                'status' => 400
            ], 400);
        }

        $turnoExistente = Turno::where('fecha_turno', $request->fecha_turno)
            ->where('horario_id', $horario->id)
            ->where('cancha_id', $cancha->id)
            ->where('estado', '!=', 'Cancelado') 
            ->first();

        $ya_bloqueado = BloqueoTemporal::where('fecha', $request->fecha_turno)
                                        ->where('horario_id', $request->horario_id)
                                        ->where('cancha_id', $request->cancha_id)
                                        ->where('usuario_id','!=', $user->id)
                                        ->exists();

        if ($turnoExistente || $ya_bloqueado) {
            return response()->json(['message' => 'El Turno ya no está disponible.'], 400);
        }

        // Crear una nueva reserva
        $turno = Turno::create([
            'fecha_turno' => $request->fecha_turno,
            'fecha_reserva' => now(),
            'horario_id' => $request->horario_id,
            'cancha_id' => $request->cancha_id,
            'usuario_id' => $user->id,
            'monto_total' => $monto_total,
            'monto_seña' => $monto_seña,
            'estado' => $request->estado,
            'tipo' => 'unico'
        ]);

        if (!$turno) {
            return response()->json([
                'message' => 'Error al crear el turno',
                'status' => 500
            ], 500);
        }

        return response()->json([
            'message' => 'Turno creado correctamente',
            'turno' => $turno,
            'status' => 201
        ], 201);
    }

    public function storeTurnoFijo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'usuario_id' => 'required|exists:users,id',
            'fecha_turno' => 'required|date',
            'cancha_id' => 'required|exists:canchas,id',
            'horario_id' => 'required|exists:horarios,id',
            'estado' => 'required|in:Pendiente,Señado,Pagado,Cancelado',
        ]);

        $horario = Horario::find($request->horario_id);
        $cancha = Cancha::find($request->cancha_id);

        if (!$horario || !$cancha) {
            return response()->json([
                'message' => 'Horario o Cancha no encontrados',
                'status' => 404
            ], 404);
        }

        $monto_total = $cancha->precio_por_hora;
        $monto_seña = $cancha->seña; // Ensure this is not null

        if (is_null($monto_seña)) {
            return response()->json([
                'message' => 'El monto de la seña no puede ser nulo',
                'status' => 400
            ], 400);
        }

        DB::beginTransaction();

        try {
            $fecha_turno = Carbon::parse($request->fecha_turno);

            for ($i = 0; $i < 4; $i++) {
                $fecha_turno_actual = $fecha_turno->copy()->addWeeks($i)->toDateString();

                $turnoExistente = Turno::where('fecha_turno', $fecha_turno_actual)
                    ->where('horario_id', $horario->id)
                    ->where('cancha_id', $cancha->id)
                    ->where('estado', '!=', 'Cancelado')
                    ->first();

                if ($turnoExistente) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Ya existe un turno para esa cancha en la fecha ' . $fecha_turno_actual,
                        'status' => 400
                    ], 400);
                }

                // Delete any existing temporary block
                BloqueoTemporal::where('fecha', $fecha_turno_actual)
                    ->where('horario_id', $request->horario_id)
                    ->where('cancha_id', $request->cancha_id)
                    ->delete();

                $turno = Turno::create([
                    'fecha_turno' => $fecha_turno_actual,
                    'fecha_reserva' => now(),
                    'horario_id' => $request->horario_id,
                    'cancha_id' => $request->cancha_id,
                    'usuario_id' => $request->user_id,
                    'monto_total' => $monto_total,
                    'monto_seña' => $monto_seña,
                    'estado' => $request->estado,
                    'tipo' => 'fijo'
                ]);

                if (!$turno) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Error al crear el turno',
                        'status' => 500
                    ], 500);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Turnos creados correctamente',
                'status' => 201
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear los turnos',
                'status' => 500,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateTurno(Request $request, $id)
    {
        $user = Auth::user();

        $turno = Turno::with(['horario','cancha'])->find($id);

        if (!$turno) {
            $data = [
                'message' => 'No hay turno encontrado',
                'status' => 404
            ];
            return response()->json($data, 404);
        }

        if($turno->fecha_turno < now()->startOfDay()){
            return response()->json([
                'message' => 'No puedes modificar un turno que ya ha pasado',
                'status' => 400
            ], 400);
        }

        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'fecha_turno' => 'sometimes|date',
            'horario_id' => 'sometimes|required_with:fecha_turno|exists:horarios,id',
            'cancha_id' => 'sometimes|required_with:fecha_turno|exists:canchas,id',
            'estado' => 'sometimes|in:Pendiente,Señado,Pagado,Cancelado',
            'motivo' => 'nullable|string|max:255'
        ]); 

        // Manejar errores de validación
        if ($validator->fails()) {
            $data = [
                'message' => 'Error en la validacion',
                'errors' => $validator->errors(),
                'status' => 400
            ];
            return response()->json($data, 400);
        }

        $datosAnteriores = [
            'fecha_turno' => $turno->fecha_turno->format('Y-m-d'),
            'horario_id' => $turno->horario_id,
            'cancha_id' => $turno->cancha_id,
            'monto_total' => $turno->monto_total,
            'monto_sena' => $turno->monto_seña,
            'estado' => $turno->estado
        ];

        DB::beginTransaction();

        try {
            if($request->has('fecha_turno') || $request->has('horario_id') || $request->has('cancha_id')) {
                $fecha_comparar = $request->fecha_turno ?? $turno->fecha_turno;
                $horario_comparar = $request->horario_id ?? $turno->horario_id;
                $cancha_comparar = $request->cancha_id ?? $turno->cancha_id;
    
                $turnoExistente = Turno::where('fecha_turno', $fecha_comparar)
                    ->where('horario_id', $horario_comparar)
                    ->where('cancha_id', $cancha_comparar)
                    ->where('id', '!=', $id)
                    ->where('estado', '!=', 'Cancelado')
                    ->first();

                $bloqueoExistente = BloqueoTemporal::where('fecha', $fecha_comparar)
                    ->where('horario_id', $horario_comparar)
                    ->where('cancha_id', $cancha_comparar)
                    ->where('usuario_id', '!=', $turno->usuario_id)
                    ->first();
    
                if($turnoExistente || $bloqueoExistente) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Ya existe un turno o bloqueo para esa cancha en esta fecha y horario',
                        'status' => 409
                    ], 409);
                }

                if ($request->has('cancha_id') && $request->cancha_id != $turno->cancha_id) {
                    $nuevaCancha = Cancha::findOrFail($request->cancha_id);
                    $precioDistinto = $nuevaCancha->precio_por_hora != $turno->monto_total;
    
                    if ($precioDistinto) {
                        if ($turno->estado === 'Pagado') {
                            DB::rollBack();
                            return response()->json([
                                'message' => 'No se puede cambiar a una cancha con diferente precio en un turno pagado',
                                'status' => 400
                            ], 400);
                        }
    
                        if ($turno->estado === 'Pendiente') {
                            $turno->monto_total = $nuevaCancha->precio_por_hora;
                            $turno->monto_seña = $nuevaCancha->seña;
                        } elseif ($turno->estado === 'Señado') {
                            $turno->monto_total = $nuevaCancha->precio_por_hora;
                        }
                    }
                }

            }

            $turno->fill($request->only([
                'fecha_turno',
                'horario_id',
                'cancha_id',
                'estado'
            ]));

            $turno->save();

            $datosNuevos = [
                'fecha_turno' => $turno->fecha_turno->format('Y-m-d'),
                'horario_id' => $turno->horario_id,
                'cancha_id' => $turno->cancha_id,
                'monto_total' => $turno->monto_total,
                'monto_sena' => $turno->monto_seña,
                'estado' => $turno->estado
            ];

            if ($datosAnteriores != $datosNuevos) {
                TurnoModificacion::create([
                    'turno_id' => $turno->id,
                    'modificado_por' => $user->id,
                    'datos_anteriores' => json_encode($datosAnteriores, JSON_PRETTY_PRINT),
                    'datos_nuevos' => json_encode($datosNuevos, JSON_PRETTY_PRINT),
                    'motivo' => json_encode($request->motivo ?? "No especificado"),
                    'fecha_modificacion' => now()
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Turno actualizado correctamente',
                'turno' => new TurnoResource($turno->fresh(['horario', 'cancha'])),
                'status' => 200
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al actualizar el turno',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function deleteTurno($id)
    {
        try {
            $turno = Turno::findOrFail($id);
            $turno->delete();

            $data = [
                'message' => 'Turno eliminado correctamente',
                'status' => 200
            ];

            return response()->json($data, 200);

        } catch (ModelNotFoundException $e) {
            $data = [
                'message' => 'Turno no encontrado',
                'status' => 404
            ];
            return response()->json($data, 404);
        }
    }

    public function restoreTurno($id)
    {
        try {
            $turno = Turno::onlyTrashed()->findOrFail($id);
            $turno->restore();

            return response()->json([
                'message' => 'Turno restaurado correctamente',
                'status' => 200
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Turno no encontrado',
                'status' => 404
            ], 404);
        }
    }

    public function showTurno($id)
    {
        try {
            $turno = Turno::with(['cancha', 'horario'])->findOrFail($id);

            $data = [
            'turno' => new TurnoResource($turno),
            'cancha_id' => $turno->cancha->id,
            'horario_id' => $turno->horario->id,
            'status' => 200
            ];

            return response()->json($data, 200);

        } catch (ModelNotFoundException $e) {
            $data = [
                'message' => 'Reserva no encontrada',
                'status' => 404
            ];
            return response()->json($data, 404);
        }
    }

    public function gridTurnos(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $fecha = Carbon::createFromFormat('Y-m-d', $request->fecha);
        $diaSemana = $this->getNombreDiaSemana($fecha->dayOfWeek); // Convertir el día de la semana a su nombre

        $horarios = Horario::where('activo', true)
                            ->where('dia', $diaSemana) // Filtrar por día de la semana
                            ->orderBy('hora_inicio', 'asc')
                            ->get();

        $canchas = Cancha::where('activa', true)->get();

        $turnos = Turno::whereDate('fecha_turno', $fecha)
                            ->with(['usuario', 'horario', 'cancha'])
                            ->get();

        $grid = [];

        foreach ($horarios as $horario) {
            $hora = Carbon::createFromFormat('H:i:s', $horario->hora_inicio)->format('H');
            $hora = (int) $hora;
            $grid[$hora] = [];

            foreach ($canchas as $cancha) {
                $turno = $turnos->first(function ($t) use ($horario, $cancha) {
                    return $t->horario->id === $horario->id && $t->cancha->id === $cancha->id;
                });

                $grid[$hora][$cancha->nro] = [
                    'cancha' => $cancha->nro,
                    'tipo' => $cancha->tipo_cancha,
                    'turno' => $turno ? [
                        'id' => $turno->id,
                        'usuario' => [
                            'usuario_id' => $turno->usuario->id,
                            'nombre' => $turno->usuario->name,
                            'dni' => $turno->usuario->dni,
                            'telefono' => $turno->usuario->telefono,
                        ],
                        'monto_total' => $turno->monto_total,
                        'monto_seña' => $turno->monto_seña,
                        'estado' => $turno->estado,
                        'tipo' => $turno->tipo,
                    ] : null,
                ];
            }
        }

        return response()->json([
            'grid' => $grid,
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

    public function getTurnosByUser($userId)
    {
        $turnos = Turno::where('usuario_id', $userId)
        ->with(['cancha', 'horario'])
        ->get();

        if ($turnos->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron turnos para este usuario',
                'status' => 404
            ], 200);
        }

        return response()->json([
            'turnos' => TurnoResource::collection($turnos),
            'status' => 200
        ], 200);
    }

    public function getProximosTurnos()
    {
        $user = Auth::user();

        $fechaHoy = now()->startOfDay();

        $turnos = Turno::where('usuario_id', $user->id)
            ->whereDate('fecha_turno', '>=', $fechaHoy)
            ->with(['cancha', 'horario'])
            ->get();

        $data = [
            'turnos' => TurnoResource::collection($turnos),
            'status' => 200
        ];

        return response()->json($data, 200);
    }

    public function cancelTurno($id)
    {
        $user = Auth::user();

        $turno = Turno::with(['horario','usuario'])->find($id);

        if(!$turno){
            return response()->json([
                'message' => 'Turno no encontrado',
                'status' => 404
            ], 404);
        }

        if($turno->estado === 'Cancelado'){
            return response()->json([
                'message' => 'El turno ya ha sido cancelado',
                'status' => 400
            ], 400);
        }

        if($turno->fecha_turno < now()->startOfDay()){
            return response()->json([
                'message' => 'No puedes cancelar un turno que ya ha pasado',
                'status' => 400
            ], 400);
        }

        DB::beginTransaction();
        try {
            $turno->estado = 'Cancelado';
            $turno->save();

            // Registro de auditoria para la cancelacion
            TurnoCancelacion::create([
                'turno_id' => $turno->id,
                'cancelado_por' => $user->id,
                'motivo' => $request->motivo ?? 'No especificado',
                'fecha_cancelacion' => now()
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Turno cancelado correctamente',
                'status' => 200
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al cancelar el turno',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }
}