<?php
// app/Services/Implementation/GrupoService.php

namespace App\Services\Implementation;

use App\Models\Grupo;
use App\Models\Zona;
use App\Services\Interface\GrupoServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GrupoService implements GrupoServiceInterface
{
    public function getAll()
    {
        return Grupo::with('equipos', 'zona')->get();
    }

    public function getById($id)
    {
        return Grupo::with('equipos', 'zona')->find($id);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'zona_id' => 'required|exists:zonas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $grupo = Grupo::create($request->all());

        return response()->json([
            'message' => 'Grupo creado correctamente',
            'grupo' => $grupo,
            'status' => 201
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $grupo = Grupo::find($id);

        if (!$grupo) {
            return response()->json([
                'message' => 'Grupo no encontrado',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'zona_id' => 'required|exists:zonas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        $grupo->update($request->all());

        return response()->json([
            'message' => 'Grupo actualizado correctamente',
            'grupo' => $grupo,
            'status' => 200
        ], 200);
    }

    public function delete($id)
    {
        $grupo = Grupo::find($id);

        if (!$grupo) {
            return response()->json([
                'message' => 'Grupo no encontrado',
                'status' => 404
            ], 404);
        }

        $grupo->delete();

        return response()->json([
            'message' => 'Grupo eliminado correctamente',
            'status' => 200
        ], 200);
    }

    public function getByZona($zonaId)
    {
        return Grupo::where('zona_id', $zonaId)->with('equipos')->get();
    }
    
    public function eliminarEquipoDeGrupo($grupoId, $equipoId)
    {
        try {
            $grupo = Grupo::findOrFail($grupoId);
            $equipo = $grupo->equipos()->findOrFail($equipoId);

            // Eliminar la relación entre el grupo y el equipo
            $grupo->equipos()->detach($equipoId);

            return [
                'message' => 'Equipo eliminado del grupo correctamente',
                'status' => 200,
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'message' => 'Grupo o equipo no encontrado',
                'status' => 404,
            ];
        } catch (\Exception $e) {
            return [
                'message' => 'Error al eliminar el equipo del grupo',
                'error' => $e->getMessage(),
                'status' => 500,
            ];
        }
    }

    public function eliminarGruposDeZona($zonaId)
    {
        try {
            // Buscar los grupos de la zona
            $grupos = Grupo::where('zona_id', $zonaId)->get();

            if ($grupos->isEmpty()) {
                return response()->json([
                    'message' => 'No se encontraron grupos para la zona especificada',
                    'status' => 404
                ], 404);
            }

            // Eliminar los grupos
            foreach ($grupos as $grupo) {
                $grupo->delete();
            }

            return response()->json([
                'message' => 'Todos los grupos de la zona han sido eliminados correctamente',
                'status' => 200
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar los grupos de la zona',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function agregarEquipoAGrupo($grupoId, $equipoId)
    {
        try {
            $grupo = Grupo::findOrFail($grupoId);
            $equipoYaEnGrupo = $grupo->equipos()->where('equipo_id', $equipoId)->exists();

            if ($equipoYaEnGrupo) {
                return [
                    'message' => 'El equipo ya pertenece a este grupo',
                    'status' => 400,
                ];
            }

            // Asociar el equipo al grupo
            $grupo->equipos()->attach($equipoId);

            return [
                'message' => 'Equipo agregado al grupo correctamente',
                'status' => 200,
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'message' => 'Grupo o equipo no encontrado',
                'status' => 404,
            ];
        } catch (\Exception $e) {
            return [
                'message' => 'Error al agregar el equipo al grupo',
                'error' => $e->getMessage(),
                'status' => 500,
            ];
        }
    }

    public function actualizarEquiposDeGrupo($grupoId, array $equipoIds)
    {
        try {
            $grupo = Grupo::findOrFail($grupoId);

            // Actualizar los equipos asociados al grupo
            $grupo->equipos()->sync($equipoIds);

            return [
                'message' => 'Equipos del grupo actualizados correctamente',
                'status' => 200,
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'message' => 'Grupo no encontrado',
                'status' => 404,
            ];
        } catch (\Exception $e) {
            return [
                'message' => 'Error al actualizar los equipos del grupo',
                'error' => $e->getMessage(),
                'status' => 500,
            ];
        }
    }
}