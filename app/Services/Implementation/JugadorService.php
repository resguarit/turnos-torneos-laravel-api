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
use App\Models\Transaccion;


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
                // Validar que no haya ya un capitán en el equipo si este jugador se marca como capitán
                if ($equipo['capitan']) {
                    $capitanExistente = \DB::table('equipo_jugador')
                        ->where('equipo_id', $equipo['id'])
                        ->where('capitan', true)
                        ->exists();

                    if ($capitanExistente) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Ya existe un capitán en el equipo ID ' . $equipo['id'],
                            'status' => 400
                        ], 400);
                    }
                }
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
                    
                    // Si la cuenta recién se creó, poner saldo negativo. Si ya existía, restar el precio de inscripción.
                    if ($cuentaCorriente->wasRecentlyCreated) {
                        $cuentaCorriente->saldo = -$precioInscripcion;
                    } else {
                        $cuentaCorriente->saldo -= $precioInscripcion;
                    }
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
            // Validar que solo uno del array viene como capitán
            $capitanes = array_filter($jugadoresData, function($jugador) {
                return isset($jugador['capitan']) && $jugador['capitan'];
            });
            if (count($capitanes) > 1) {
                return response()->json([
                    'message' => 'Solo puede haber un capitán por equipo en la carga múltiple.',
                    'status' => 400
                ], 400);
            }

            // Validar que no haya ya un capitán en el equipo
            $capitanExistente = \DB::table('equipo_jugador')
                ->where('equipo_id', $equipoId)
                ->where('capitan', true)
                ->exists();
            if ($capitanExistente && count($capitanes) > 0) {
                return response()->json([
                    'message' => 'Ya existe un capitán en el equipo.',
                    'status' => 400
                ], 400);
            }

            foreach ($jugadoresData as $jugadorData) {
                $capitan = $jugadorData['capitan'];
                unset($jugadorData['capitan']);

                // Crear jugador
                $jugador = Jugador::create($jugadorData);

                // Si es capitán, crear persona y cuenta corriente si no existen
                if ($capitan) {
                    $persona = Persona::firstOrCreate(
                        ['dni' => $jugador->dni],
                        [
                            'name' => $jugador->nombre . ' ' . $jugador->apellido,
                            'telefono' => $jugador->telefono,
                        ]
                    );
                    $equipoModel = Equipo::with('zonas.torneo')->find($equipoId);
                    $torneo = $equipoModel->zonas->first()->torneo ?? null;
                    $precioInscripcion = $torneo ? $torneo->precio_inscripcion : 0;

                    $cuentaCorriente = CuentaCorriente::firstOrCreate(
                        ['persona_id' => $persona->id],
                        ['saldo' => 0]
                    );
                    if ($cuentaCorriente->wasRecentlyCreated) {
                        $cuentaCorriente->saldo = -$precioInscripcion;
                    } else {
                        $cuentaCorriente->saldo -= $precioInscripcion;
                    }
                    $cuentaCorriente->save();
                }

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
    
    public function asociarJugadorAEquipo($jugadorId, $equipoId, $capitan = false)
    {
        $jugador = Jugador::find($jugadorId);
        $equipo = Equipo::find($equipoId);

        if (!$jugador || !$equipo) {
            return response()->json([
                'message' => 'Jugador o equipo no encontrado',
                'status' => 404
            ], 404);
        }

        if ($capitan) {
            $capitanExistente = \DB::table('equipo_jugador')
                ->where('equipo_id', $equipoId)
                ->where('capitan', true)
                ->exists();
            if ($capitanExistente) {
                return response()->json([
                    'message' => 'Ya existe un capitán en el equipo.',
                    'status' => 400
                ], 400);
            }
        }

        $jugador->equipos()->syncWithoutDetaching([$equipoId => ['capitan' => $capitan]]);

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

public function getEquipoJugadorId($equipoId, $jugadorId)
{
    return DB::table('equipo_jugador')
        ->where('equipo_id', $equipoId)
        ->where('jugador_id', $jugadorId)
        ->value('id'); // Devuelve solo el ID
}

public function crearPersonaYCuentaCorrienteSiCapitan($jugadorId, $equipoId, $zonaId)
{
    $jugador = Jugador::find($jugadorId);
    $equipo = Equipo::find($equipoId);
    $zona = Zona::with('torneo')->find($zonaId);

    if (!$jugador || !$equipo || !$zona || !$zona->torneo) {
        return [
            'message' => 'Jugador, equipo o zona (o torneo de la zona) no encontrado',
            'status' => 404
        ];
    }

    // Verificar si es capitán en la tabla pivote
    $esCapitan = \DB::table('equipo_jugador')
        ->where('equipo_id', $equipoId)
        ->where('jugador_id', $jugadorId)
        ->value('capitan');

    if (!$esCapitan) {
        return [
            'message' => 'El jugador no es capitán en este equipo',
            'status' => 200
        ];
    }

    // Crear persona si no existe
    $persona = Persona::firstOrCreate(
        ['dni' => $jugador->dni],
        [
            'name' => $jugador->nombre . ' ' . $jugador->apellido,
            'telefono' => $jugador->telefono,
        ]
    );

    $precioInscripcion = $zona->torneo->precio_inscripcion ?? 0;

    // Crear cuenta corriente si no existe y setear saldo negativo
    $cuentaCorriente = CuentaCorriente::firstOrCreate(
        ['persona_id' => $persona->id],
        ['saldo' => 0]
    );

    // Si la cuenta recién se creó, poner saldo negativo. Si ya existía, restar el precio de inscripción.
    if ($cuentaCorriente->wasRecentlyCreated) {
        $cuentaCorriente->saldo = -$precioInscripcion;
    } else {
        $cuentaCorriente->saldo -= $precioInscripcion;
    }
    $cuentaCorriente->save();

    return [
        'message' => 'Persona y cuenta corriente verificadas/creadas para el capitán',
        'persona_id' => $persona->id,
        'cuenta_corriente_id' => $cuentaCorriente->id,
        'saldo' => $cuentaCorriente->saldo,
        'status' => 201
    ];
}

public function cambiarCapitan($equipoId, $jugadorNuevoId, $zonaId)
{
    $equipo = Equipo::with('zonas.torneo')->find($equipoId);
    $jugadorNuevo = Jugador::find($jugadorNuevoId);
    $zona = Zona::with('torneo')->find($zonaId);

    if (!$equipo || !$jugadorNuevo || !$zona || !$zona->torneo) {
        return [
            'message' => 'Datos no encontrados',
            'status' => 404
        ];
    }

    $torneo = $zona->torneo;
    $precioInscripcion = $torneo->precio_inscripcion ?? 0;

    // Buscar el jugador actual capitán del equipo
    $jugadorActualId = DB::table('equipo_jugador')
        ->where('equipo_id', $equipoId)
        ->where('capitan', true)
        ->value('jugador_id');

    // Verificar que el nuevo jugador no sea ya capitán
    $esCapitanNuevo = DB::table('equipo_jugador')
        ->where('equipo_id', $equipoId)
        ->where('jugador_id', $jugadorNuevoId)
        ->value('capitan');

    if ($esCapitanNuevo) {
        return [
            'message' => 'El jugador nuevo ya es capitán',
            'status' => 400
        ];
    }

    DB::beginTransaction();
    try {
        if ($jugadorActualId) {
            // Hay capitán actual, hacer el cambio
            if ($jugadorActualId == $jugadorNuevoId) {
                return [
                    'message' => 'El jugador seleccionado ya es el capitán',
                    'status' => 400
                ];
            }

            // Cambiar el capitán en la tabla pivote
            DB::table('equipo_jugador')
                ->where('equipo_id', $equipoId)
                ->where('jugador_id', $jugadorActualId)
                ->update(['capitan' => false]);
            DB::table('equipo_jugador')
                ->where('equipo_id', $equipoId)
                ->where('jugador_id', $jugadorNuevoId)
                ->update(['capitan' => true]);
        } else {
            // No hay capitán actual, simplemente asignar el nuevo
            DB::table('equipo_jugador')
                ->where('equipo_id', $equipoId)
                ->where('jugador_id', $jugadorNuevoId)
                ->update(['capitan' => true]);
        }

        // Crear persona y cuenta corriente para el nuevo capitán si no existen
        $personaNuevo = Persona::firstOrCreate(
            ['dni' => $jugadorNuevo->dni],
            [
                'name' => $jugadorNuevo->nombre . ' ' . $jugadorNuevo->apellido,
                'telefono' => $jugadorNuevo->telefono,
            ]
        );
        $cuentaCorrienteNuevo = CuentaCorriente::firstOrCreate(
            ['persona_id' => $personaNuevo->id],
            ['saldo' => 0]
        );

        // Buscar si existe pago de inscripción para este torneo en la cuenta corriente del capitán anterior
        $pagoInscripcion = null;
        $cuentaCorrienteActual = null;
        if ($jugadorActualId) {
            $jugadorActual = Jugador::find($jugadorActualId);
            $personaActual = Persona::where('dni', $jugadorActual->dni)->first();
            $cuentaCorrienteActual = $personaActual
                ? CuentaCorriente::where('persona_id', $personaActual->id)->first()
                : null;

            if ($cuentaCorrienteActual) {
                $pagoInscripcion = Transaccion::where('torneo_id', $torneo->id)
                    ->where('tipo', 'inscripcion')
                    ->where('cuenta_corriente_id', $cuentaCorrienteActual->id)
                    ->first();
            }
        } else {
            // Si no hay capitán anterior, buscar si existe algún pago de inscripción para este equipo y torneo
            $pagoInscripcion = Transaccion::where('torneo_id', $torneo->id)
                ->where('tipo', 'inscripcion')
                ->where('cuenta_corriente_id', $cuentaCorrienteNuevo->id)
                ->first();
        }

        if ($pagoInscripcion) {
            // Si ya se pagó la inscripción, la cuenta corriente del nuevo capitán queda en 0 si recién se crea
            if ($cuentaCorrienteNuevo->wasRecentlyCreated) {
                $cuentaCorrienteNuevo->saldo = 0;
                $cuentaCorrienteNuevo->save();
            }
        } else {
            // Si no se pagó la inscripción, se descuenta el precio de inscripción al nuevo capitán
            if ($cuentaCorrienteNuevo->wasRecentlyCreated) {
                $cuentaCorrienteNuevo->saldo = -$precioInscripcion;
            } else {
                $cuentaCorrienteNuevo->saldo -= $precioInscripcion;
            }
            $cuentaCorrienteNuevo->save();

            // Registrar transacción de deuda al nuevo capitán
            Transaccion::create([
                'cuenta_corriente_id' => $cuentaCorrienteNuevo->id,
                'monto' => -$precioInscripcion,
                'tipo' => 'saldo',
                'descripcion' => "Asignación de deuda de inscripción por cambio de capitán en torneo {$torneo->nombre} ({$torneo->id})",
                'torneo_id' => $torneo->id
            ]);

            // Devolver el saldo al capitán anterior si corresponde y registrar transacción
            if ($cuentaCorrienteActual) {
                $cuentaCorrienteActual->saldo += $precioInscripcion;
                $cuentaCorrienteActual->save();

                Transaccion::create([
                    'cuenta_corriente_id' => $cuentaCorrienteActual->id,
                    'monto' => $precioInscripcion,
                    'tipo' => 'saldo',
                    'descripcion' => "Eliminación de deuda de inscripción por cambio de capitán en torneo {$torneo->nombre} ({$torneo->id})",
                    'torneo_id' => $torneo->id
                ]);
            }
        }

        DB::commit();
        return [
            'message' => $jugadorActualId
                ? 'Cambio de capitán realizado correctamente'
                : 'Capitán asignado correctamente',
            'status' => 200
        ];
    } catch (\Exception $e) {
        DB::rollBack();
        return [
            'message' => 'Error al cambiar capitán',
            'error' => $e->getMessage(),
            'status' => 500
        ];
    }
}


}