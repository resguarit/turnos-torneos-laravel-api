<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservaResource;
use App\Models\HorarioCancha;
use Illuminate\Http\Request;
use App\Models\Reserva;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;


class reservaController extends Controller
{
    //

    public function index(Request $request)
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('reservas:show') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');
        
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
        $query = Reserva::query();

        if ($request->has('fecha')) {
            $query->whereDate('fecha_turno', $request->fecha);
        }

        if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
            $query->whereBetween('fecha_turno', [$request->fecha_inicio, $request->fecha_fin]);
        }

        if(!$request->has('fecha') && !$request->has('fecha_inicio') && !$request->has('fecha_fin')){
            $query->whereDate('fecha_turno', '>=', $fechaHoy);
        }

        $reservas = $query->with([
            'usuario',
            'horarioCancha.horario',
            'horarioCancha.cancha',
        ])->get();

        $data = [
            'reservas' => ReservaResource::collection($reservas),
            'status' => 200
        ];

        return response()->json($data, 200);
    }

    public function getAll(){

        $user = Auth::user();

        abort_unless( $user->tokenCan('reservas:show_all') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        $reservas = Reserva::with([
            'usuario',
            'horarioCancha.horario',
            'horarioCancha.cancha',
        ])->get();

        $data = [
            'reservas' => ReservaResource::collection($reservas),
            'status' => 200
        ];

        return response()->json($data, 200);
    }

    public function store(Request $request)
    {
        
        $user = Auth::user();

        abort_unless( $user->tokenCan('reservas:create') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        if (!$request->user()->tokenCan('create_reservation')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'fecha_turno' => 'required|date',
            'canchaID' => 'required|exists:canchas,id',
            'horarioID' => 'required|exists:horarios,id',
            'usuarioID' => 'required|exists:users,id',
            'monto_total' => 'required',
            'monto_seña' => 'required',
            'estado' => 'required'
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

        $reservaExistente = Reserva::where('fecha_turno', $request->fecha_turno)
                                   ->where('horarioCanchaID', $horarioCancha->id)
                                   ->first();

        if ($reservaExistente) {
            $data = [
                'message' => 'Ya existe una reserva para esa cancha en esta fecha y horario',
                'status' => 400
            ];
            return response()->json($data, 400);
        }

        // Crear una nueva reserva
        $reserva = Reserva::create([
            'fecha_turno' => $request->fecha_turno,
            'fecha_reserva' => now(),
            'horarioCanchaID' => $horarioCancha->id,
            'usuarioID' => $request->usuarioID,
            'monto_total' => $request->monto_total,
            'monto_seña' => $request->monto_seña,
            'estado' => $request->estado
        ]);

        if (!$reserva) {
            $data = [
                'message' => 'Error al crear la reserva',
                'status' => 500
            ];
            return response()->json($data, 500);
        }

        $data = [
            'message' => 'Reserva creada correctamente',
            'reserva' => $reserva,
            'status' => 201
        ];

        return response()->json($data, 201);
    }

    public function update(Request $request, $id)
    {

        $user = Auth::user();

        abort_unless( $user->tokenCan('reservas:update') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        // Encontrar la reserva por su ID
        $reserva = Reserva::find($id);

        // Verificar si la reserva existe
        if (!$reserva) {
            $data = [
                'message' => 'No hay reserva encontrada',
                'status' => 404
            ];
            return response()->json($data, 404);
        }

        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'fecha_turno' => 'sometimes|date',
            'horarioCanchaID' => 'sometimes|exists:horarios_cancha,id',
            'monto_total' => 'sometimes',
            'monto_seña' => 'sometimes',
            'estado' => 'sometimes'
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
        if($request->has('fechaTurno')){
            $reserva->fechaTurno = $request->fechaTurno;
        }

        if($request->has('horarioCanchaID')){
            $reserva->horarioCanchaID = $request->horarioCanchaID;
        }

        if($request->has('monto_total')){
            $reserva->monto_total = $request->monto_total;
        }

        if($request->has('monto_seña')){
            $reserva->monto_seña = $request->monto_seña;
        }
        
        if($request->has('estado')){
            $reserva->estado = $request->estado;
        }

        // Guardar los cambios en la base de datos
        $reserva->save();

        // Respuesta exitosa
        $data = [
            'message' => 'Reserva actualizada correctamente',
            'reserva' => $reserva,
            'status' => 200
        ];

        return response()->json($data, 200);
    }

    public function destroy($id)
    {
        $user = Auth::user();

        abort_unless( $user->tokenCan('reservas:destroy') || $user->rol === 'admin',403, 'No tienes permisos para realizar esta acción');

        try {
            $reserva = Reserva::findOrFail($id);
            $reserva->delete();

            $data = [
                'message' => 'Reserva eliminada correctamente',
                'status' => 200
            ];

            return response()->json($data, 200);

        } catch (ModelNotFoundException $e) {
            $data = [
                'message' => 'reserva no encontrada',
                'status' => 404
            ];
            return response()->json($data, 404);
        }
    }
}
