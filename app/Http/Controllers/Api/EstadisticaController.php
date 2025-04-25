<?php
// app/Http/Controllers/Api/EstadisticaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\EstadisticaServiceInterface;
use Illuminate\Http\Request;

class EstadisticaController extends Controller
{
    protected $estadisticaService;

    public function __construct(EstadisticaServiceInterface $estadisticaService)
    {
        $this->estadisticaService = $estadisticaService;
    }

    public function index()
    {
        return response()->json($this->estadisticaService->getAll(), 200);
    }

    public function show($id)
    {
        $estadistica = $this->estadisticaService->getById($id);

        if (!$estadistica) {
            return response()->json([
                'message' => 'Estadística no encontrada',
                'status' => 404
            ], 404);
        }

        return response()->json($estadistica, 200);
    }

    public function store(Request $request)
    {
        return $this->estadisticaService->create($request);
    }

    public function update(Request $request, $id)
    {
        return $this->estadisticaService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->estadisticaService->delete($id);
    }

    public function getByPartido($partidoId)
    {
        return response()->json($this->estadisticaService->getByPartido($partidoId), 200);
    }

    public function getByEquipo($equipoId)
    {
        return response()->json($this->estadisticaService->getByEquipo($equipoId), 200);
    }

    public function getByJugador($jugadorId)
    {
        return response()->json($this->estadisticaService->getByJugador($jugadorId), 200);
    }

    public function getByZona($zonaId)
    {
        return response()->json($this->estadisticaService->getByZona($zonaId), 200);
    }

    public function getJugadoresStatsByZona($zonaId) 
    {
        return $this->estadisticaService->getJugadoresStatsByZona($zonaId);
    }
}