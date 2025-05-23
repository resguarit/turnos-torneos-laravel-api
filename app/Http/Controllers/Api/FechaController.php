<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interface\FechaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

    public function destroyMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha_ids' => 'required|array',
            'fecha_ids.*' => 'required|integer|exists:fechas,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
                'status' => 400
            ], 400);
        }

        return $this->fechaService->deleteMultiple($request->fecha_ids);
    }
}