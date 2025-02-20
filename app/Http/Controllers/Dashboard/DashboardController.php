<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Interface\DashboardServiceInterface;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardServiceInterface $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function totalReservas()
    {
        return $this->dashboardService->totalReservas();
    }

    public function usuariosActivos()
    {
        return $this->dashboardService->usuariosActivos();
    }

    public function ingresos()
    {
        return $this->dashboardService->ingresos();
    }

    public function tasaOcupacion()
    {
        return $this->dashboardService->tasaOcupacion();
    }

    public function canchaMasPopular()
    {
        return $this->dashboardService->canchaMasPopular();
    }

    public function horasPico()
    {
        return $this->dashboardService->horasPico();
    }

    public function reservasPorMes()
    {
        return $this->dashboardService->reservasPorMes();
    }
}