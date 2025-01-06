<?php

namespace App\Http\Controllers;

use App\Http\Resources\TurnoResource;
use App\Models\HorarioCancha;
use Illuminate\Http\Request;
use App\Models\Turno;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


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
            'fecha_fin' => 'date|nullable|after_or_equal:fecha_inicio',
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
            'horarioCancha.horario',
            'horarioCancha.cancha',
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
            'horarioCancha.horario',
            'horarioCancha.cancha',
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

        abort_unless( $user->tokenCan('turnos:create') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        if (!$request->user()->tokenCan('turnos:create')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'fecha_turno' => 'required|date',
            'canchaID' => 'required|exists:canchas,id',
            'horarioID' => 'required|exists:horarios,id',
            // 'usuarioID' => 'required|exists:users,id',
            'monto_total' => 'required',
            'monto_seña' => 'required',
            'estado' => 'required',
        ]);

        if ($validator->fails()) {
            $data = [
                'message' => 'Error en la validacion',
                'errors' => $validator->errors(),
                'status' => 400
            ];
            return response()->json($data, 400);
        }

        $horarioCancha = HorarioCancha::where('cancha_id', $request->canchaID)
                                      ->where('horario_id', $request->horarioID)
                                      ->first();

        if (!$horarioCancha) {
            $data = [
                'message' => 'HorarioCancha no encontrado',
                'status' => 404
            ];
            return response()->json($data, 404);
        }

        $user = Auth::user();

        $turnoExistente = Turno::where('fecha_turno', $request->fecha_turno)
                                   ->where('horarioCanchaID', $horarioCancha->id)
                                   ->first();

        if ($turnoExistente) {
            $data = [
                'message' => 'Ya existe un turno para esa cancha en esta fecha y horario',
                'status' => 400
            ];
            return response()->json($data, 400);
        }

        // Crear una nueva reserva
        $turno = Turno::create([
            'fecha_turno' => $request->fecha_turno,
            'fecha_reserva' => now(),
            'horarioCanchaID' => $horarioCancha->id,
            'usuarioID' => $user->id,
            'monto_total' => $request->monto_total,
            'monto_seña' => $request->monto_seña,
            'estado' => $request->estado,
            'tipo' => 'único'
        ]);

        if (!$turno) {
            $data = [
                'message' => 'Error al crear la turno',
                'status' => 500
            ];
            return response()->json($data, 500);
        }

        $data = [
            'message' => 'Turno creada correctamente',
            'turno' => $turno,
            'status' => 201
        ];

        return response()->json($data, 201);
    }

    public function storeTurnoFijo(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->tokenCan('turnos:createTurnoFijo') || $user->rol === 'admin', 403, 'No tienes permisos para realizar esta acción');

        $validator = Validator::make($request->all(), [
            'fecha_turno' => 'required|date',
            'canchaID' => 'required|exists:canchas,id',
            'horarioID' => 'required|exists:horarios,id',
            'usuarioID' => 'required|exists:users,id',
            'monto_total' => 'required|numeric',
            'monto_seña' => 'required|numeric',
            'estado' => 'required|string',
            'tipo' => 'required|string|in:fijo'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validacion',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $horarioCancha = HorarioCancha::where('cancha_id', $request->canchaID)
                                      ->where('horario_id', $request->horarioID)
                                      ->first();

        if (!$horarioCancha) {
            return response()->json([
                'message' => 'HorarioCancha no encontrado',
                'status' => 404
            ], 404);
        }

        DB::beginTransaction();

        try {
            for ($i = 0; $i < 4; $i++) {
                $fecha_turno = now()->addWeeks($i)->toDateString();
                $turnoExistente = Turno::where('fecha_turno', $fecha_turno)
                                           ->where('horarioCanchaID', $horarioCancha->id)
                                           ->first();

                if ($turnoExistente) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Ya existe un turno para esa cancha en la fecha ' . $fecha_turno,
                        'status' => 400
                    ], 400);
                }

                $turno = Turno::create([
                    'fecha_turno' => $fecha_turno,
                    'fecha_reserva' => now(),
                    'horarioCanchaID' => $horarioCancha->id,
                    'usuarioID' => $request->usuarioID,
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
                'message' => 'Turnos creadas correctamente',
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
            'horarioCanchaID' => 'sometimes|required_with:fecha_turno|exists:horarios_cancha,id',
            'monto_total' => 'sometimes|numeric',
            'monto_seña' => 'sometimes|numeric',
            'estado' => 'sometimes',
            'tipo' => 'sometimes'
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
        if($request->has('fechaTurno') && $request->has('horarioCanchaID')){
            $turnoExistente = Turno::where('fecha_turno', $request->fecha_turno)
                                ->where('horarioCanchaID', $request->horarioCanchaID)
                                ->first();

            if ($turnoExistente) {
                $data = [
                'message' => 'Ya existe un turno para esa cancha en esta fecha y horario',
                'status' => 400
            ];
            return response()->json($data, 400);
            }
            $turno->fechaTurno = $request->fechaTurno;
            $turno->horarioCanchaID = $request->horarioCanchaID;

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

        if($request->has('tipo')){
            $turno->tipo = $request->tipo;
        }


        // Guardar los cambios en la base de datos
        $turno->save();

        // Respuesta exitosa
        $data = [
            'message' => 'turno actualizado correctamente',
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
                'message' => 'Turno eliminada correctamente',
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
}
