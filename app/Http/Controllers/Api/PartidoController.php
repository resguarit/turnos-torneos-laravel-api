<?php
// app/Http/Controllers/Api/PartidoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\PartidoServiceInterface;
use Illuminate\Http\Request;

class PartidoController extends Controller
{
    protected $partidoService;

    public function __construct(PartidoServiceInterface $partidoService)
    {
        $this->partidoService = $partidoService;
    }

    public function index()
    {
        return response()->json($this->partidoService->getAll(), 200);
    }

    public function show($id)
    {
        $partido = $this->partidoService->getById($id);

        if (!$partido) {
            return response()->json([
                'message' => 'Partido no encontrado',
                'status' => 404
            ], 404);
        }

        return response()->json($partido, 200);
    }

    public function store(Request $request)
    {
        return $this->partidoService->create($request);
    }

    public function update(Request $request, $id)
    {
        return $this->partidoService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->partidoService->delete($id);
    }

    public function getByFecha($fechaId)
    {
        return response()->json($this->partidoService->getByFecha($fechaId), 200);
    }

    public function getByEquipo($equipoId)
    {
        return response()->json($this->partidoService->getByEquipo($equipoId), 200);
    }

    public function getByZona($zonaId)
    {
        return response()->json($this->partidoService->getByZona($zonaId), 200);
    }

    public function getByEquipoAndZona($equipoId, $zonaId)
    {
        return response()->json($this->partidoService->getByEquipoAndZona($equipoId, $zonaId), 200);
    }

    public function asignarHoraYCanchaPorZona(Request $request, $zonaId)
    {
        return $this->partidoService->asignarHoraYCanchaPorZona($request, $zonaId);
    }
}