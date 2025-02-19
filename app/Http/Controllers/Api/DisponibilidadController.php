<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Interface\DisponibilidadServiceInterface;

class DisponibilidadController extends Controller
{
    protected $disponibilidadService;

    public function __construct(DisponibilidadServiceInterface $disponibilidadService)
    {
        $this->disponibilidadService = $disponibilidadService;
    }

    public function getHorariosNoDisponibles()
    {
        return $this->disponibilidadService->getHorariosNoDisponibles();
    }

    public function getHorariosDisponiblesPorFecha(Request $request)
    {
        return $this->disponibilidadService->getHorariosDisponiblesPorFecha($request);
    }

    public function getCanchasPorHorarioFecha(Request $request)
    {
        return $this->disponibilidadService->getCanchasPorHorarioFecha($request);
    }
}