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
use Illuminate\Support\Facades\Redis;
use App\Enums\TurnoEstado;
use App\Services\Interface\AuditoriaServiceInterface; // Importar la interfaz del servicio de auditoría
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use App\Models\Persona;
use App\Models\User;
use App\Models\CuentaCorriente;
use App\Models\Transaccion;
use function Symfony\Component\Clock\now;
use App\Services\Implementation\AuditoriaService;
use App\Notifications\ReservaNotification;
use App\Mail\ReservaConfirmada;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Jobs\TurnosPendientes;
use Illuminate\Support\Facades\Log;
use App\Services\Interface\PaymentServiceInterface;
use App\Models\MetodoPago;
use App\Models\Caja;
use App\Http\Controllers\Checkout\MercadoPagoController;
use App\Models\Configuracion;
use App\Models\Descuento;
use App\Jobs\SendTenantNotification;

class TurnoService implements TurnoServiceInterface
{
    protected $auditoriaService;
    protected $paymentService;

    public function __construct(AuditoriaServiceInterface $auditoriaService, PaymentServiceInterface $paymentService)
    {
        $this->auditoriaService = $auditoriaService;
        $this->paymentService = $paymentService;
    }

    public function getTurnos(Request $request)
    {       
        $validator = Validator::make($request->all(), [
            'fecha' => 'date|nullable',
            'fecha_inicio' => 'date|nullable',
            'fecha_fin' => 'date|nullable|required_with:fecha_inicio|after_or_equal:fecha_inicio',
            'searchType' => 'string|nullable|in:name,email,dni,telefono,id',
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

        if ($request->has('searchType') && $request->has('searchTerm') && $request->searchType === 'id') {
            $query->where('turnos.id', $request->searchTerm);
        }

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
            } else if ($searchType !== 'id') {
                $query->whereHas('persona', function ($q) use ($searchType, $searchTerm) {
                    $q->where($searchType, 'like', "%{$searchTerm}%");
                });
            }
        }

        $turnos = $query->with([
            'persona',
            'cancha',
            'horario',
            'partido.fecha.zona.torneo' // <-- agrega esto
        ])
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
        $monto_seña = $cancha->seña;
        $descuento_aplicado_id = null;

        $descuento = Descuento::where('fecha', $request->fecha_turno)
            ->where('cancha_id', $request->cancha_id)
            ->where('horario_id', $request->horario_id)
            ->first();

        if ($descuento) {
            if ($descuento->tipo === 'porcentaje') {
                $monto_total -= $monto_total * ($descuento->valor / 100);
            } elseif ($descuento->tipo === 'fijo') {
                $monto_total -= $descuento->valor;
            }

            $monto_total = max(0, $monto_total);
            $descuento_aplicado_id = $descuento->id;
        }

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
            'descuento_id' => $descuento_aplicado_id,
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

            //Registrar transacción en cuenta corriente según el estado
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
                    'tipo' => 'saldo',
                    'descripcion' => $descripcion
                ]);
                
                // Actualizar el saldo de la cuenta corriente
                $cuentaCorriente->saldo += $montoTransaccion;
                $cuentaCorriente->save();
            }

            // Obtener la configuración para incluirla en las notificaciones
            $configuracion = Configuracion::first();
            
            // Usar SendTenantNotification en lugar de notify directamente
            $subdominio = $request->header('x-complejo');
            $admins = User::where('rol', 'admin')->get();
            
            foreach ($admins as $admin) {
                SendTenantNotification::dispatch(
                    $subdominio,
                    $admin->id,
                    ReservaNotification::class,
                    [$turno->id, 'admin.pending', $configuracion->id]
                );
            }

            TurnosPendientes::dispatch($turno->id, $configuracion->id, $subdominio)->delay(Carbon::now()->addMinutes(30));

            DB::commit();

            
            // Comentamos esta linea para que no se borre el bloqueo temporal -> lo vamos a borrar cuando se haga el pago
            //Cache::forget($clave);

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
            'turno' => $turno->fresh(['persona', 'horario', 'cancha']),
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
            'deporte_id' => 'required|exists:deportes,id',
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
                    ->where('deporte_id', $request->deporte_id)
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

                $persona = Persona::with('cuentaCorriente')->find($persona_id);

                if (!$persona) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Persona no encontrada',
                        'status' => 404
                    ], 404);
                }

                if ($estado != 'Pagado') {

                    if ($estado == 'Pendiente') {
                        $montoTransaccion = -$monto_total;
                        $descripcion = "Reserva de turno fijo #{$turno->id} (pendiente de pago)";
                        $persona->cuentaCorriente->saldo += $montoTransaccion;
                        $persona->cuentaCorriente->save();
                    } else if ($estado == 'Señado') {
                        $montoTransaccion = -($monto_total - $monto_seña);
                        $descripcion = "Reserva de turno fijo #{$turno->id} (señado)";
                        $persona->cuentaCorriente->saldo += $montoTransaccion;
                        $persona->cuentaCorriente->save();
                    }

                    $transaccion = Transaccion::create([
                        'cuenta_corriente_id' => $persona->cuentaCorriente->id,
                        'turno_id' => $turno->id,
                        'monto' => $montoTransaccion,
                        'tipo' => 'saldo',
                        'descripcion' => $descripcion
                    ]);

                }

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
    
        $turno = Turno::with(['horario', 'cancha'])->find($id);
        $fecha = Carbon::now(); // Usar Carbon en lugar de DatePoint
        $fecha->subDays(7);
    
        if (!$turno) {
            return response()->json([
                'message' => 'No hay turno encontrado',
                'status' => 404
            ], 404);
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
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
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
            if ($request->has('fecha_turno') || $request->has('horario_id') || $request->has('cancha_id')) {
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
                        'message' => 'El Turno está bloqueado temporalmente.',
                        'status' => 409
                    ], 409);
                }
    
                if ($turnoExistente) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'El turno ya está reservado',
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
    
            // Registrar auditoría
            AuditoriaService::registrar(
                'modificar',
                'turnos',
                $id,
                $datosAnteriores,
                $turno->fresh()->toArray()
            );
    
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

                // Registrar auditoría
                $this->auditoriaService->registrar(
                    'modificar',
                    'turnos',
                    $turno->id,
                    $datosAnteriores,
                    $datosNuevos
                );
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
            $datosAnteriores = $turno->toArray();
            
            $turno->delete();

            // Registrar auditoría
            $this->auditoriaService->registrar(
                'eliminar',
                'turnos',
                $turno->id,
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


        $horarios = Horario::where('activo', true)
            ->where('dia', $diaSemana)
            ->where('deporte_id', $deporteId)
            ->orderBy('hora_inicio', 'asc')
            ->get();


        $canchas = Cancha::where('activa', true)
            ->where('deporte_id', $deporteId)
            ->get();

        $turnos = Turno::whereDate('fecha_turno', $fecha)
            ->with([
                'persona',
                'horario',
                'cancha',
                'partido.fecha.zona.torneo',
                'partido.equipoLocal',
                'partido.equipoVisitante'
            ])
            ->where('estado', '!=', 'Cancelado')
            ->whereHas('cancha', function($query) use ($deporteId) {
                $query->where('deporte_id', $deporteId);
            })
            ->get();


        $grid = [];

        foreach ($horarios as $horario) {
            $hora = Carbon::createFromFormat('H:i:s', $horario->hora_inicio)->format('H');
            $hora = (int) $hora;


            $grid[$hora] = [];

            foreach ($canchas as $cancha) {
                if ($cancha->deporte_id == $horario->deporte_id) {
                    $turno = $turnos->first(function ($t) use ($horario, $cancha, $fecha) {
                         $match = $t->horario_id == $horario->id
                        && $t->cancha_id == $cancha->id
                        && $t->fecha_turno->isSameDay($fecha); // Asegurarse de que la fecha también coincida

                    if ($match) {
                        Log::debug('Turno MATCH', [
                            'turno_id' => $t->id,
                            'horario_id' => $t->horario_id,
                            'cancha_id' => $t->cancha_id,
                            'fecha_turno' => $t->fecha_turno
                        ]); // 👀
                    }
                    return $match;
                });


                    $turnoData = null;

                    if ($turno) {
                        if ($turno->tipo === 'torneo' && $turno->partido) {
                            $partido = $turno->partido; 
                            $fechaModel = $partido->getRelationValue('fecha');

                            $fechaNombre = null;
                            $zonaNombre = null;
                            $torneoNombre = null;

                            if ($fechaModel instanceof \App\Models\Fecha) {
                                $fechaNombre = $fechaModel->nombre;
                                $zonaModel = $fechaModel->zona; 
                                if ($zonaModel instanceof \App\Models\Zona) {
                                    $zonaNombre = $zonaModel->nombre;
                                    $torneoModel = $zonaModel->torneo; 
                                    if ($torneoModel instanceof \App\Models\Torneo) {
                                        $torneoNombre = $torneoModel->nombre;
                                    }
                                }
                            } else {
                                if ($partido && $fechaModel !== null) {
                                     Log::warning("TurnoService: For partido ID {$partido->id}, 'fecha' relation was not a Fecha model instance. Type: " . gettype($fechaModel));
                                }
                            }

                            $turnoData = [
                                'id' => $turno->id,
                                'tipo' => $turno->tipo,
                                'estado' => $turno->estado,
                                'partido' => [
                                    'id' => $partido->id,
                                    'fecha' => $fechaNombre,
                                    'zona' => $zonaNombre,
                                    'torneo' => $torneoNombre,
                                    'equipos' => [
                                        'local' => $partido->equipoLocal->nombre ?? null,
                                        'visitante' => $partido->equipoVisitante->nombre ?? null,
                                    ],
                                ],
                            ];
                        } else {
                            $turnoData = [
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
                            ];
                        }
                        
                    }

                    $grid[$hora][$cancha->nro] = [
                        'cancha' => $cancha->nro,
                        'deporte' => $cancha->deporte,
                        'tipo' => $cancha->tipo_cancha,
                        'turno' => $turnoData,
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
        $turno = Turno::with(['horario', 'persona.cuentaCorriente', 'cancha'])->find($id);

        if (!$turno) {
            return response()->json(['message' => 'Turno no encontrado', 'status' => 404], 404);
        }

        if ($turno->estado === TurnoEstado::CANCELADO) {
            return response()->json(['message' => 'El turno ya ha sido cancelado', 'status' => 400], 400);
        }

        $fechaTurnoParte = Carbon::parse($turno->fecha_turno)->format('Y-m-d'); // Extrae solo YYYY-MM-DD
        $fechaHoraTurno = Carbon::parse($fechaTurnoParte . ' ' . $turno->horario->hora_inicio);

        if ($fechaHoraTurno->isPast()) {
            return response()->json(['message' => 'No puedes cancelar un turno que ya ha pasado', 'status' => 400], 400);
        }

        if ($turno->estado === TurnoEstado::PAGADO) {
            return response()->json([
                'message' => 'No se puede cancelar un turno que ya ha sido Pagado. Contacte al administrador.',
                'status' => 400
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'motivo' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Error en la validación', 'errors' => $validator->errors(), 'status' => 400], 400);
        }

        DB::beginTransaction();
        try {
            $persona = $turno->persona;
            $cuentaCorriente = $persona->cuentaCorriente;

            if (!$cuentaCorriente) {
                $cuentaCorriente = CuentaCorriente::firstOrCreate(
                    ['persona_id' => $persona->id],
                    ['saldo' => 0]
                );
            }

            $now = Carbon::now();
            $fechaCreacionTurno = Carbon::parse($turno->created_at);

            if (!$turno->horario || !$turno->horario->hora_inicio) {
                DB::rollBack();
                return response()->json(['message' => 'Error: El turno no tiene un horario o hora de inicio válida.', 'status' => 500], 500);
            }

            $messageDetail = '';
            $estadoOriginalTurno = $turno->estado;

            // Lógica de cancelación
            if ($turno->estado === TurnoEstado::PENDIENTE) {
                if ($fechaCreacionTurno->diffInMinutes($now) <= 30) {
                    // Cancelación < 30 min de reserva, estado Pendiente
                    $cuentaCorriente->saldo += $turno->monto_total;
                    Transaccion::create([
                        'cuenta_corriente_id' => $cuentaCorriente->id,
                        'turno_id' => $turno->id,
                        'monto' => $turno->monto_total,
                        'tipo' => 'saldo',
                        'descripcion' => "Ajuste de saldo por cancelación temprana (pendiente) de turno #{$turno->id}"
                    ]);
                    $messageDetail = ' (cancelación temprana de turno pendiente, sin cargos)';
                } else {
                    // Cancelación > 30 min de reserva, estado Pendiente (Usuario no especificó penalización aquí, por ahora se devuelve todo)
                    $cuentaCorriente->saldo += $turno->monto_total;
                     Transaccion::create([
                        'cuenta_corriente_id' => $cuentaCorriente->id,
                        'turno_id' => $turno->id,
                        'monto' => $turno->monto_total,
                        'tipo' => 'saldo',
                        'descripcion' => "Devolución por cancelación (pendiente >30min) de turno #{$turno->id}"
                    ]);
                    $messageDetail = ' (cancelación de turno pendiente)';
                }
            } elseif ($turno->estado === TurnoEstado::SEÑADO) {
                $horasAnticipacionCancelacion = $now->diffInHours($fechaHoraTurno, false); // false para que sea positivo si fechaHoraTurno es futuro

                if ($horasAnticipacionCancelacion >= 24 && $fechaHoraTurno->isFuture()) {
                    // Cancelación > 24 horas ANTES del turno
                    $transaccionSeñaMP = Transaccion::where('turno_id', $turno->id)
                                                  ->whereNotNull('payment_id')
                                                  ->whereIn('tipo', ['seña', 'turno']) // 'turno' es el que usa PaymentService para la seña MP
                                                  ->orderBy('created_at', 'desc')
                                                  ->first();
                    $montoDeudaRestante = $turno->monto_total - $turno->monto_seña;

                    if ($transaccionSeñaMP && $transaccionSeñaMP->payment_id) {
                        // Devolución de seña por MercadoPago
                        // El usuario implementará handleRefund en PaymentService
                        $this->paymentService->refundPayment($transaccionSeñaMP->payment_id);
                        // AGREGAR UNA TRANSACCION QUE ME INDIQUE EL MONTO SALIENTE DE LA SEÑA PARA QUE ME RESTE EN 
                        // EL BALANCE DE LA CAJA
                        $cajaAbierta = Caja::where('activa', true)->first();
                        $metodoPago = MetodoPago::where('nombre', 'mercadopago')->first();

                        Transaccion::create([
                            'cuenta_corriente_id' => $cuentaCorriente->id,
                            'caja_id' => $cajaAbierta ? $cajaAbierta->id : null,
                            'turno_id' => $turno->id,
                            'monto' => -$turno->monto_seña,
                            'tipo' => 'devolucion',
                            'payment_id' => $transaccionSeñaMP->payment_id,
                            'descripcion' => "Devolución de seña por MercadoPago de turno #{$turno->id}",
                            'metodo_pago_id' => $metodoPago->id
                        ]);


                        // Anular deuda restante en CC si existía
                        if ($montoDeudaRestante > 0) {
                            $cuentaCorriente->saldo += $montoDeudaRestante;
                            Transaccion::create([
                                'cuenta_corriente_id' => $cuentaCorriente->id,
                                'turno_id' => $turno->id,
                                'monto' => $montoDeudaRestante,
                                'tipo' => 'saldo',
                                'descripcion' => "Ajuste de saldo por cancelación (>24h, seña MP devuelta) de turno #{$turno->id}"
                            ]);
                        }
                        $messageDetail = ' (cancelación >24h, seña devuelta por Mercado Pago, deuda restante anulada)';
                    } else {
                        // AGREGAR UNA TRANSACCION QUE ME INDIQUE EL MONTO SALIENTE DE LA SEÑA PARA QUE ME RESTE EN 
                        // EL BALANCE DE LA CAJA
                        $cajaAbierta = Caja::where('activa', true)->first();
                        $transaccionSeña = Transaccion::where('turno_id', $turno->id)->where('tipo', 'turno')->first();

                        Transaccion::create([
                            'cuenta_corriente_id' => $cuentaCorriente->id,
                            'caja_id' => $cajaAbierta ? $cajaAbierta->id : null,
                            'turno_id' => $turno->id,
                            'monto' => -$turno->monto_seña,
                            'tipo' => 'devolucion',
                            'descripcion' => "Devolución de seña manual de turno #{$turno->id}",
                            'metodo_pago_id' => $transaccionSeña->metodo_pago_id
                        ]);

                        

                        // Devolución manual de seña (no MP) y anulación deuda restante
                        $cuentaCorriente->saldo += $montoDeudaRestante; // Seña + Deuda Restante
                        Transaccion::create([
                            'cuenta_corriente_id' => $cuentaCorriente->id,
                            'turno_id' => $turno->id,
                            'monto' => $montoDeudaRestante,
                            'tipo' => 'saldo',
                            'descripcion' => "Ajuste de saldo por cancelación (>24h, seña manual) de turno #{$turno->id}"
                        ]);
                        $messageDetail = ' (cancelación >24h, seña devuelta manualmente, deuda restante anulada)';
                    }
                } else {
                    // Cancelación < 24 horas ANTES del turno (o el mismo día)
                    // Complejo se queda con la seña. Anular deuda restante en CC si existía.
                    $montoDeudaRestante = $turno->monto_total - $turno->monto_seña;
                    if ($montoDeudaRestante > 0) {
                        $cuentaCorriente->saldo += $montoDeudaRestante;
                        Transaccion::create([
                            'cuenta_corriente_id' => $cuentaCorriente->id,
                            'turno_id' => $turno->id,
                            'monto' => $montoDeudaRestante,
                            'tipo' => 'saldo',
                            'descripcion' => "Ajuste de saldo por cancelación (<24h, seña retenida) de turno #{$turno->id}"
                        ]);
                    }
                    $messageDetail = ' (cancelación <24h, seña retenida por el complejo, deuda restante anulada)';
                }
            } else {
                // Estado no manejado explícitamente por la nueva lógica (ej. si se elimina la restricción de PAGADO después)
                DB::rollBack();
                return response()->json(['message' => 'Estado del turno no compatible con las reglas de cancelación actuales.', 'status' => 400], 400);
            }

            $turno->estado = TurnoEstado::CANCELADO;
            $turno->save();
            $cuentaCorriente->save();

            TurnoCancelacion::create([
                'turno_id' => $turno->id,
                'cancelado_por' => $user->id, // O $persona->id si el propio usuario cancela
                'motivo' => $request->motivo ?? 'No especificado',
                'fecha_cancelacion' => $now,
            ]);

            // Notificaciones (adaptar según necesidad)
            // Obtener la configuración para incluirla en las notificaciones
            $configuracion = Configuracion::first();
            $subdominio = request()->header('x-complejo');
            
            if ($persona->usuario) {
                // Usar SendTenantNotification en lugar de notify directamente
                SendTenantNotification::dispatch(
                    $subdominio,
                    $persona->usuario->id,
                    ReservaNotification::class,
                    [$turno->id, 'cancelacion', $configuracion?->id]
                );
            }
            
            // Enviar notificaciones a los administradores
            $admins = User::where('rol', 'admin')->get();
            foreach ($admins as $admin) {
                SendTenantNotification::dispatch(
                    $subdominio,
                    $admin->id,
                    ReservaNotification::class,
                    [$turno->id, 'admin.cancelacion', $configuracion?->id]
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Turno cancelado correctamente' . $messageDetail,
                'status' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error al cancelar el turno #{$id}: " . $e->getMessage() . " en la línea " . $e->getLine() . " del archivo " . $e->getFile());
            return response()->json([
                'message' => 'Error al cancelar el turno.',
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
                    'turno_id' => $turno->id,
                    'monto' => $montoTransaccion,
                    'tipo' => 'saldo',
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

    public function crearTurnoTorneo($partido)
    {
        // Verifica que el partido tenga fecha, horario y cancha asignados
        if (!$partido->fecha || !$partido->horario_id || !$partido->cancha_id) {
            return;
        }

        // Verifica si ya existe un turno de tipo Torneo para ese partido/cancha/horario/fecha
        $existe = \App\Models\Turno::where('fecha_turno', $partido->fecha)
            ->where('horario_id', $partido->horario_id)
            ->where('cancha_id', $partido->cancha_id)
            ->where('tipo', 'torneo')
            ->where('partido_id', $partido->id)
            ->first();

        if ($existe) {
            return; // Ya existe, no crear duplicado
        }

        // Crea el turno de tipo Torneo
        \App\Models\Turno::create([
            'fecha_turno' => $partido->fecha,
            'fecha_reserva' => now(),
            'horario_id' => $partido->horario_id,
            'cancha_id' => $partido->cancha_id,
            'persona_id' => null, // O el organizador si corresponde
            'monto_total' => 0,
            'monto_seña' => 0,
            'estado' => \App\Enums\TurnoEstado::PAGADO, // O el estado que corresponda
            'tipo' => 'torneo',
            'partido_id' => $partido->id
        ]);
    }
}