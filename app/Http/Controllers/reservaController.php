<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservaResource;
use App\Models\HorarioCancha;
use Illuminate\Http\Request;
use App\Models\Reserva;
use Illuminate\Support\Facades\Validator;

class reservaController extends Controller
{
    //

    public function index(Request $request)
    {
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

        $query = Reserva::query();

        if ($request->has('fecha')) {
            $query->whereDate('fecha_turno', $request->fecha);
        }

        if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
            $query->whereBetween('fecha_turno', [$request->fecha_inicio, $request->fecha_fin]);
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

    public function store(Request $request)
    {
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
}
