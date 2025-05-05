<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Implementation\PagoService;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    protected $pagoService;

    public function __construct(PagoService $pagoService)
    {
        $this->pagoService = $pagoService;
    }

    public function registrarPagoInscripcion($equipoId, $torneoId, $metodoPagoId)
    {
        return response()->json(
            $this->pagoService->registrarPagoInscripcion($equipoId, $torneoId, $metodoPagoId)
        );
    }
    
    public function registrarPagoPorFecha($fechaId, $metodoPagoId)
    {
        return response()->json(
            $this->pagoService->registrarPagoPorFecha($fechaId, $metodoPagoId)
        );
    }

    public function obtenerPagoInscripcion($equipoId, $torneoId)
    {
        return response()->json(
            $this->pagoService->obtenerPagoInscripcion($equipoId, $torneoId)
        );
    }

    public function obtenerPagoPorFecha($equipoId, $zonaId)
    {
        return response()->json(
            $this->pagoService->obtenerPagoPorFecha($equipoId, $zonaId)
        );
    }
}