<?php
// app/Services/Implementation/JugadorService.php

namespace App\Services\Implementation;

use App\Models\Jugador;
use App\Services\Interface\JugadorServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Enums\FechaEstado;
use App\Models\CuentaCorriente;
use App\Models\Equipo;
use App\Models\Zona;
use App\Models\Persona;


class JugadorService implements JugadorServiceInterface
{
    public function getAll()
    {
        // Eager load the 'equipos' relationship
        return Jugador::with('equipos')->get();
    }

    public function getById($id)
    {
        // Eager load the 'equipos' relationship
        return Jugador::with('equipos')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'dni' => 'required|string|max:20|unique:jugadores,dni',
            'telefono' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'required|date',
            'equipos' => 'required|array',
            'equipos.*.id' => 'required|exists:equipos,id',
            'equipos.*.capitan' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        DB::beginTransaction();
        try {
            $jugador = Jugador::create($request->except('equipos'));

            $equiposPivot = [];
            foreach ($request->input('equipos') as $equipo) {
                $equiposPivot[$equipo['id']] = ['capitan' => $equipo['capitan']];

                // Si es capitán, crear persona y cuenta corriente si no existen
                if ($equipo['capitan']) {
                    $persona = Persona::firstOrCreate(
                        ['dni' => $jugador->dni],
                        [
                            'name' => $jugador->nombre . ' ' . $jugador->apellido,
                            'telefono' => $jugador->telefono,
                        ]
                    );
                    $equipoModel = Equipo::find($equipo['id']);
                    $torneo = $equipoModel->zonas->first()->torneo ?? null; // Asume que el equipo pertenece a una zona y torneo
                    $precioInscripcion = $torneo ? $torneo->precio_inscripcion : 0;

                    // Crear la cuenta corriente con saldo inicial negativo
                    $cuentaCorriente = CuentaCorriente::firstOrCreate(
                        ['persona_id' => $persona->id],
                        ['saldo' => 0] 
                    );
                    
                    $cuentaCorriente->saldo -= $precioInscripcion;
                    $cuentaCorriente->save();
                }
            }
            $jugador->equipos()->attach($equiposPivot);

            DB::commit();

            return response()->json([
                'message' => 'Jugador creado correctamente',
                'jugador' => $jugador->load('equipos'),
                'status' => 201
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el jugador o asociar equipos.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
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
            'nombre' => 'sometimes|required|string|max:255',
            'apellido' => 'sometimes|required|string|max:255',
            'dni' => 'sometimes|required|string|max:20|unique:jugadores,dni,' . $id,
            'telefono' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'sometimes|required|date',
            'equipos' => 'sometimes|array',
            'equipos.*.id' => 'required_with:equipos|exists:equipos,id',
            'equipos.*.capitan' => 'required_with:equipos|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        DB::beginTransaction();
        try {
            $jugador->update($request->except('equipos'));

            if ($request->has('equipos')) {
                $equiposPivot = [];
                foreach ($request->input('equipos') as $equipo) {
                    $equiposPivot[$equipo['id']] = ['capitan' => $equipo['capitan']];
                }
                $jugador->equipos()->sync($equiposPivot);
            }

            DB::commit();

            return response()->json([
                'message' => 'Jugador actualizado correctamente',
                'jugador' => $jugador->load('equipos'),
                'status' => 200
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar el jugador o asociar equipos.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
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

        // Detach from all teams (optional, cascade delete on pivot might handle this)
        // $jugador->equipos()->detach();

        $jugador->delete();

        return response()->json([
            'message' => 'Jugador eliminado correctamente',
            'status' => 200
        ], 200);
    }

    public function getByEquipo($equipoId)
    {
        // Find the team and load its players
        $equipo = Equipo::with('jugadores')->find($equipoId);
        return $equipo ? $equipo->jugadores : collect(); // Return players or empty collection
    }

    public function getByZona($zonaId)
    {
        // Find players belonging to teams that are associated with the given zona
        return Jugador::whereHas('equipos.zonas', function ($query) use ($zonaId) {
            $query->where('zonas.id', $zonaId);
        })->with(['equipos' => function($q) use ($zonaId) {
            // Optionally filter the loaded teams to only the relevant one(s) for the zone
            $q->whereHas('zonas', function($zq) use ($zonaId) {
                $zq->where('zonas.id', $zonaId);
            });
        }])->get();
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
            'jugadores.*.capitan' => 'required|boolean', // Nuevo: validar capitan por jugador
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
        $createdJugadores = [];

        DB::beginTransaction();
        try {
            foreach ($jugadoresData as $jugadorData) {
                // Extraer y quitar 'capitan' del array para crear el jugador
                $capitan = $jugadorData['capitan'];
                unset($jugadorData['capitan']);

                // Crear jugador
                $jugador = Jugador::create($jugadorData);

                // Asociar al equipo con el campo capitan en el pivote
                $jugador->equipos()->attach($equipoId, ['capitan' => $capitan]);
                $createdJugadores[] = $jugador->load('equipos');
            }
            DB::commit();
            return response()->json([
                'message' => 'Jugadores creados y asociados correctamente',
                'jugadores' => $createdJugadores,
                'status' => 201
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear jugadores múltiples.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function searchByDni(Request $request)
    {
        $dniQuery = $request->query('dni');
        $zonaId = $request->query('zona_id');

        if (!$dniQuery) {
            return response()->json([], 200);
        }

        // Si no se pasa zona_id, comportamiento original
        if (!$zonaId) {
            $jugadores = Jugador::where('dni', 'like', $dniQuery . '%')
                ->with('equipos')
                ->limit(10)
                ->get();
            return response()->json($jugadores, 200);
        }

        // Obtener el torneo de la zona
        $zona = Zona::with('torneo')->find($zonaId);
        if (!$zona || !$zona->torneo) {
            return response()->json([], 200);
        }
        $torneoId = $zona->torneo->id;

        // Obtener equipos de ese torneo
        $equiposTorneoIds = Equipo::whereHas('zonas', function($q) use ($torneoId) {
            $q->where('torneo_id', $torneoId);
        })->pluck('id');

        // Jugadores que NO están en ningún equipo de ese torneo
        $jugadores = Jugador::where('dni', 'like', $dniQuery . '%')
            ->whereDoesntHave('equipos', function($q) use ($equiposTorneoIds) {
                $q->whereIn('equipos.id', $equiposTorneoIds);
            })
            ->with('equipos')
            ->limit(10)
            ->get();

        return response()->json($jugadores, 200);
    }
    
    public function asociarJugadorAEquipo($jugadorId, $equipoId)
    {
        $jugador = Jugador::find($jugadorId);
        $equipo = Equipo::find($equipoId);

        if (!$jugador || !$equipo) {
            return response()->json([
                'message' => 'Jugador o equipo no encontrado',
                'status' => 404
            ], 404);
        }

        // Asociar el jugador al equipo (no duplica si ya existe)
        $jugador->equipos()->syncWithoutDetaching([$equipoId]);

        return response()->json([
            'message' => 'Jugador asociado correctamente al equipo',
            'status' => 200
        ], 200);
    }

    public function getInfoJugadorByDni($dni)
{
    $jugador = Jugador::where('dni', $dni)
        ->with([
            'equipos.zonas.torneo',
            'equipos.zonas.fechas.partidos.equipoLocal',
            'equipos.zonas.fechas.partidos.equipoVisitante',
            'equipos.zonas.fechas.partidos.cancha',
            'equipos.zonas.fechas.partidos.horario'
        ])
        ->first();

    if (!$jugador) {
        return response()->json(['message' => 'Jugador no encontrado'], 404);
    }

    $equipos = $jugador->equipos->map(function ($equipo) {
        $torneosZonas = $equipo->zonas->map(function ($zona) use ($equipo) {
            $torneo = $zona->torneo;
            
            $fechaPendiente = $zona->fechas
                ->where('estado', FechaEstado::PENDIENTE->value)
                ->sortBy('fecha_inicio')
                ->first();

            if (!$fechaPendiente) return null;

            $partido = $fechaPendiente->partidos->first(function ($partido) use ($equipo) {
                return $partido->equipo_local_id == $equipo->id 
                    || $partido->equipo_visitante_id == $equipo->id;
            });

            if (!$partido) return null;

            return [
                'torneo' => $torneo->only('id', 'nombre', 'descripcion'),
                'zona' => $zona->only('id', 'nombre'),
                'primera_fecha_pendiente' => [
                    'fecha' => $fechaPendiente->only('id', 'nombre', 'fecha_inicio', 'estado'),
                    'partido' => [
                        'id' => $partido->id,
                        'equipo_local' => optional($partido->equipoLocal)->nombre ?? 'Sin definir',
                        'equipo_visitante' => optional($partido->equipoVisitante)->nombre ?? 'Sin definir',
                        'cancha' => $partido->cancha ? [
                            'nro' => $partido->cancha->nro,
                            'tipo' => $partido->cancha->tipo_cancha
                        ] : null,
                        'horario' => $partido->horario ? [
                            'inicio' => $partido->horario->hora_inicio,
                            'fin' => $partido->horario->hora_fin
                        ] : null,
                        'fecha_partido' => $partido->fecha
                    ]
                ]
            ];
        })->filter()->values();

        return [
            'equipo' => $equipo->only('id', 'nombre'),
            'competencias' => $torneosZonas
        ];
    });

    return response()->json([
        'jugador' => $jugador->only('id', 'nombre', 'dni'),
        'equipos' => $equipos
    ]);
}
}