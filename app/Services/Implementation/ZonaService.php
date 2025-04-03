<?php
// app/Services/Implementation/ZonaService.php

namespace App\Services\Implementation;

use App\Models\Zona;
use App\Models\Fecha;
use App\Models\Partido;
use App\Models\Equipo;
use App\Services\Interface\ZonaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Enums\ZonaFormato;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Grupo;
use Carbon\Carbon;

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
        $validator = Validator::make($request->all(), [
            'fecha_inicial' => 'required|date_format:Y-m-d', // Validar que la fecha_inicial sea obligatoria y tenga el formato correcto
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

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
                'message' => 'El número de equipos debe ser mayor o igual a 2',
                'status' => 400
            ], 400);
        }

        // Validar que el número de equipos sea 4, 8 o 16 para eliminatoria
        if ($zona->formato === ZonaFormato::ELIMINATORIA && !in_array($numEquipos, [4, 8, 16])) {
            return response()->json([
                'message' => 'El número de equipos debe ser 4, 8 o 16 para un torneo eliminatoria',
                'status' => 400
            ], 400);
        }

        $fechaInicial = Carbon::createFromFormat('Y-m-d', $request->input('fecha_inicial')); // Convertir la fecha inicial a un objeto Carbon

        $fechas = [];

        if ($zona->formato === ZonaFormato::LIGA) {
            $fechas = $this->createFechasLiga($zona, $equipos, $fechaInicial);
        } elseif ($zona->formato === ZonaFormato::LIGA_IDA_VUELTA) {
            $fechas = $this->createFechasLigaIdaVuelta($zona, $equipos, $fechaInicial);
        } elseif ($zona->formato === ZonaFormato::ELIMINATORIA) {
            $fechas = $this->createFechasEliminatoria($zona, $equipos, $fechaInicial);
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
            $fechas = $this->createFechasGrupos($zonaId, $numGrupos, $fechaInicial);
        }

        return response()->json([
            'message' => 'Fechas creadas correctamente',
            'fechas' => Fecha::with('partidos.equipos')->where('zona_id', $zona->id)->get(),
            'status' => 201
        ], 201);
    }

    private function createFechasLiga($zona, $equipos, $fechaInicial)
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
                'fecha_inicio' => $fechaInicial->copy()->addWeeks($i),
                'fecha_fin' => $fechaInicial->copy()->addWeeks($i)->addDays(1),
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

    private function createFechasLigaIdaVuelta($zona, $equipos, $fechaInicial)
    {
        $numEquipos = $equipos->count();
        $equiposArray = $equipos->toArray();
        $fechas = [];

        // Crear partidos de ida
        for ($i = 0; $i < $numEquipos - 1; $i++) {
            $fecha = Fecha::create([
                'nombre' => 'Fecha Ida ' . ($i + 1),
                'fecha_inicio' => $fechaInicial->copy()->addWeeks($i),
                'fecha_fin' => $fechaInicial->copy()->addWeeks($i)->addDays(1),
                'estado' => 'Pendiente',
                'zona_id' => $zona->id,
            ]);

            $partidos = [];

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

                $partidos[] = $partido;
            }

            $fecha->partidos = $partidos;
            $fechas[] = $fecha;

            // Rotar equipos para la siguiente fecha
            $last = array_pop($equiposArray);
            array_splice($equiposArray, 1, 0, [$last]);
        }

        // Crear partidos de vuelta
        for ($i = 0; $i < $numEquipos - 1; $i++) {
            $fecha = Fecha::create([
                'nombre' => 'Fecha Vuelta ' . ($i + 1),
                'fecha_inicio' => $fechaInicial->copy()->addWeeks($numEquipos - 1 + $i),
                'fecha_fin' => $fechaInicial->copy()->addWeeks($numEquipos - 1 + $i)->addDays(1),
                'estado' => 'Pendiente',
                'zona_id' => $zona->id,
            ]);

            $partidos = [];

            for ($j = 0; $j < $numEquipos / 2; $j++) {
                $local = $equiposArray[$numEquipos - 1 - $j];
                $visitante = $equiposArray[$j];

                $partido = Partido::create([
                    'fecha_id' => $fecha->id,
                    'equipo_local_id' => $local['id'],
                    'equipo_visitante_id' => $visitante['id'],
                    'estado' => 'Pendiente',
                    'fecha' => $fecha->fecha_inicio,
                    'horario_id' => null,
                    'cancha_id' => null,
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

    private function createFechasEliminatoria($zona, $equipos, $fechaInicial)
    {
        $numEquipos = $equipos->count();

        // Determinar el nombre de la fecha según el número de equipos
        $nombreFecha = $this->getNombreEliminatoria($numEquipos);

        $fecha = Fecha::create([
            'nombre' => $nombreFecha,
            'fecha_inicio' => $fechaInicial,
            'fecha_fin' => $fechaInicial->copy()->addDays(1),
            'estado' => 'Pendiente',
            'zona_id' => $zona->id,
        ]);

        $partidos = [];

        // Crear partidos para la fecha
        $equiposArray = $equipos->toArray();
        shuffle($equiposArray);

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

            // Asociar los equipos al partido en la tabla pivote
            $partido->equipos()->attach([$local['id'], $visitante['id']]);

            $partidos[] = $partido;
        }

        $fecha->partidos = $partidos;

        return [$fecha];
    }

    /**
     * Obtener el nombre de la eliminatoria según el número de equipos.
     */
    private function getNombreEliminatoria($numEquipos)
    {
        switch ($numEquipos) {
            case 2:
                return 'Final';
            case 4:
                return 'Semifinal';
            case 8:
                return 'Cuartos de Final';
            case 16:
                return 'Octavos de Final';
            case 32:
                return 'Dieciseisavos de Final';
            case 64:
                return 'Treintaidosavos de Final';
            default:
                return 'Eliminatoria';
        }
    }

    private function createFechasGrupos($zonaId, $numGrupos, $fechaInicial)
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
        $zona = Zona::with('equipos', 'grupos')->find($zonaId);

        if (!$zona) {
            throw new \Exception('Zona no encontrada', 404);
        }

        $equipos = $zona->equipos;
        $numEquipos = $equipos->count();

        if ($numEquipos < 2 || $numEquipos % $numGrupos !== 0) {
            throw new \Exception('El número de equipos debe ser divisible por el número de grupos.', 400);
        }

        // Eliminar los grupos existentes
        foreach ($zona->grupos as $grupo) {
            $grupo->equipos()->detach();
            $grupo->delete();
        }

        // Crear nuevos grupos
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

    public function reemplazarEquipo($zonaId, $equipoIdViejo, $equipoIdNuevo)
    {
        try {
            DB::beginTransaction();
            
            $zona = Zona::find($zonaId);
            if (!$zona) {
                return response()->json([
                    'message' => 'Zona no encontrada',
                    'status' => 404
                ], 404);
            }
            
            // Verificar que el equipo viejo pertenezca a la zona
            $equipoViejo = Equipo::where('id', $equipoIdViejo)->where('zona_id', $zonaId)->first();
            if (!$equipoViejo) {
                return response()->json([
                    'message' => 'El equipo a reemplazar no pertenece a esta zona',
                    'status' => 400
                ], 400);
            }
            
            // Verificar que el equipo nuevo exista
            $equipoNuevo = Equipo::find($equipoIdNuevo);
            if (!$equipoNuevo) {
                return response()->json([
                    'message' => 'El equipo nuevo no existe',
                    'status' => 404
                ], 404);
            }
            
            // Asignar el equipo nuevo a la zona
            $equipoNuevo->zona_id = $zonaId;
            $equipoNuevo->save();
            
            // Desasignar el equipo viejo de la zona
            $equipoViejo->zona_id = null;
            $equipoViejo->save();
            
            // Reemplazar el equipo en todos los partidos
            $partidos = Partido::whereHas('fecha', function($query) use ($zonaId) {
                $query->where('zona_id', $zonaId);
            })->where(function($query) use ($equipoIdViejo) {
                $query->where('equipo_local_id', $equipoIdViejo)
                    ->orWhere('equipo_visitante_id', $equipoIdViejo);
            })->get();
            
            // También actualizar grupos si es formato de grupos
            $grupos = Grupo::where('zona_id', $zonaId)->get();
            foreach ($grupos as $grupo) {
                $equiposGrupo = json_decode($grupo->equipos_json, true) ?: [];
                if (in_array($equipoIdViejo, $equiposGrupo)) {
                    // Reemplazar el ID del equipo viejo por el nuevo
                    $equiposGrupo = array_map(function($id) use ($equipoIdViejo, $equipoIdNuevo) {
                        return $id == $equipoIdViejo ? $equipoIdNuevo : $id;
                    }, $equiposGrupo);
                    $grupo->equipos_json = json_encode($equiposGrupo);
                    $grupo->save();
                }
            }
            
            foreach ($partidos as $partido) {
                if ($partido->equipo_local_id == $equipoIdViejo) {
                    $partido->equipo_local_id = $equipoIdNuevo;
                }
                if ($partido->equipo_visitante_id == $equipoIdViejo) {
                    $partido->equipo_visitante_id = $equipoIdNuevo;
                }
                $partido->save();
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Equipo reemplazado correctamente',
                'status' => 200
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al reemplazar el equipo',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function generarSiguienteRonda(Request $request, $zonaId)
    {
        $validator = Validator::make($request->all(), [
            'equipos' => 'required|array|min:2', // Validar que se pasen los equipos
            'fecha_anterior_id' => 'required|exists:fechas,id', // Validar que la fecha anterior exista
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $zona = Zona::find($zonaId);
        if (!$zona) {
            return response()->json([
                'message' => 'Zona no encontrada',
                'status' => 404
            ], 404);
        }

        $equipos = Equipo::whereIn('id', $request->input('equipos'))->get();
        $fechaAnterior = Fecha::find($request->input('fecha_anterior_id'));

        return $this->createSiguienteRondaEliminatoria($zona, $equipos, $fechaAnterior);
    }

    private function createSiguienteRondaEliminatoria($zona, $equipos, $fechaAnterior)
    {
        $numEquipos = count($equipos);

        // Validar que el número de equipos sea una potencia de 2
        if (!in_array($numEquipos, [2, 4, 8, 16, 32, 64])) {
            return response()->json([
                'message' => 'El número de equipos debe ser una potencia de 2 (2, 4, 8, 16, etc.) para continuar en un torneo eliminatoria',
                'status' => 400
            ], 400);
        }

        // Determinar el nombre de la nueva fase
        $nombreFase = $this->getNombreEliminatoria($numEquipos);

        // Convertir fecha_fin a un objeto Carbon
        $fechaFinAnterior = Carbon::parse($fechaAnterior->fecha_fin);

        // Crear la nueva fecha
        $fecha = Fecha::create([
            'nombre' => $nombreFase,
            'fecha_inicio' => $fechaFinAnterior->copy()->addDays(1), // La nueva fecha comienza después de la anterior
            'fecha_fin' => $fechaFinAnterior->copy()->addDays(2),
            'estado' => 'Pendiente',
            'zona_id' => $zona->id,
        ]);

        $partidos = [];

        // Convertir la colección de equipos a un array y barajar
        $equiposArray = $equipos->toArray();
        shuffle($equiposArray);

        // Crear los partidos para la nueva fase
        for ($i = 0; $i < $numEquipos / 2; $i++) {
            $local = $equiposArray[$i];
            $visitante = $equiposArray[$numEquipos - 1 - $i];

            $partido = Partido::create([
                'fecha_id' => $fecha->id,
                'equipo_local_id' => $local['id'],
                'equipo_visitante_id' => $visitante['id'],
                'estado' => 'Pendiente',
                'fecha' => $fecha->fecha_inicio,
                'horario_id' => null,
                'cancha_id' => null,
            ]);

            $partidos[] = $partido;
        }

        $fecha->partidos = $partidos;

        return response()->json([
            'message' => 'Siguiente ronda creada correctamente',
            'fecha' => $fecha,
            'status' => 201
        ], 201);
    }
}