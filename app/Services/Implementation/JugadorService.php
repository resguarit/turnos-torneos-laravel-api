<?php
// app/Services/Implementation/JugadorService.php

namespace App\Services\Implementation;

use App\Models\Jugador;
use App\Services\Interface\JugadorServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JugadorService implements JugadorServiceInterface
{
    public function getAll()
    {
        return Jugador::with('equipo')->get();
    }

    public function getById($id)
    {
        return Jugador::with('equipo')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'dni' => 'required|string|max:20|unique:jugadores,dni',
            'telefono' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'required|date',
            'equipo_id' => 'required|exists:equipos,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $jugador = Jugador::create($request->all());

        return response()->json([
            'message' => 'Jugador creado correctamente',
            'jugador' => $jugador,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $jugador = Jugador::find($id);

        if (!$jugador) {
            return response()->json([
                'message' => 'Jugador no encontrado',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'dni' => 'required|string|max:20|unique:jugadores,dni,' . $id,
            'telefono' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'required|date',
            'equipo_id' => 'required|exists:equipos,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $jugador->update($request->all());

        return response()->json([
            'message' => 'Jugador actualizado correctamente',
            'jugador' => $jugador,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $jugador = Jugador::find($id);

        if (!$jugador) {
            return response()->json([
                'message' => 'Jugador no encontrado',
                'status' => 404
            ], 404);
        }

        $jugador->delete();

        return response()->json([
            'message' => 'Jugador eliminado correctamente',
            'status' => 200
        ], 200);
    }

    public function getByEquipo($equipoId)
    {
        return Jugador::where('equipo_id', $equipoId)->get();
    }

    public function getByZona($zonaId)
    {
        return Jugador::whereHas('equipo', function ($query) use ($zonaId) {
            $query->where('zona_id', $zonaId);
        })->with('equipo')->get();
    }

    public function createMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jugadores' => 'required|array',
            'jugadores.*.nombre' => 'required|string|max:255',
            'jugadores.*.apellido' => 'required|string|max:255',
            'jugadores.*.dni' => 'required|string|max:20|unique:jugadores,dni',
            'jugadores.*.telefono' => 'nullable|string|max:20',
            'jugadores.*.fecha_nacimiento' => 'required|date',
            'equipo_id' => 'required|exists:equipos,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $equipoId = $request->input('equipo_id');
        $jugadoresData = $request->input('jugadores');

        $jugadores = [];
        foreach ($jugadoresData as $jugadorData) {
            $jugadorData['equipo_id'] = $equipoId;
            $jugadores[] = Jugador::create($jugadorData);
        }

        return response()->json([
            'message' => 'Jugadores creados correctamente',
            'jugadores' => $jugadores,
            'status' => 201
        ], 201);
    }
}