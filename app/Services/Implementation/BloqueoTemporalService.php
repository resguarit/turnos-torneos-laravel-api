<?php

namespace App\Services\Implementation;

use App\Models\BloqueoTemporal;
use App\Models\Turno;
use App\Services\Interface\BloqueoTemporalServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Jobs\EliminarBloqueo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class BloqueoTemporalService implements BloqueoTemporalServiceInterface
{
    public function bloquearHorario(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'horario_id' => 'required|exists:horarios,id',
            'cancha_id' => 'required|exists:canchas,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $clave = "bloqueo:{$request->fecha}:{$request->horario_id}:{$request->cancha_id}";

        // Verificar si el turno ya está reservado
        $ya_reservado = Turno::where('fecha_turno', $request->fecha)
            ->where('horario_id', $request->horario_id)
            ->where('cancha_id', $request->cancha_id)
            ->where('estado', '!=', 'Cancelado')
            ->exists();

        if ($ya_reservado) {
            return response()->json(['message' => 'El Turno ya no está disponible.'], 400);
        }

        // Verificar si el turno ya está bloqueado en Redis
        if (Redis::exists($clave)) {
            return response()->json(['message' => 'El Turno esta siendo reservado por alguien mas.'], 400);
        }

        // Crear el bloqueo en Redis con un tiempo de expiración de 10 minutos
        Redis::set($clave, json_encode([
            'usuario_id' => Auth::id(),
            'horario_id' => $request->horario_id,
            'cancha_id' => $request->cancha_id,
            'fecha' => $request->fecha,
        ]));    

        Redis::expire($clave, 180);

        return response()->json([
            'message' => 'Bloqueo temporal creado con éxito.',
            'status' => 201
        ], 201);

    }

    public function cancelarBloqueo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'horario_id' => 'required|exists:horarios,id',
            'cancha_id' => 'required|exists:canchas,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $clave = "bloqueo:{$request->fecha}:{$request->horario_id}:{$request->cancha_id}";

        // Verificar si el bloqueo existe
        if (!Redis::exists($clave)) {
            return response()->json([
                'message' => 'No hay un bloqueo activo para este turno.',
                'status' => '404'
            ], 404);
        }

        // Eliminar el bloqueo de Redis
        Redis::del($clave);

        return response()->json([
            'message' => 'Bloqueo cancelado con éxito.',
            'status' => 200
        ], 200);
    }

    public function listarBloqueos()
    {
        $claves = Redis::keys('bloqueo:*');
    
        // Recuperar los valores de cada clave
        $bloqueos = [];
        foreach ($claves as $clave) {
            $bloqueos[] = [
                'clave' => $clave,
                'valor' => json_decode(Redis::get($clave), true),
            ];
        }
    
        return response()->json([
            'bloqueos' => $bloqueos,
        ]);
    }
}