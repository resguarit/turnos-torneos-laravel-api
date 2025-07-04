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
use App\Enums\PartidoEstado;
use App\Services\EstadisticaService;

class ZonaService implements ZonaServiceInterface
{
    public function getAll()
    {
        return Zona::with('equipos', 'fechas')->get();
    }

    public function getById($id)
    {
        return Zona::with('equipos', 'fechas', 'torneo')->find($id);
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
            'nombre' => 'sometimes|string|max:255',
            'formato' => ['sometimes', 'string', Rule::in(ZonaFormato::values())],
            'año' => 'sometimes|integer',
            'activo' => 'sometimes|boolean',
            'torneo_id' => 'sometimes|exists:torneos,id',
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
        if ($zona->formato === ZonaFormato::ELIMINATORIA && !in_array($numEquipos, [2, 4, 8, 16, 32, 64])) {
            return response()->json([
                'message' => 'El número de equipos debe ser 2, 4, 8, 16, 32 o 64 para un torneo eliminatoria',
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
        } elseif ($zona->formato === ZonaFormato::LIGA_PLAYOFF) {
            // Usar la lógica de Liga para la fase de Liga
            $fechas = $this->createFechasLiga($zona, $equipos, $fechaInicial);

            // Aquí podrías agregar lógica adicional para la fase de Playoff si es necesario
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

                // Asociar los equipos al partido en la tabla pivote
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

        // Validar que haya equipos suficientes
        if ($numEquipos < 2) {
            return response()->json([
                'message' => 'El número de equipos debe ser mayor o igual a 2',
                'status' => 400
            ], 400);
        }

        // Convertir los equipos a un array si es una colección
        if ($equipos instanceof \Illuminate\Database\Eloquent\Collection) {
            $equiposArray = $equipos->toArray();
        } else {
            $equiposArray = $equipos;
        }

        // Si el número de equipos es impar, agregar un equipo "libre"
        if ($numEquipos % 2 != 0) {
            $equiposArray[] = ['id' => null, 'nombre' => 'Libre'];
            $numEquipos++;
        }

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

                // Si alguno de los equipos es "Libre", no crear el partido
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

                // Asociar los equipos al partido en la tabla pivote
                $partido->equipos()->attach([$local['id'], $visitante['id']]);

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

                // Si alguno de los equipos es "Libre", no crear el partido
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

                // Asociar los equipos al partido en la tabla pivote
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
                'num_grupos' => $numGrupos,
                'message' => 'El número de grupos debe ser mayor o igual a 2',
                'status' => 400
            ], 400);
        }

        $fechas = [];
        $numFechas = 0;

        // Determinar el número máximo de fechas necesarias para cualquier grupo
        foreach ($zona->grupos as $grupo) {
            $numEquipos = $grupo->equipos->count();
            if ($numEquipos >= 2) {
                $numFechas = max($numFechas, $numEquipos - 1);
            }
        }

        // Inicializar las rotaciones para cada grupo
        $rotaciones = [];
        foreach ($zona->grupos as $grupo) {
            $equipos = $grupo->equipos;
            $numEquipos = $equipos->count();
            $equiposArray = $equipos->toArray();

            // Añadir equipo "Libre" si es impar
            if ($numEquipos % 2 != 0) {
                $equiposArray[] = ['id' => null, 'nombre' => 'Libre'];
            }

            $rotaciones[$grupo->id] = $equiposArray;
        }

        // Generar las fechas
        for ($i = 0; $i < $numFechas; $i++) {
            $fecha = Fecha::create([
                'nombre' => 'Fecha ' . ($i + 1),
                'fecha_inicio' => $fechaInicial->copy()->addWeeks($i),
                'fecha_fin' => $fechaInicial->copy()->addWeeks($i)->addDays(1),
                'estado' => 'Pendiente',
                'zona_id' => $zona->id,
            ]);

            $partidos = [];

            foreach ($zona->grupos as $grupo) {
                $grupoId = $grupo->id;
                if (!isset($rotaciones[$grupoId])) continue;

                $equiposArray = $rotaciones[$grupoId];
                $numEquipos = count($equiposArray);

                if ($numEquipos < 2) continue;

                // Generar partidos para esta fecha
                for ($j = 0; $j < $numEquipos / 2; $j++) {
                    $local = $equiposArray[$j];
                    $visitante = $equiposArray[$numEquipos - 1 - $j];

                    if ($local['id'] === null || $visitante['id'] === null) continue;

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
                    $partidos[] = $partido;
                }

                // Rotar para la próxima fecha (excepto en la última iteración)
                if ($i < $numFechas - 1) {
                    $last = array_pop($equiposArray);
                    array_splice($equiposArray, 1, 0, [$last]);
                    $rotaciones[$grupoId] = $equiposArray;
                }
            }

            $fecha->partidos = $partidos;
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
            $equipoViejo = Equipo::whereHas('zonas', function($query) use ($zonaId) {
                $query->where('zonas.id', $zonaId);
            })->where('id', $equipoIdViejo)->first();

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
            
            // Desasignar el equipo viejo de la zona
            $equipoViejo->zonas()->detach($zonaId);
            
            // Asignar el equipo nuevo a la zona
            $equipoNuevo->zonas()->attach($zonaId);
            
            // Reemplazar el equipo en todos los partidos
            $partidos = Partido::whereHas('fecha', function($query) use ($zonaId) {
                $query->where('zona_id', $zonaId);
            })->where(function($query) use ($equipoIdViejo) {
                $query->where('equipo_local_id', $equipoIdViejo)
                    ->orWhere('equipo_visitante_id', $equipoIdViejo);
            })->get();
            
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
            'winners' => 'required|array|min:2',
            'fecha_anterior_id' => 'required|exists:fechas,id',
            'crear_tercer_puesto' => 'sometimes|boolean', // Nuevo parámetro opcional
            'perdedores' => 'sometimes|array' // Solo requerido si crear_tercer_puesto es true
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

        $winners = $request->input('winners');
        $numEquipos = count($winners);
        $crearTercerPuesto = $request->boolean('crear_tercer_puesto', false);
        $perdedores = $request->input('perdedores', []);

        // Validar que sea potencia de 2
        if (!in_array($numEquipos, [2, 4, 8, 16, 32, 64])) {
            return response()->json([
                'message' => 'El número de equipos debe ser una potencia de 2',
                'status' => 400
            ], 400);
        }

        $fechaAnterior = Fecha::find($request->input('fecha_anterior_id'));
        $fechaFinAnterior = Carbon::parse($fechaAnterior->fecha_fin);

        // Crear nueva fecha
        if ($numEquipos == 2) {
            // Solo crear fechas "Final" y "Tercer Puesto" aquí
            $fechaInicioAnterior = Carbon::parse($fechaAnterior->fecha_inicio);
            // Inicializar el arreglo que almacenará todas las fechas creadas en esta ronda
            $fechasCreadas = [];

            // Si se solicita, crear partido por el tercer puesto en otra fecha
            if ($crearTercerPuesto && count($perdedores) == 2) {
                $terceroLocal = $perdedores[0];
                $terceroVisitante = $perdedores[1];

                $fechaTercerPuesto = Fecha::create([
                    'nombre' => 'Tercer Puesto',
                    'fecha_inicio' => $fechaInicioAnterior->copy()->addDays(7),
                    'fecha_fin' => $fechaInicioAnterior->copy()->addDays(7),
                    'estado' => 'Pendiente',
                    'zona_id' => $zona->id,
                ]);

                $tercerPuesto = Partido::create([
                    'fecha_id' => $fechaTercerPuesto->id,
                    'equipo_local_id' => $terceroLocal,
                    'equipo_visitante_id' => $terceroVisitante,
                    'estado' => 'Pendiente',
                    'fecha' => $fechaTercerPuesto->fecha_inicio,
                    'horario_id' => null,
                    'cancha_id' => null,
                ]);
                $tercerPuesto->equipos()->attach([$terceroLocal, $terceroVisitante]);

                $fechasCreadas[] = $fechaTercerPuesto->load('partidos.equipos');
            }

            $fechaFinal = Fecha::create([
                'nombre' => 'Final',
                'fecha_inicio' => $fechaInicioAnterior->copy()->addDays(7),
                'fecha_fin' => $fechaInicioAnterior->copy()->addDays(7),
                'estado' => 'Pendiente',
                'zona_id' => $zona->id,
            ]);

            $localId = $winners[0];
            $visitanteId = $winners[1];

            $final = Partido::create([
                'fecha_id' => $fechaFinal->id,
                'equipo_local_id' => $localId,
                'equipo_visitante_id' => $visitanteId,
                'estado' => 'Pendiente',
                'fecha' => $fechaFinal->fecha_inicio,
                'horario_id' => null,
                'cancha_id' => null,
            ]);
            $final->equipos()->attach([$localId, $visitanteId]);

            $fechasCreadas[] = $fechaFinal->load('partidos.equipos');

            return response()->json([
                'message' => 'Siguiente ronda creada correctamente',
                'fechas' => $fechasCreadas,
                'status' => 201
            ], 201);
        } else {
            // Solo aquí crear la fecha para el resto de rondas
            $fecha = Fecha::create([
                'nombre' => $this->getNombreEliminatoria($numEquipos),
                'fecha_inicio' => $fechaFinAnterior->copy()->addDays(1),
                'fecha_fin' => $fechaFinAnterior->copy()->addDays(2),
                'estado' => 'Pendiente',
                'zona_id' => $zona->id,
            ]);

            $partidos = [];
            for ($i = 0; $i < $numEquipos; $i += 2) {
                $localId = $winners[$i];
                $visitanteId = $winners[$i + 1] ?? null;

                if (!$visitanteId) break;

                $partido = Partido::create([
                    'fecha_id' => $fecha->id,
                    'equipo_local_id' => $localId,
                    'equipo_visitante_id' => $visitanteId,
                    'estado' => 'Pendiente',
                    'fecha' => $fecha->fecha_inicio,
                    'horario_id' => null,
                    'cancha_id' => null,
                ]);

                $partido->equipos()->attach([$localId, $visitanteId]);
                $partidos[] = $partido;
            }

            return response()->json([
                'message' => 'Siguiente ronda creada correctamente',
                'fecha' => $fecha->load('partidos.equipos'),
                'status' => 201
            ], 201);
        }
    }

    private function getNumeroRonda($numEquipos)
    {
        return match($numEquipos) {
            2 => 1,    // Final
            4 => 2,    // Semifinal
            8 => 3,    // Cuartos
            16 => 4,   // Octavos
            32 => 5,   // Dieciseisavos
            64 => 6    // Treintaidosavos
        };
    }

    public function crearPlayoff(Request $request, $zonaId)
    {
        $validator = Validator::make($request->all(), [
            'equipos' => 'required|array|min:2', // Lista de equipos clasificados
            'fecha_inicial' => 'required|date_format:Y-m-d', // Fecha inicial del playoff
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

        $equipos = $request->input('equipos');
        $numEquipos = count($equipos);

        // Validar que el número de equipos sea una potencia de 2 (2, 4, 8, 16, etc.)
        if (!in_array($numEquipos, [2, 4, 8, 16, 32, 64])) {
            return response()->json([
                'message' => 'El número de equipos debe ser una potencia de 2 (2, 4, 8, 16, etc.)',
                'status' => 400
            ], 400);
        }

        $fechaInicial = Carbon::createFromFormat('Y-m-d', $request->input('fecha_inicial'));

        // Crear la fecha para los cruces
        $nombreFecha = $this->getNombreEliminatoria($numEquipos);

        $fecha = Fecha::create([
            'nombre' => $nombreFecha,
            'fecha_inicio' => $fechaInicial,
            'fecha_fin' => $fechaInicial->copy()->addDays(1),
            'estado' => 'Pendiente',
            'zona_id' => $zona->id,
        ]);

        $partidos = [];

        // Barajar los equipos para generar cruces aleatorios
        shuffle($equipos);

        // Crear los partidos para la fecha
        for ($i = 0; $i < $numEquipos; $i += 2) {
            $local = $equipos[$i];
            $visitante = $equipos[$i + 1] ?? null;

            if (!$visitante) {
                break; // Si no hay un equipo visitante, no se crea el partido
            }

            $partido = Partido::create([
                'fecha_id' => $fecha->id,
                'equipo_local_id' => $local,
                'equipo_visitante_id' => $visitante,
                'estado' => 'Pendiente',
                'fecha' => $fecha->fecha_inicio,
                'horario_id' => null,
                'cancha_id' => null,
            ]);

            // Asociar los equipos al partido en la tabla pivote
            $partido->equipos()->attach([$local, $visitante]);

            $partidos[] = $partido;
        }

        $fecha->partidos = $partidos;

        return response()->json([
            'message' => 'Playoff creado correctamente',
            'fecha' => $fecha->load('partidos.equipos'),
            'status' => 201
        ], 201);
    }

    public function agregarEquipos($zonaId, array $equipoIds)
    {
        try {
            $zona = Zona::findOrFail($zonaId);
            
            // Verificar que los equipos existan
            $equiposExistentes = Equipo::whereIn('id', $equipoIds)->count();
            if ($equiposExistentes !== count($equipoIds)) {
                return response()->json([
                    'message' => 'Uno o más equipos no existen',
                    'status' => 404
                ], 404);
            }

            // Agregar los equipos a la zona
            $zona->equipos()->attach($equipoIds);

            return response()->json([
                'message' => 'Equipos agregados correctamente a la zona',
                'status' => 200
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al agregar equipos a la zona',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function quitarEquipos($zonaId, array $equipoIds)
    {
        try {
            $zona = Zona::findOrFail($zonaId);
            
            // Verificar que los equipos existan
            $equiposExistentes = Equipo::whereIn('id', $equipoIds)->count();
            if ($equiposExistentes !== count($equipoIds)) {
                return response()->json([
                    'message' => 'Uno o más equipos no existen',
                    'status' => 404
                ], 404);
            }

            // Quitar los equipos de la zona
            $zona->equipos()->detach($equipoIds);

            return response()->json([
                'message' => 'Equipos quitados correctamente de la zona',
                'status' => 200
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al quitar equipos de la zona',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function calcularEstadisticasGrupos($zonaId)
    {
        $zona = Zona::with(['grupos.equipos', 'fechas.partidos' => function ($query) {
            $query->where('estado', PartidoEstado::FINALIZADO)
                  ->with(['equipoLocal', 'equipoVisitante']);
        }])->find($zonaId);

        if (!$zona) {
            return response()->json(['message' => 'Zona no encontrada', 'status' => 404], 404);
        }

        if ($zona->formato !== ZonaFormato::GRUPOS) {
             return response()->json(['message' => 'La zona no tiene formato de Grupos', 'status' => 400], 400);
        }

        $estadisticasGrupos = [];

        // Get all finalized partidos for the zone efficiently
        $partidosFinalizados = Partido::where('estado', PartidoEstado::FINALIZADO)
            ->whereHas('fecha', function ($q) use ($zonaId) {
                $q->where('zona_id', $zonaId);
            })
            ->with(['equipoLocal', 'equipoVisitante'])
            ->get();

        foreach ($zona->grupos as $grupo) {
            $grupoData = [
                'id' => $grupo->id,
                'nombre' => $grupo->nombre,
                'zona_id' => $grupo->zona_id,
                'equipos' => [],
            ];

            foreach ($grupo->equipos as $equipo) {
                $stats = [
                    'puntaje' => 0,
                    'partidosJugados' => 0,
                    'partidosGanados' => 0,
                    'partidosEmpatados' => 0,
                    'partidosPerdidos' => 0,
                    'golesFavor' => 0,
                    'golesContra' => 0,
                    'diferenciaGoles' => 0,
                ];

                foreach ($partidosFinalizados as $partido) {
                    $esLocal = $partido->equipo_local_id === $equipo->id;
                    $esVisitante = $partido->equipo_visitante_id === $equipo->id;

                    if ($esLocal || $esVisitante) {
                        $stats['partidosJugados']++;
                        $marcadorLocal = $partido->marcador_local ?? 0;
                        $marcadorVisitante = $partido->marcador_visitante ?? 0;

                        if ($esLocal) {
                            $stats['golesFavor'] += $marcadorLocal;
                            $stats['golesContra'] += $marcadorVisitante;
                            if ($marcadorLocal > $marcadorVisitante) {
                                $stats['puntaje'] += 3;
                                $stats['partidosGanados']++;
                            } elseif ($marcadorLocal === $marcadorVisitante) {
                                $stats['puntaje'] += 1;
                                $stats['partidosEmpatados']++;
                            } else {
                                $stats['partidosPerdidos']++;
                            }
                        } else { // esVisitante
                            $stats['golesFavor'] += $marcadorVisitante;
                            $stats['golesContra'] += $marcadorLocal;
                            if ($marcadorVisitante > $marcadorLocal) {
                                $stats['puntaje'] += 3;
                                $stats['partidosGanados']++;
                            } elseif ($marcadorVisitante === $marcadorLocal) {
                                $stats['puntaje'] += 1;
                                $stats['partidosEmpatados']++;
                            } else {
                                $stats['partidosPerdidos']++;
                            }
                        }
                    }
                }

                $stats['diferenciaGoles'] = $stats['golesFavor'] - $stats['golesContra'];

                $grupoData['equipos'][] = array_merge($equipo->toArray(), $stats);
            }
             // Sort teams within the group by points (desc), then goal difference (desc), then goals for (desc)
            usort($grupoData['equipos'], function ($a, $b) {
                if ($b['puntaje'] !== $a['puntaje']) {
                    return $b['puntaje'] <=> $a['puntaje'];
                }
                if ($b['diferenciaGoles'] !== $a['diferenciaGoles']) {
                    return $b['diferenciaGoles'] <=> $a['diferenciaGoles'];
                }
                return $b['golesFavor'] <=> $a['golesFavor'];
            });


            $estadisticasGrupos[] = $grupoData;
        }

        return response()->json($estadisticasGrupos, 200);
    }

    public function calcularEstadisticasLiga($zonaId)
    {
        $zona = Zona::with('equipos')->find($zonaId);

        if (!$zona) {
            return response()->json(['message' => 'Zona no encontrada', 'status' => 404], 404);
        }

        if (!in_array($zona->formato, [ZonaFormato::LIGA, ZonaFormato::LIGA_PLAYOFF, ZonaFormato::LIGA_IDA_VUELTA])) {
             return response()->json(['message' => 'La zona no tiene formato de Liga o Liga + Playoff o Liga Ida y Vuelta', 'status' => 400], 400);
        }

        $estadisticasEquipos = [];

        // Get all finalized partidos for the zone efficiently
        $partidosFinalizados = Partido::where('estado', PartidoEstado::FINALIZADO)
            ->whereHas('fecha', function ($q) use ($zonaId) {
                $q->where('zona_id', $zonaId);
            })
            ->with(['equipoLocal', 'equipoVisitante'])
            ->get();

        foreach ($zona->equipos as $equipo) {
            $stats = [
                'puntaje' => 0,
                'partidosJugados' => 0,
                'partidosGanados' => 0,
                'partidosEmpatados' => 0,
                'partidosPerdidos' => 0,
                'golesFavor' => 0,
                'golesContra' => 0,
                'diferenciaGoles' => 0,
            ];

            foreach ($partidosFinalizados as $partido) {
                $esLocal = $partido->equipo_local_id === $equipo->id;
                $esVisitante = $partido->equipo_visitante_id === $equipo->id;

                if ($esLocal || $esVisitante) {
                    $stats['partidosJugados']++;
                    $marcadorLocal = $partido->marcador_local ?? 0;
                    $marcadorVisitante = $partido->marcador_visitante ?? 0;

                    if ($esLocal) {
                        $stats['golesFavor'] += $marcadorLocal;
                        $stats['golesContra'] += $marcadorVisitante;
                        if ($marcadorLocal > $marcadorVisitante) {
                            $stats['puntaje'] += 3;
                            $stats['partidosGanados']++;
                        } elseif ($marcadorLocal === $marcadorVisitante) {
                            $stats['puntaje'] += 1;
                            $stats['partidosEmpatados']++;
                        } else {
                            $stats['partidosPerdidos']++;
                        }
                    } else { // esVisitante
                        $stats['golesFavor'] += $marcadorVisitante;
                        $stats['golesContra'] += $marcadorLocal;
                        if ($marcadorVisitante > $marcadorLocal) {
                            $stats['puntaje'] += 3;
                            $stats['partidosGanados']++;
                        } elseif ($marcadorVisitante === $marcadorLocal) {
                            $stats['puntaje'] += 1;
                            $stats['partidosEmpatados']++;
                        } else {
                            $stats['partidosPerdidos']++;
                        }
                    }
                }
            }

            $stats['diferenciaGoles'] = $stats['golesFavor'] - $stats['golesContra'];

            $estadisticasEquipos[] = array_merge($equipo->toArray(), $stats);
        }

        // Sort teams by points (desc), then goal difference (desc), then goals for (desc)
        usort($estadisticasEquipos, function ($a, $b) {
            if ($b['puntaje'] !== $a['puntaje']) {
                return $b['puntaje'] <=> $a['puntaje'];
            }
            if ($b['diferenciaGoles'] !== $a['diferenciaGoles']) {
                return $b['diferenciaGoles'] <=> $a['diferenciaGoles'];
            }
            return $b['golesFavor'] <=> $a['golesFavor'];
        });

        return response()->json($estadisticasEquipos, 200);
    }

    public function crearPlayoffEnLiga(Request $request, $zonaId)
    {
        $zona = Zona::with('equipos', 'torneo')->find($zonaId);
        if (!$zona) {
            return response()->json(['message' => 'Zona no encontrada', 'status' => 404], 404);
        }

        $fechaInicial = $request->input('fecha_inicial');
        if (!$fechaInicial) {
            return response()->json(['message' => 'Debe enviar fecha_inicial', 'status' => 400], 400);
        }

        // Obtener equipos ordenados por puntaje
        $response = $this->calcularEstadisticasLiga($zonaId);
        $equiposOrdenados = $response->getData(true); // Devuelve el array de equipos ordenados

        $numEquipos = count($equiposOrdenados);
        if ($numEquipos < 2) {
            return response()->json(['message' => 'No hay suficientes equipos para playoff', 'status' => 400], 400);
        }

        $mitad = intdiv($numEquipos, 2);
        $ganadores = array_slice($equiposOrdenados, 0, $mitad);
        $perdedores = array_slice($equiposOrdenados, $mitad);

        DB::beginTransaction();
        try {
            // Crear zona de ganadores
            $zonaGanadores = Zona::create([
                'nombre' => $zona->nombre . ' Playoff Ganadores',
                'formato' => ZonaFormato::ELIMINATORIA,
                'año' => $zona->año,
                'torneo_id' => $zona->torneo_id,
            ]);
            $equiposGanadoresIds = array_column($ganadores, 'id');
            $zonaGanadores->equipos()->attach($equiposGanadoresIds);

            // Crear zona de perdedores
            $zonaPerdedores = Zona::create([
                'nombre' => $zona->nombre . ' Playoff Perdedores',
                'formato' => ZonaFormato::ELIMINATORIA,
                'año' => $zona->año,
                'torneo_id' => $zona->torneo_id,
            ]);
            $equiposPerdedoresIds = array_column($perdedores, 'id');
            $zonaPerdedores->equipos()->attach($equiposPerdedoresIds);

            // Crear fecha y partidos para ganadores
            $this->crearFechaPlayoffZona($zonaGanadores, $ganadores, $fechaInicial);

            // Crear fecha y partidos para perdedores
            $this->crearFechaPlayoffZona($zonaPerdedores, $perdedores, $fechaInicial);

            DB::commit();
            return response()->json([
                'message' => 'Playoff en liga creado correctamente',
                'zona_ganadores' => $zonaGanadores->load('equipos', 'fechas.partidos'),
                'zona_perdedores' => $zonaPerdedores->load('equipos', 'fechas.partidos'),
                'status' => 201
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear playoff en liga',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    private function crearFechaPlayoffZona($zona, $equiposStats, $fechaInicial)
    {
        $numEquipos = count($equiposStats);
        if ($numEquipos < 2) return;

        // Usar el nombre de la ronda según la cantidad de equipos
        $nombreFecha = $this->getNombreEliminatoria($numEquipos);

        $fecha = Fecha::create([
            'nombre' => $nombreFecha,
            'fecha_inicio' => $fechaInicial,
            'fecha_fin' => Carbon::parse($fechaInicial)->addDays(1),
            'estado' => 'Pendiente',
            'zona_id' => $zona->id,
        ]);

        $partidos = [];
        for ($i = 0; $i < $numEquipos / 2; $i++) {
            $local = $equiposStats[$i];
            $visitante = $equiposStats[$numEquipos - 1 - $i];

            $partido = Partido::create([
                'fecha_id' => $fecha->id,
                'equipo_local_id' => $local['id'],
                'equipo_visitante_id' => $visitante['id'],
                'estado' => 'Pendiente',
                'fecha' => $fecha->fecha_inicio,
                'horario_id' => null,
                'cancha_id' => null,
            ]);
            $partido->equipos()->attach([$local['id'], $visitante['id']]);
            $partidos[] = $partido;
        }
        $fecha->partidos = $partidos;
    }

    public function crearPlayoffEnGrupos(Request $request, $zonaId)
{
    $zona = Zona::with('grupos.equipos', 'torneo')->find($zonaId);
    if (!$zona) {
        return response()->json(['message' => 'Zona no encontrada', 'status' => 404], 404);
    }

    if ($zona->formato !== ZonaFormato::GRUPOS) {
        return response()->json(['message' => 'La zona no tiene formato de Grupos', 'status' => 400], 400);
    }

    $fechaInicial = $request->input('fecha_inicial');
    $equiposPorGrupo = $request->input('equipos_por_grupo'); // array de arrays de ids de equipos por grupo

    if (!$fechaInicial || !is_array($equiposPorGrupo) || count($equiposPorGrupo) < 2) {
        return response()->json(['message' => 'Debe enviar fecha_inicial y al menos dos grupos de equipos', 'status' => 400], 400);
    }

    $cantidadPorGrupo = count($equiposPorGrupo[0]);
    foreach ($equiposPorGrupo as $grupo) {
        if (count($grupo) !== $cantidadPorGrupo) {
            return response()->json(['message' => 'Todos los grupos deben tener la misma cantidad de equipos seleccionados', 'status' => 400], 400);
        }
    }

    // Obtener estadísticas y ordenar equipos por grupo
    $estadisticasGrupos = $this->calcularEstadisticasGrupos($zonaId)->getData(true);
    $equiposOrdenadosPorGrupo = [];
    foreach ($equiposPorGrupo as $idx => $equipoIds) {
        $grupoStats = $estadisticasGrupos[$idx]['equipos'] ?? [];
        // Filtrar solo los equipos seleccionados y mantener el orden por estadísticas
        $equiposFiltrados = array_filter($grupoStats, function($eq) use ($equipoIds) {
            return in_array($eq['id'], $equipoIds);
        });
        $equiposOrdenadosPorGrupo[] = array_values($equiposFiltrados);
    }

    // Crear la zona de playoff
    $zonaPlayoff = Zona::create([
        'nombre' => $zona->nombre . ' Playoff',
        'formato' => ZonaFormato::ELIMINATORIA,
        'año' => $zona->año,
        'torneo_id' => $zona->torneo_id,
    ]);
    // Asociar todos los equipos seleccionados
    $todosEquipos = array_merge(...$equiposPorGrupo);
    $zonaPlayoff->equipos()->attach($todosEquipos);

    // Crear la fecha y los partidos cruzados
    $nombreFecha = $this->getNombreEliminatoria($cantidadPorGrupo * 2);
    $fecha = Fecha::create([
        'nombre' => $nombreFecha,
        'fecha_inicio' => $fechaInicial,
        'fecha_fin' => \Carbon\Carbon::parse($fechaInicial)->addDays(1),
        'estado' => 'Pendiente',
        'zona_id' => $zonaPlayoff->id,
    ]);

    $partidos = [];
    // Cruces: 1° grupo A vs último grupo B, 2° grupo A vs penúltimo grupo B, etc.
    $grupoA = $equiposOrdenadosPorGrupo[0];
    $grupoB = $equiposOrdenadosPorGrupo[1];
    for ($i = 0; $i < $cantidadPorGrupo; $i++) {
        $local = $grupoA[$i];
        $visitante = $grupoB[$cantidadPorGrupo - 1 - $i];

        $partido = Partido::create([
            'fecha_id' => $fecha->id,
            'equipo_local_id' => $local['id'],
            'equipo_visitante_id' => $visitante['id'],
            'estado' => 'Pendiente',
            'fecha' => $fecha->fecha_inicio,
            'horario_id' => null,
            'cancha_id' => null,
        ]);
        $partido->equipos()->attach([$local['id'], $visitante['id']]);
        $partidos[] = $partido;
    }
    $fecha->partidos = $partidos;

    return response()->json([
        'message' => 'Playoff de grupos creado correctamente',
        'zona_playoff' => $zonaPlayoff->load('equipos', 'fechas.partidos'),
        'status' => 201
    ], 201);
}
}