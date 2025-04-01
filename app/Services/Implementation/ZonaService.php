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
use App\Models\Grupo;

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
        return Zona::where('torneo_id', $torneoId)->with('equipos', 'fechas', 'grupos')->get();
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

        if ($numEquipos < 2 ) {
            return response()->json([
                'message' => 'El número de equipos debe ser par y mayor o igual a 2',
                'status' => 400
            ], 400);
        }

        $fechas = [];

        if ($zona->formato === ZonaFormato::LIGA) {
            $fechas = $this->createFechasLiga($zona, $equipos);
        } elseif ($zona->formato === ZonaFormato::ELIMINATORIA) {
            $fechas = $this->createFechasEliminatoria($zona, $equipos);
        } elseif ($zona->formato === ZonaFormato::GRUPOS) {
            $numGrupos = $request->input('num_grupos');
            if ($numGrupos < 1 || $numEquipos % $numGrupos != 0) {
                return response()->json([
                    'num_grupos' => $numGrupos,
                    'numero_equipos' => $numEquipos,
                    'message' => 'El número de grupos debe ser mayor o igual a 1 y los equipos deben dividirse equitativamente entre los grupos',
                    'status' => 400
                ], 400);
            }
            $fechas = $this->createFechasGrupos($zonaId, $numGrupos);
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
        $numFechas = ($numEquipos % 2 == 0) ? $numEquipos - 1 : $numEquipos;

        $equiposArray = $equipos->toArray();
        shuffle($equiposArray);
        $fechas = [];

        // Si el número de equipos es impar, agregamos un "equipo libre"
        if ($numEquipos % 2 != 0) {
            $equiposArray[] = ['id' => null, 'nombre' => 'Libre'];
            $numEquipos++;
        }

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

                // Si alguno de los equipos es "Libre", no creamos un partido
                if ($local['id'] === null || $visitante['id'] === null) {
                    continue;
                }

                $partido = Partido::create([
                    'fecha_id' => $fecha->id,
                    'equipo_local_id' => $local['id'],
                    'equipo_visitante_id' => $visitante['id'],
                    'estado' => 'Pendiente',
                    'fecha' => $fecha->fecha_inicio,
                    'horario_id' => null,
                    'cancha_id' => null,
                ]);

                // Agregar los equipos al array de equipos del partido
                $partido->equipos()->attach([$local['id'], $visitante['id']]);

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
        shuffle($equiposArray);
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

    private function createFechasGrupos($zonaId, $numGrupos)
    {
        $zona = Zona::with('grupos.equipos')->find($zonaId);

        if (!$zona) {
            return response()->json([
                'message' => 'Zona no encontrada',
                'status' => 404
            ], 404);
        }

        if ($numGrupos < 2) {
            return response()->json([
                'numero_grupos' => $numGrupos,
                'message' => 'El número de grupos debe ser mayor o igual a 2',
                'status' => 400
            ], 400);
        }

        $fechas = [];
        $numFechas = 0;

        foreach ($zona->grupos as $grupo) {
            $equipos = $grupo->equipos;
            $numEquipos = $equipos->count();
            $numFechas = max($numFechas, $numEquipos - 1);
        }

        for ($i = 0; $i < $numFechas; $i++) {
            $fecha = Fecha::create([
                'nombre' => 'Fecha ' . ($i + 1),
                'fecha_inicio' => now()->addWeeks($i),
                'fecha_fin' => now()->addWeeks($i)->addDays(1),
                'estado' => 'Pendiente',
                'zona_id' => $zona->id,
            ]);

            foreach ($zona->grupos()->with('equipos')->get() as $grupo) {
                $equipos = $grupo->equipos; // Aquí tienes los equipos del grupo
                $numEquipos = $equipos->count();

                $equiposArray = $equipos->toArray();
                shuffle($equiposArray);

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
                        'fecha' => $fecha->fecha_inicio,
                        'horario_id' => null,
                        'cancha_id' => null,
                    ]);

                    // Asociar los equipos al partido en la tabla pivote
                    $partido->equipos()->attach([$local['id'], $visitante['id']]);
                }
            }

            $fechas[] = $fecha;
        }

        return $fechas;
    }

    public function crearGruposAleatoriamente($zonaId, $numGrupos)
    {
        $zona = Zona::with('equipos')->find($zonaId);

        if (!$zona) {
            throw new \Exception('Zona no encontrada', 404);
        }

        $equipos = $zona->equipos;
        $numEquipos = $equipos->count();

        if ($numEquipos < 2 || $numEquipos % 2 != 0) {
            throw new \Exception('El número de equipos debe ser par y mayor o igual a 2', 400);
        }

        if ($numGrupos < 1) {
            throw new \Exception('El número de grupos debe ser mayor o igual a 1', 400);
        }

        $equiposArray = $equipos->toArray();
        shuffle($equiposArray);

        $grupos = [];
        for ($i = 0; $i < $numGrupos; $i++) {
            $grupo = Grupo::create([
                'nombre' => 'Grupo ' . ($i + 1),
                'zona_id' => $zona->id,
            ]);
            $grupos[] = $grupo;
        }

        $grupoIndex = 0;
        foreach ($equiposArray as $equipo) {
            $grupos[$grupoIndex]->equipos()->attach($equipo['id']);
            $grupoIndex = ($grupoIndex + 1) % $numGrupos;
        }

        return $grupos;
    }
}