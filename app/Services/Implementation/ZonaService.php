<?php
// app/Services/Implementation/ZonaService.php

namespace App\Services\Implementation;

use App\Models\Zona;
use App\Models\Fecha;
use App\Models\Partido;
use App\Services\Interface\ZonaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Enums\ZonaFormato;
use Illuminate\Support\Facades\Log;

class ZonaService implements ZonaServiceInterface
{
    public function getAll()
    {
        return Zona::with('equipos', 'fechas')->get();
    }

    public function getById($id)
    {
        return Zona::with('equipos', 'fechas')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'formato' => ['required', 'string', Rule::in(ZonaFormato::values())],
            'año' => 'required|integer',
            'torneo_id' => 'required|exists:torneos,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $zona = Zona::create($request->all());

        return response()->json([
            'message' => 'Zona creada correctamente',
            'zona' => $zona,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $zona = Zona::find($id);

        if (!$zona) {
            return response()->json([
                'message' => 'Zona no encontrada',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'formato' => ['required', 'string', Rule::in(ZonaFormato::values())],
            'año' => 'required|integer',
            'torneo_id' => 'required|exists:torneos,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $zona->update($request->all());

        return response()->json([
            'message' => 'Zona actualizada correctamente',
            'zona' => $zona,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $zona = Zona::find($id);

        if (!$zona) {
            return response()->json([
                'message' => 'Zona no encontrada',
                'status' => 404
            ], 404);
        }

        $zona->delete();

        return response()->json([
            'message' => 'Zona eliminada correctamente',
            'status' => 200
        ], 200);
    }

    public function getByTorneo($torneoId)
    {
        return Zona::where('torneo_id', $torneoId)->with('equipos', 'fechas')->get();
    }

    public function createFechas(Request $request, $zonaId)
    {
        $zona = Zona::with('equipos')->find($zonaId);

        if (!$zona) {
            return response()->json([
                'message' => 'Zona no encontrada',
                'status' => 404
            ], 404);
        }

        $equipos = $zona->equipos;
        $numEquipos = $equipos->count();

        if ($numEquipos < 2) {
            return response()->json([
                'message' => 'No hay suficientes equipos para crear fechas',
                'status' => 400
            ], 400);
        }

        $fechas = [];

        if ($zona->formato === ZonaFormato::LIGA) {
            $fechas = $this->createFechasLiga($zona, $equipos);
        } elseif ($zona->formato === ZonaFormato::ELIMINATORIA) {
            $fechas = $this->createFechasEliminatoria($zona, $equipos);
        } elseif ($zona->formato === ZonaFormato::GRUPOS) {
            $fechas = $this->createFechasGrupos($zona, $equipos);
        }

        Log::info('Fechas creadas:', ['fechas' => $fechas]);

        return response()->json([
            'message' => 'Fechas creadas correctamente',
            'fechas' => $fechas,
            'status' => 201
        ], 201);
    }

    private function createFechasLiga($zona, $equipos)
    {
        $numEquipos = $equipos->count();
        $numFechas = $numEquipos - 1;

        $equiposArray = $equipos->toArray();
        $fechas = [];

        for ($i = 0; $i < $numFechas; $i++) {
            $fecha = Fecha::create([
                'nombre' => 'Fecha ' . ($i + 1),
                'fecha_inicio' => now()->addWeeks($i),
                'fecha_fin' => now()->addWeeks($i)->addDays(1),
                'estado' => 'Pendiente',
                'zona_id' => $zona->id,
            ]);

            $partidos = [];

            // Crear partidos para la fecha
            for ($j = 0; $j < $numEquipos / 2; $j++) {
                $local = $equiposArray[$j];
                $visitante = $equiposArray[$numEquipos - 1 - $j];

                $partido = Partido::create([
                    'fecha_id' => $fecha->id,
                    'equipo_local_id' => $local['id'],
                    'equipo_visitante_id' => $visitante['id'],
                    'estado' => 'Pendiente',
                    'fecha' => $fecha->fecha_inicio, // Proporcionar un valor para el campo fecha
                    'horario_id' => null, // Permitir valores nulos
                    'cancha_id' => null, // Permitir valores nulos
                ]);

                $partidos[] = $partido;
            }

            $fecha->partidos = $partidos;
            $fechas[] = $fecha;

            // Rotar equipos para la siguiente fecha
            $last = array_pop($equiposArray);
            array_splice($equiposArray, 1, 0, [$last]);
        }

        return $fechas;
    }

    private function createFechasEliminatoria($zona, $equipos)
    {
        $fecha = Fecha::create([
            'nombre' => 'Eliminatoria',
            'fecha_inicio' => now(),
            'fecha_fin' => now()->addDays(1),
            'estado' => 'Pendiente',
            'zona_id' => $zona->id,
        ]);

        $partidos = [];

        // Crear partidos para la fecha
        $equiposArray = $equipos->toArray();
        $numEquipos = count($equiposArray);

        for ($i = 0; $i < $numEquipos / 2; $i++) {
            $local = $equiposArray[$i];
            $visitante = $equiposArray[$numEquipos - 1 - $i];

            $partido = Partido::create([
                'fecha_id' => $fecha->id,
                'equipo_local_id' => $local['id'],
                'equipo_visitante_id' => $visitante['id'],
                'estado' => 'Pendiente',
                'fecha' => $fecha->fecha_inicio, // Proporcionar un valor para el campo fecha
                'horario_id' => null, // Permitir valores nulos
                'cancha_id' => null, // Permitir valores nulos
            ]);

            $partidos[] = $partido;
        }

        $fecha->partidos = $partidos;

        return [$fecha];
    }

    private function createFechasGrupos($zona, $equipos)
    {
        $numEquipos = $equipos->count();
        $numFechas = $numEquipos - 1;

        $equiposArray = $equipos->toArray();
        $fechas = [];

        for ($i = 0; $i < $numFechas; $i++) {
            $fecha = Fecha::create([
                'nombre' => 'Fecha ' . ($i + 1),
                'fecha_inicio' => now()->addWeeks($i),
                'fecha_fin' => now()->addWeeks($i)->addDays(1),
                'estado' => 'Pendiente',
                'zona_id' => $zona->id,
            ]);

            $partidos = [];

            // Crear partidos para la fecha
            for ($j = 0; $j < $numEquipos / 2; $j++) {
                $local = $equiposArray[$j];
                $visitante = $equiposArray[$numEquipos - 1 - $j];

                $partido = Partido::create([
                    'fecha_id' => $fecha->id,
                    'equipo_local_id' => $local['id'],
                    'equipo_visitante_id' => $visitante['id'],
                    'estado' => 'Pendiente',
                    'fecha' => $fecha->fecha_inicio, // Proporcionar un valor para el campo fecha
                    'horario_id' => null, // Permitir valores nulos
                    'cancha_id' => null, // Permitir valores nulos
                ]);

                $partidos[] = $partido;
            }

            $fecha->partidos = $partidos;
            $fechas[] = $fecha;

            // Rotar equipos para la siguiente fecha
            $last = array_pop($equiposArray);
            array_splice($equiposArray, 1, 0, [$last]);
        }

        return $fechas;
    }
}