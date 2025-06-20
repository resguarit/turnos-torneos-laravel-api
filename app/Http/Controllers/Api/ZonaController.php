<?php
// app/Http/Controllers/Api/ZonaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\ZonaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ZonaController extends Controller
{
    protected $zonaService;

    public function __construct(ZonaServiceInterface $zonaService)
    {
        $this->zonaService = $zonaService;
    }

    public function index()
    {
        return response()->json($this->zonaService->getAll(), 200);
    }

    public function show($id)
    {
        $zona = $this->zonaService->getById($id);

        if (!$zona) {
            return response()->json([
                'message' => 'Zona no encontrada',
                'status' => 404
            ], 404);
        }

        return response()->json($zona, 200);
    }

    public function store(Request $request)
    {
        return $this->zonaService->create($request);
    }

    public function update(Request $request, $id)
    {
        return $this->zonaService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->zonaService->delete($id);
    }

    public function getByTorneo($torneoId)
    {
        return response()->json($this->zonaService->getByTorneo($torneoId), 200);
    }

    public function createFechas(Request $request, $zonaId)
    {
        return $this->zonaService->createFechas($request, $zonaId);
    }

    public function crearGruposAleatoriamente(Request $request, $zonaId)
    {
        try {
            $numGrupos = $request->input('num_grupos');
            $grupos = $this->zonaService->crearGruposAleatoriamente($zonaId, $numGrupos);

            return response()->json([
                'message' => 'Grupos creados correctamente',
                'grupos' => $grupos,
                'status' => 201
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => $e->getCode()
            ], $e->getCode());
        }
    }

    public function reemplazarEquipo(Request $request, $zonaId)
    {
        return $this->zonaService->reemplazarEquipo(
            $zonaId, 
            $request->input('equipo_viejo_id'), 
            $request->input('equipo_nuevo_id')
        );
    }

    public function generarSiguienteRonda(Request $request, $zonaId)
    {
        return $this->zonaService->generarSiguienteRonda($request, $zonaId);
    }

    public function crearPlayoff(Request $request, $zonaId)
    {
        return $this->zonaService->crearPlayoff($request, $zonaId);
    }

    public function agregarEquipos(Request $request, $zonaId)
    {
        $validator = Validator::make($request->all(), [
            'equipo_ids' => 'required|array',
            'equipo_ids.*' => 'required|integer|exists:equipos,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        return $this->zonaService->agregarEquipos($zonaId, $request->input('equipo_ids'));
    }

    public function quitarEquipos(Request $request, $zonaId)
    {
        $validator = Validator::make($request->all(), [
            'equipo_ids' => 'required|array',
            'equipo_ids.*' => 'required|integer|exists:equipos,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        return $this->zonaService->quitarEquipos($zonaId, $request->input('equipo_ids'));
    }

    public function obtenerEstadisticasGrupos($zonaId)
    {
        return $this->zonaService->calcularEstadisticasGrupos($zonaId);
    }

    public function obtenerEstadisticasLiga($zonaId)
    {
        return $this->zonaService->calcularEstadisticasLiga($zonaId);
    }

    public function crearPlayoffEnLiga(Request $request, $zonaId)
    {
        return $this->zonaService->crearPlayoffEnLiga($request, $zonaId);
    }

    public function crearPlayoffEnGrupos(Request $request, $zonaId)
    {
        return $this->zonaService->crearPlayoffEnGrupos($request, $zonaId);
    }
}