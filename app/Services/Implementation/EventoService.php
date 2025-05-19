<?php

namespace App\Services\Implementation;

use Illuminate\Http\Request;
use App\Models\Evento;
use Illuminate\Support\Facades\Validator;
use App\Services\Interface\EventoServiceInterface;
use App\Enums\EventoEstado;


class EventoService implements EventoServiceInterface
{
    public function getAll(){
        return Evento::with('persona')->get();
    }

    public function getById($id){
        return Evento::with('persona')->find($id);
    }

    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'required|string|max:255',
            'fecha' => 'required|date',
            'persona_id' => 'required|exists:personas,id',
            'combinaciones' => 'required|array',
            'combinaciones.*.horario_id' => 'required|exists:horarios,id',
            'combinaciones.*.cancha_id' => 'required|exists:canchas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $evento = Evento::create($request->only(['nombre', 'descripcion', 'fecha', 'persona_id']));

        foreach ($request->combinaciones as $combinacion) {
            \App\Models\EventoHorarioCancha::create([
                'evento_id' => $evento->id,
                'horario_id' => $combinacion['horario_id'],
                'cancha_id' => $combinacion['cancha_id'],
                // Agrega otros campos necesarios como 'estado'
            ]);
        }

        return response()->json([
            'message' => 'Evento creado correctamente',
            'evento' => $evento,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id){
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'sometimes|string|max:255',
            'fecha' => 'sometimes|date',
            'persona_id' => 'sometimes|exists:personas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $evento = Evento::find($id);
        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado',
                'status' => 404
            ], 404);
        }

        $evento->update($request->all());

        return response()->json([
            'message' => 'Evento actualizado correctamente',
            'evento' => $evento,
            'status' => 200
        ], 200);
    }

    public function delete($id){
        $evento = Evento::find($id);
        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado',
                'status' => 404
            ], 404);
        }

        $evento->delete();

        return response()->json([
            'message' => 'Evento eliminado correctamente',
            'status' => 200
        ], 200);
    }
}