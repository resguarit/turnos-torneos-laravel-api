<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\FechaServiceInterface;
use Illuminate\Http\Request;

class FechaController extends Controller
{
    protected $fechaService;

    public function __construct(FechaServiceInterface $fechaService)
    {
        $this->fechaService = $fechaService;
    }

    public function index()
    {
        return response()->json($this->fechaService->getAll(), 200);
    }

    public function show($id)
    {
        $fecha = $this->fechaService->getById($id);

        if (!$fecha) {
            return response()->json([
                'message' => 'Fecha no encontrada',
                'status' => 404
            ], 404);
        }

        return response()->json($fecha, 200);
    }

    public function store(Request $request)
    {
        return $this->fechaService->create($request);
    }

    public function update(Request $request, $id)
    {
        return $this->fechaService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->fechaService->delete($id);
    }

    public function getByZona($zonaId)
    {
        return response()->json($this->fechaService->getByZona($zonaId), 200);
    }

    public function postergarFechas($fechaId)
    {
        return $this->fechaService->postergarFechas($fechaId);
    }
    public function verificarEstadoFecha($fechaId)
    {
        return $this->fechaService->verificarEstadoFecha($fechaId);
    }
}