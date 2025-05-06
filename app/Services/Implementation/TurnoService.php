<?php

namespace App\Services\Implementation;

use App\Models\Turno;
use App\Models\TurnoModificacion;
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
use Illuminate\Support\Facades\Cache;
use App\Enums\TurnoEstado;
use Illuminate\Validation\Rule;
use App\Models\Persona;
use App\Models\User;
use App\Models\CuentaCorriente;
use App\Models\Transaccion;
use function Symfony\Component\Clock\now;
use App\Services\Implementation\AuditoriaService;

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

        $fechaHoy = Carbon::today();
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

            if ($searchType === 'email') {
                $query->whereHas('persona.usuario', function ($q) use ($searchTerm) {
                    $q->where('email', 'like', "%{$searchTerm}%");
                });
            } else {
                $query->whereHas('persona', function ($q) use ($searchType, $searchTerm) {
                    $q->where($searchType, 'like', "%{$searchTerm}%");
                });
            }
        }

        $turnos = $query->with(['persona', 'cancha', 'horario'])
        ->join('horarios', 'turnos.horario_id', '=', 'horarios.id')
        ->orderBy('horarios.hora_inicio', 'asc')
        ->select('turnos.*')
        ->get();

        $data = [
            'turnos' => TurnoResource::collection($turnos),
            'status' => 200 
        ];

        return response()->json($data, 200);
    }

    public function getAllTurnos()
    {
        $turnos = Turno::with([
            'persona',
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
            'persona_id' => 'sometimes|exists:personas,id',
            'estado' => ['required', Rule::enum(TurnoEstado::class)],
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

        if ($turnoExistente) {
            return response()->json([
                'message' => 'El Turno no está disponible.',
                'status' => 400
            ], 400);
        }

        $clave = "bloqueo:{$request->fecha_turno}:{$request->horario_id}:{$request->cancha_id}";
        $bloqueo = Cache::get($clave);

        if ($bloqueo) {
            if($bloqueo['usuario_id'] !== $user->id){
                return response()->json([
                    'message' => 'El Turno ya no está disponible.',
                    'status' => 400
                ], 400);
            }
        }

        if ($request->has('persona_id')) {
            $persona = Persona::find($request->persona_id);

            if (!$persona) {
                return response()->json([
                    'message' => 'Persona no encontrada',
                    'status' => 404
                ], 404);
            }
        } else {
            $persona = $user->persona;
        }

        // Iniciar la transacción de base de datos
        DB::beginTransaction();
        
        try {
        // Crear una nueva reserva
        $turno = Turno::create([
            'fecha_turno' => $request->fecha_turno,
            'fecha_reserva' => now(),
            'horario_id' => $request->horario_id,
            'cancha_id' => $request->cancha_id,
            'persona_id' => $persona->id,
            'monto_total' => $monto_total,
            'monto_seña' => $monto_seña,
            'estado' => $request->estado,
            'tipo' => 'unico'
        ]);

        if (!$turno) {
                DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el turno',
                'status' => 500
            ], 500);
        }

            // Registrar transacción en cuenta corriente según el estado
            if ($request->estado != 'Pagado') {
                // Buscar o crear la cuenta corriente de la persona
                $cuentaCorriente = CuentaCorriente::firstOrCreate(
                    ['persona_id' => $persona->id],
                    ['saldo' => 0]
                );
                
                // Determinar el monto de la transacción según el estado
                if ($request->estado == 'Pendiente') {
                    $montoTransaccion = -$monto_total; // Monto negativo por el total
                    $descripcion = "Reserva de turno #{$turno->id} (pendiente de pago)";
                } else if ($request->estado == 'Señado') {
                    $montoTransaccion = -($monto_total - $monto_seña); // Monto negativo por el total menos la seña
                    $descripcion = "Reserva de turno #{$turno->id} (señado)";
                }
                
                // Crear la transacción
                $transaccion = Transaccion::create([
                    'cuenta_corriente_id' => $cuentaCorriente->id,
                    'turno_id' => $turno->id,
                    'monto' => $montoTransaccion,
                    'tipo' => 'turno',
                    'descripcion' => $descripcion
                ]);
                
                // Actualizar el saldo de la cuenta corriente
                $cuentaCorriente->saldo += $montoTransaccion;
                $cuentaCorriente->save();
            }
            
            DB::commit();
            
            Cache::forget($clave);

            // Registrar auditoría
            AuditoriaService::registrar(
                'crear', 
                'turnos', 
                $turno->id, 
                null, 
                $turno->toArray()
            );

        return response()->json([
            'message' => 'Turno creado correctamente',
            'turno' => $turno,
            'status' => 201
        ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el turno: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }

        
    }

    public function storeTurnoFijo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'persona_id' => 'required|exists:personas,id',
            'fecha_turno' => 'required|date',
            'horario_id' => 'required|exists:horarios,id',
            'estado' => ['required', Rule::enum(TurnoEstado::class)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $horario = Horario::find($request->horario_id);

        if (!$horario) {
            return response()->json([
                'message' => 'Horario no encontrado',
                'status' => 404
            ], 404);
        }

        DB::beginTransaction();

        try {
            $fecha_turno = Carbon::parse($request->fecha_turno);
            $persona_id = $request->persona_id;
            $estado = $request->estado;

            for ($i = 0; $i < 4; $i++) {
                $fecha_turno_actual = $fecha_turno->copy()->addWeeks($i)->toDateString();

                // Obtener canchas disponibles para la fecha y horario
                $canchasDisponibles = Cancha::where('activa', true)
                    ->whereDoesntHave('turnos', function ($query) use ($fecha_turno_actual, $horario) {
                        $query->where('fecha_turno', $fecha_turno_actual)
                    ->where('horario_id', $horario->id)
                              ->where('estado', '!=', 'Cancelado');
                    })
                    ->whereDoesntHave('bloqueosTemporales', function ($query) use ($fecha_turno_actual, $horario) {
                        $query->where('fecha', $fecha_turno_actual)
                              ->where('horario_id', $horario->id);
                    })
                    ->first();

                if (!$canchasDisponibles) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'No hay canchas disponibles para la fecha ' . $fecha_turno_actual,
                        'status' => 400
                    ], 400);
                }

                $monto_total = $canchasDisponibles->precio_por_hora;
                $monto_seña = $canchasDisponibles->seña;

                if (is_null($monto_seña)) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'El monto de la seña no puede ser nulo',
                        'status' => 400
                    ], 400);
                }

                $turno = Turno::create([
                    'fecha_turno' => $fecha_turno_actual,
                    'fecha_reserva' => now(),
                    'horario_id' => $horario->id,
                    'cancha_id' => $canchasDisponibles->id,
                    'persona_id' => $persona_id,
                    'monto_total' => $monto_total,
                    'monto_seña' => $monto_seña,
                    'estado' => $estado,
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

        if($turno->fecha_turno < Carbon::now()->subDays(3)->startOfDay()) {
            return response()->json([
                'message' => 'No puedes modificar un turno de más de 3 días atrás',
                'status' => 400
            ], 400);
        }

        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'fecha_turno' => 'sometimes|date',
            'horario_id' => 'sometimes|required_with:fecha_turno|exists:horarios,id',
            'cancha_id' => 'sometimes|required_with:fecha_turno|exists:canchas,id',
            'estado' => ['sometimes', Rule::enum(TurnoEstado::class)],
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

                $clave = "bloqueo:{$fecha_comparar}:{$horario_comparar}:{$cancha_comparar}";
                $bloqueo = Cache::has($clave);

                if ($bloqueo) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'El Turno esta bloqueado temporalmente.',
                        'status' => 409
                    ], 409);
                }
    
                if($turnoExistente) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'El turno ya esta reservado',
                        'status' => 409
                    ], 409);
                }

                if ($request->has('cancha_id') && $request->cancha_id != $turno->cancha_id) {
                    $nuevaCancha = Cancha::findOrFail($request->cancha_id);
                    $precioDistinto = $nuevaCancha->precio_por_hora != $turno->monto_total;
    
                    if ($precioDistinto) {
                        if ($turno->estado === TurnoEstado::PAGADO) {
                            DB::rollBack();
                            return response()->json([
                                'message' => 'No se puede cambiar a una cancha con diferente precio en un turno pagado',
                                'status' => 400
                            ], 400);
                        }
    
                        if ($turno->estado === TurnoEstado::PENDIENTE) {
                            $turno->monto_total = $nuevaCancha->precio_por_hora;
                            $turno->monto_seña = $nuevaCancha->seña;
                        } elseif ($turno->estado === 'Señado') {
                            $turno->monto_total = $nuevaCancha->precio_por_hora;
                        }
                    }
                }

            }

            // Obtener datos anteriores para auditoría
            $datosAnteriores = $turno->toArray();

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

            $accion = 'modificar';
            // Comprueba si el estado anterior no era cancelado y el nuevo estado es cancelado
            if ($datosAnteriores['estado'] !== TurnoEstado::CANCELADO && $turno->estado === TurnoEstado::CANCELADO) {
                $accion = 'cancelar';
            }

            AuditoriaService::registrar(
                $accion, 
                'turnos', 
                $id, 
                $datosAnteriores, 
                $turno->fresh()->toArray()
            );

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
            $datosAnteriores = $turno->toArray();
            
            $turno->delete();
            
            // Registrar auditoría
            AuditoriaService::registrar(
                'eliminar', 
                'turnos', 
                $id, 
                $datosAnteriores, 
                null
            );

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
            $turno = Turno::with(['cancha.deporte', 'horario'])->findOrFail($id);

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
            'deporte_id' => 'required|exists:deportes,id'
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
        $deporteId = $request->deporte_id;

        // Consulta base de horarios - filtrar siempre por deporte
        $horariosQuery = Horario::where('activo', true)
                                ->where('dia', $diaSemana)
                                ->where('deporte_id', $deporteId)
                                ->orderBy('hora_inicio', 'asc');
        
        $horarios = $horariosQuery->get();

        // Consulta base de canchas - filtrar siempre por deporte
        $canchasQuery = Cancha::where('activa', true)
                              ->where('deporte_id', $deporteId);
        
        $canchas = $canchasQuery->get();

        // Consulta base de turnos - filtrar siempre por deporte a través de las canchas
        $turnosQuery = Turno::whereDate('fecha_turno', $fecha)
                            ->with(['persona', 'horario', 'cancha'])
                            ->where('estado', '!=', 'Cancelado')
                            ->whereHas('cancha', function($query) use ($deporteId) {
                                $query->where('deporte_id', $deporteId);
                            });
        
        $turnos = $turnosQuery->get();

        $grid = [];

        foreach ($horarios as $horario) {
            $hora = Carbon::createFromFormat('H:i:s', $horario->hora_inicio)->format('H');
            $hora = (int) $hora;
            $grid[$hora] = [];

            foreach ($canchas as $cancha) {
                // Solo incluir canchas que coincidan con el deporte del horario
                if ($cancha->deporte_id == $horario->deporte_id) {
                    $turno = $turnos->first(function ($t) use ($horario, $cancha) {
                        return $t->horario_id == $horario->id && $t->cancha_id == $cancha->id;
                    });

                    $grid[$hora][$cancha->nro] = [
                        'cancha' => $cancha->nro,
                        'deporte' => $cancha->deporte,
                        'tipo' => $cancha->tipo_cancha,
                        'turno' => $turno ? [
                            'id' => $turno->id,
                            'usuario' => [
                                'usuario_id' => $turno->persona->usuario?->id ?? null,
                                'nombre' => $turno->persona->name,
                                'dni' => $turno->persona->dni,
                                'telefono' => $turno->persona->telefono,
                            ],
                            'monto_total' => $turno->monto_total,
                            'monto_seña' => $turno->monto_seña,
                            'estado' => $turno->estado,
                            'tipo' => $turno->tipo,
                        ] : null,
                    ];
                }
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
        $user = User::where('id', $userId)->first();
        $personaId = $user->persona->id;
        $turnos = Turno::where('persona_id', $personaId)
        ->with(['cancha', 'horario'])
        ->get();

        if ($turnos->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron turnos para este usuario',
                'status' => 404
            ], 404);
        }

        $fechaHoy = Carbon::today();

        // Calcular la diferencia de días respecto a la fecha de hoy
        $turnos = $turnos->map(function ($turno) use ($fechaHoy) {
            $turno->diferencia_dias = $fechaHoy->diffInDays($turno->fecha_turno, true);
            return $turno;
        });

        // Ordenar los turnos por la diferencia de días
        $turnos = $turnos->sortBy('diferencia_dias')->values();

        return response()->json([
            'turnos' => TurnoResource::collection($turnos),
            'status' => 200
        ], 200);
    }

    public function getProximosTurnos()
    {
        $user = Auth::user();

        $fechaHoy = Carbon::today();

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

    public function cancelTurno($id, Request $request)
    {
        $user = Auth::user();

        $turno = Turno::with(['horario', 'persona', 'persona.cuentaCorriente'])->find($id);

        if (!$turno) {
            return response()->json([
                'message' => 'Turno no encontrado',
                'status' => 404
            ], 404);
        }

        if ($turno->estado === TurnoEstado::CANCELADO) {
            return response()->json([
                'message' => 'El turno ya ha sido cancelado',
                'status' => 400
            ], 400);
        }
        
        if ($turno->fecha_turno < Carbon::now()->startOfDay()) {
            return response()->json([
                'message' => 'No puedes cancelar un turno que ya ha pasado',
                'status' => 400
            ], 400);
        }
        
        // Nueva validación: impedir cancelación de turnos señados
        if ($turno->estado === TurnoEstado::SEÑADO || $turno->estado === TurnoEstado::PAGADO || $turno->estado === TurnoEstado::CANCELADO) {
            return response()->json([
                'message' => 'No se puede cancelar un turno que ya ha sido ' . $turno->estado->value, 
                'status' => 400
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'motivo' => 'sometimes|string|max:255'
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
            // Calcular el tiempo transcurrido desde la creación del turno
            $fechaCreacion = Carbon::parse($turno->created_at);
            $tiempoTranscurrido = $fechaCreacion->diffInMinutes(now());
            
            // Determinar si aplica cargo por cancelación (pasados 30 minutos)
            $aplicaCargo = $tiempoTranscurrido > 30;
            
            // Calcular el monto a devolver
            $montoTotal = $turno->monto_total;
            $montoDevolver = $aplicaCargo ? $montoTotal * 0.9 : $montoTotal; // 90% o 100%
            
            // Si el turno no estaba en estado Pagado, determinar el monto que se había cobrado
            $montoCobrado = 0;
            if ($turno->estado === TurnoEstado::PENDIENTE) {
                $montoCobrado = $montoTotal;
            } elseif ($turno->estado === TurnoEstado::SEÑADO) {
                $montoCobrado = $montoTotal - $turno->monto_seña;
            }
            
            // Solo crear transacción si había un monto cobrado (pendiente o señado)
            if ($montoCobrado > 0) {
                // Buscar o crear la cuenta corriente de la persona
                $cuentaCorriente = CuentaCorriente::firstOrCreate(
                    ['persona_id' => $turno->persona_id],
                    ['saldo' => 0]
                );
                
                // Calcular la devolución proporcional
                $montoRealDevolver = $montoCobrado > $montoDevolver ? $montoDevolver : $montoCobrado;
                
                // Crear la transacción de devolución
                $descripcion = $aplicaCargo 
                    ? "Devolución por cancelación de turno #{$turno->id} (con cargo del 10%)" 
                    : "Devolución por cancelación de turno #{$turno->id}";
                    
                Transaccion::create([
                    'cuenta_corriente_id' => $cuentaCorriente->id,
                    'persona_id' => $turno->persona_id,
                    'monto' => $montoRealDevolver, // Monto positivo por ser devolución
                    'tipo' => 'devolucion',
                    'descripcion' => $descripcion
                ]);
                
                // Actualizar el saldo de la cuenta corriente
                $cuentaCorriente->saldo += $montoRealDevolver;
                $cuentaCorriente->save();
            }

            // Cambiar el estado del turno a cancelado
            $turno->estado = TurnoEstado::CANCELADO;
            $turno->save();

            // Registro de auditoría para la cancelación
            TurnoCancelacion::create([
                'turno_id' => $turno->id,
                'cancelado_por' => $user->id,
                'motivo' => $request->motivo ?? 'No especificado',
                'fecha_cancelacion' => now(),
            ]);

            // Registrar auditoría
            AuditoriaService::registrar(
                'cancelar', 
                'turnos', 
                $turno->id, 
                $turno->toArray(), 
                null
            );

            DB::commit();

            return response()->json([
                'message' => 'Turno cancelado correctamente' . 
                             ($aplicaCargo ? ' (con cargo del 10%)' : ' (sin cargo)'),
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

    public function storeTurnoPersona(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'fecha_turno' => 'required|date',
            'cancha_id' => 'required|exists:canchas,id',
            'horario_id' => 'required|exists:horarios,id',
            'estado' => ['required', Rule::enum(TurnoEstado::class)],
            'persona_id' => 'required|exists:personas,id',
            'tipo' => 'required|in:unico,fijo'
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

        if ($turnoExistente) {
            return response()->json([
                'message' => 'El Turno no está disponible.',
                'status' => 400
            ], 400);
        }

        // Iniciar la transacción de base de datos
        DB::beginTransaction();
        
        try {
        // Crear una nueva reserva
        $turno = Turno::create([
            'fecha_turno' => $request->fecha_turno,
            'fecha_reserva' => now(),
            'horario_id' => $request->horario_id,
            'cancha_id' => $request->cancha_id,
            'persona_id' => $request->persona_id,
            'monto_total' => $monto_total,
            'monto_seña' => $monto_seña,
            'estado' => $request->estado,
            'tipo' => $request->tipo
        ]);

        if (!$turno) {
                DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el turno',
                'status' => 500
            ], 500);
        }

            // Registrar transacción en cuenta corriente según el estado
            if ($request->estado != 'Pagado') {
                $persona = Persona::find($request->persona_id);
                
                // Buscar o crear la cuenta corriente de la persona
                $cuentaCorriente = CuentaCorriente::firstOrCreate(
                    ['persona_id' => $persona->id],
                    ['saldo' => 0]
                );
                
                // Determinar el monto de la transacción según el estado
                if ($request->estado == 'Pendiente') {
                    $montoTransaccion = -$monto_total; // Monto negativo por el total
                    $descripcion = "Reserva de turno #{$turno->id} (pendiente de pago)";
                } else if ($request->estado == 'Señado') {
                    $montoTransaccion = -($monto_total - $monto_seña); // Monto negativo por el total menos la seña
                    $descripcion = "Reserva de turno #{$turno->id} (señado)";
                }
                
                // Crear la transacción
                $transaccion = Transaccion::create([
                    'cuenta_corriente_id' => $cuentaCorriente->id,
                    'monto' => $montoTransaccion,
                    'tipo' => 'turno',
                    'descripcion' => $descripcion
                ]);
                
                // Actualizar el saldo de la cuenta corriente
                $cuentaCorriente->saldo += $montoTransaccion;
                $cuentaCorriente->save();
            }
            
            DB::commit();

        return response()->json([
            'message' => 'Turno creado correctamente',
            'turno' => $turno,
            'status' => 201
        ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el turno: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }
}