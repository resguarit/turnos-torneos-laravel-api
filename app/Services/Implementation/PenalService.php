<?php

namespace App\Services\Implementation;
use App\Services\Interface\PenalServiceInterface;
use App\Models\Penal;
use App\Models\Partido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PenalService implements PenalServiceInterface
{
    public function getAll()
    {
        return Penal::with('equipoLocal', 'equipoVisitante', 'partido')->get();
    }

    public function getById($id)
    {
        return Penal::with('equipoLocal', 'equipoVisitante', 'partido')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'partido_id' => 'required|exists:partidos,id',
            'equipo_local_id' => 'required|exists:equipos,id',
            'equipo_visitante_id' => 'required|exists:equipos,id',
            'penales_local' => 'required|integer|min:0',
            'penales_visitante' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $penal = Penal::create($request->all());

        // Traer el partido y actualizar el ganador_id
        $partido = Partido::find($request->partido_id);
        if ($partido) {
            if ($request->penales_local > $request->penales_visitante) {
                $partido->ganador_id = $request->equipo_local_id;
            } elseif ($request->penales_visitante > $request->penales_local) {
                $partido->ganador_id = $request->equipo_visitante_id;
            } else {
                $partido->ganador_id = null; // Empate, no debería pasar en penales
            }
            $partido->save();
        }

        return response()->json([
            'message' => 'Penal creado correctamente',
            'penal' => $penal,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $penal = Penal::find($id);
        if (!$penal) {
            return response()->json(['message' => 'Penal no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'partido_id' => 'sometimes|exists:partidos,id',
            'equipo_local_id' => 'sometimes|exists:equipos,id',
            'equipo_visitante_id' => 'sometimes|exists:equipos,id',
            'penales_local' => 'sometimes|integer|min:0',
            'penales_visitante' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $penal->update($request->all());

        return response()->json([
            'message' => 'Penal actualizado correctamente',
            'penal' => $penal,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $penal = Penal::find($id);
        if (!$penal) {
            return response()->json(['message' => 'Penal no encontrado'], 404);
        }

        $penal->delete();

        return response()->json(['message' => 'Penal eliminado correctamente', 'status' => 200], 200);
    }

    public function getByPartido($partidoId)
    {
        return Penal::where('partido_id', $partidoId)
            ->with('equipoLocal', 'equipoVisitante')
            ->get();
    }
}