<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Interface\CuentaCorrienteServiceInterface;
use Illuminate\Support\Facades\Auth;

class CuentaCorrienteController extends Controller
{
    protected $cuentaCorrienteService;

    public function __construct(CuentaCorrienteServiceInterface $cuentaCorrienteService)
    {
        $this->cuentaCorrienteService = $cuentaCorrienteService;
    }

    public function index(Request $request)
    {
        return $this->cuentaCorrienteService->getCuentasCorrientes($request);
    }

    public function show($id)
    {
        return $this->cuentaCorrienteService->getCuentaCorrienteByPersona($id);
    }
}

