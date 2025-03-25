<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Interface\CajaServiceInterface;

class CajaController extends Controller
{

    protected $cajaService;

    public function __construct(CajaServiceInterface $cajaService)
    {
        $this->cajaService = $cajaService;
    }

    public function abrirCaja(Request $request)
    {
        return $this->cajaService->abrirCaja($request);
    }

    public function cerrarCaja(Request $request)
    {
        return $this->cajaService->cerrarCaja($request);
    }

    public function getCaja()
    {
        return $this->cajaService->getCaja();
    }

    public function index()
    {
        return $this->cajaService->index();
    }
}
