<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reserva;
use Illuminate\Support\Facades\Validator;

class reservaController extends Controller
{
    //

    public function index(){
        $reservas = Reserva::all();

        $data = [
            'reservas' => $reservas,
            'status' => 200
        ];

        return response()->json($data, 200);
    }

    public function store(Request $request){

        $validator = Validator::make($request->all(), [
            'fecha_turno' => 'required',
            'horarioCanchaID' => 'required',
            'usuarioID' => 'required',
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
}
