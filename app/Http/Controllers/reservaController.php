<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservaResource;
use Illuminate\Http\Request;
use App\Models\Reserva;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class reservaController extends Controller
{
    //

    public function index(){

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

    public function store(Request $request){

        $validator = Validator::make($request->all(), [
            'fecha_turno' => 'required|date',
            'horarioCanchaID' => 'required|exists:horarios_cancha,id',
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

        $reserva = Reserva::create([
            'fecha_turno' => $request->fecha_turno,
            'fecha_reserva' => now(),
            'horarioCanchaID' => $request->horarioCanchaID,
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
