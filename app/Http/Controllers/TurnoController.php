<?php

namespace App\Http\Controllers;

use App\Http\Resources\TurnoResource;
use App\Models\Horario;
use App\Models\Cancha;
use Illuminate\Http\Request;
use App\Models\Turno;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\BloqueoTemporal;
use Carbon\Carbon;


class TurnoController extends Controller
{
    //

    public function index(Request $request)
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('turnos:show') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');
        
        $validator = Validator::make($request->all(), [
            'fecha' => 'date|nullable',
            'fecha_inicio' => 'date|nullable',
            'fecha_fin' => 'date|nullable|required_with:fecha_turno|after_or_equal:fecha_inicio',
        ]);

        if ($validator->fails()) {
            $data = [
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ];
            return response()->json($data, 400);
        }

        $fechaHoy = now()->startOfDay();
        $query = Turno::query();

        if ($request->has('fecha')) {
            $query->whereDate('fecha_turno', $request->fecha);
        }

        if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
            $query->whereBetween('fecha_turno', [$request->fecha_inicio, $request->fecha_fin]);
        }

        if(!$request->has('fecha') && !$request->has('fecha_inicio') && !$request->has('fecha_fin')){
            $query->whereDate('fecha_turno', '>=', $fechaHoy);
        }

        $turnos = $query->with([
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

    public function getAll(){

        $user = Auth::user();

        abort_unless( $user->tokenCan('turnos:show_all') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

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

        abort_unless($user->tokenCan('turnos:create') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $validated = $request->validate([
            'fecha_turno' => 'required|date',
            'cancha_id' => 'required|exists:canchas,id',
            'horario_id' => 'required|exists:horarios,id',
            'monto_total' => 'required|numeric',
            'monto_seña' => 'required|numeric',
            'estado' => 'required|string',
        ]);

        // Asegúrate de que no haya llamadas duplicadas aquí

        $horario = Horario::find($request->horario_id);
        $cancha = Cancha::find($request->cancha_id);

        if (!$horario || !$cancha) {
            return response()->json([
                'message' => 'Horario o Cancha no encontrados',
                'status' => 404
            ], 404);
        }

        $turnoExistente = Turno::where('fecha_turno', $request->fecha_turno)
                            ->where('horario_id', $horario->id)
                            ->where('cancha_id', $cancha->id)
                            ->first();

        if ($turnoExistente) {
            return response()->json([
                'message' => 'Ya existe un turno para esa cancha en esta fecha y horario',
                'status' => 400
            ], 400);
        }

        // Eliminar bloqueo temporal si existe
        BloqueoTemporal::where('fecha', $request->fecha_turno)
            ->where('horario_id', $request->horario_id)
            ->where('cancha_id', $request->cancha_id)
            ->delete();

        // Crear una nueva reserva
        $turno = Turno::create([
            'fecha_turno' => $request->fecha_turno,
            'fecha_reserva' => now(),
            'horario_id' => $request->horario_id,
            'cancha_id' => $request->cancha_id,
            'usuario_id' => $user->id,
            'monto_total' => $request->monto_total,
            'monto_seña' => $request->monto_seña,
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
        $user = Auth::user();

        abort_unless($user->tokenCan('turnos:createTurnoFijo') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $validator = Validator::make($request->all(), [
            'fecha_turno' => 'required|date',
            'cancha_id' => 'required|exists:canchas,id',
            'horario_id' => 'required|exists:horarios,id',
            'monto_total' => 'required|numeric',
            'monto_seña' => 'required|numeric',
            'estado' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validacion',
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

        DB::beginTransaction();

        try {
            $fecha_turno = Carbon::parse($request->fecha_turno); // Inicializa la fecha_turno

            for ($i = 0; $i < 4; $i++) {
                $fecha_turno_actual = $fecha_turno->copy()->addWeeks($i)->toDateString(); // Copia y agrega semanas

                $turnoExistente = Turno::where('fecha_turno', $fecha_turno_actual)
                                        ->where('horario_id', $horario->id)
                                        ->where('cancha_id', $cancha->id)
                                        ->first();

                if ($turnoExistente) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Ya existe un turno para esa cancha en la fecha ' . $fecha_turno_actual,
                        'status' => 400
                    ], 400);
                }

                // Eliminar bloqueo temporal si existe
                BloqueoTemporal::where('fecha', $fecha_turno_actual)
                    ->where('horario_id', $request->horario_id)
                    ->where('cancha_id', $request->cancha_id)
                    ->delete();

                $turno = Turno::create([
                    'fecha_turno' => $fecha_turno_actual,
                    'fecha_reserva' => now(),
                    'horario_id' => $request->horario_id,
                    'cancha_id' => $request->cancha_id,
                    'usuario_id' => $user->id,
                    'monto_total' => $request->monto_total,
                    'monto_seña' => $request->monto_seña,
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

    public function update(Request $request, $id)
    {

        $user = Auth::user();

        abort_unless( $user->tokenCan('turnos:update') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        // Encontrar la reserva por su ID
        $turno = Turno::find($id);

        // Verificar si la reserva existe
        if (!$turno) {
            $data = [
                'message' => 'No hay turno encontrado',
                'status' => 404
            ];
            return response()->json($data, 404);
        }

        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'fecha_turno' => 'sometimes|date',
            'horario_id' => 'sometimes|required_with:fecha_turno|exists:horarios,id',
            'cancha_id' => 'sometimes|required_with:fecha_turno|exists:canchas,id',
            'monto_total' => 'sometimes|numeric',
            'monto_seña' => 'sometimes|numeric',
            'estado' => 'sometimes',
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

        // Actualizar los campos de la reserva
        if($request->has('fecha_turno') || $request->has('horario_id') || $request->has('cancha_id')){

            if($request->has('fecha_turno')){
                $fecha_comparar = $request->fecha_turno;
            } else {
                $fecha_comparar = $turno->fecha_turno;
            }

            if($request->has('horario_id')){
                $horario_comparar = $request->horario_id;
            } else {
                $horario_comparar = $turno->horario_id;
            }

            if($request->has('cancha_id')){
                $cancha_comparar = $request->cancha_id;
            } else {
                $cancha_comparar = $turno->cancha_id;
            }

            $turnoExistente = Turno::where('fecha_turno', $fecha_comparar)
                                    ->where('horario_id', $horario_comparar)
                                    ->where('cancha_id', $cancha_comparar)
                                    ->where('id', '!=', $id)
                                    ->first();
                                    

            if($turnoExistente) {
                $data = [
                'message' => 'Ya existe un turno para esa cancha en esta fecha y horario',
                'status' => 400
            ];
            return response()->json($data, 400);
           }
            if($request->has('fecha_turno')){
            $turno->fecha_turno = $request->fecha_turno;
            }
            if($request->has('horario_id')){
            $turno->horario_id = $request->horario_id;
            }
            if($request->has('cancha_id')){
            $turno->cancha_id = $request->cancha_id;
            }
        }

        if($request->has('monto_total')){
            $turno->monto_total = $request->monto_total;
        }

        if($request->has('monto_seña')){
            $turno->monto_seña = $request->monto_seña;
        }
        
        if($request->has('estado')){
            $turno->estado = $request->estado;
        }
        // Guardar los cambios en la base de datos
        $turno->save();

        // Respuesta exitosa
        $data = [
            'message' => 'Turno actualizado correctamente',
            'turno' => $turno,
            'status' => 200
        ];

        return response()->json($data, 200);
    }

    public function destroy($id)
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('turnos:destroy') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

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

    public function show($id)
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('turnos:show') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

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


    public function grid(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->tokenCan('turnos:show') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

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

        $horarios = Horario::where('activo', true)->get();
        $canchas = Cancha::where('activa', true)->get();

        $turnos = Turno::whereDate('fecha_turno', $fecha)
                            ->with(['usuario', 'horario', 'cancha'])
                            ->get();

        $grid = [];

        foreach ($horarios as $horario) {
            $hora = Carbon::createFromFormat('H:i:s', $horario->hora_inicio)->format('H');
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

    public function getTurnosByUser()
    {
        $user = Auth::user();

        abort_unless($user->tokenCan('turnos:show') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $turnos = Turno::where('usuario_id', $user->id)
            ->with(['cancha', 'horario'])
            ->get();

        $data = [
            'turnos' => TurnoResource::collection($turnos),
            'status' => 200
        ];

        return response()->json($data, 200);
    }

    public function getProximos(){
        $user = Auth::user();

        abort_unless($user->tokenCan('turnos:show') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

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
}
