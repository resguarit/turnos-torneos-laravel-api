<?php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface CuentaCorrienteServiceInterface
{
    public function getCuentaCorrienteByPersona($personaId);

    public function getCuentasCorrientes(Request $request);

    public function getMiCuentaCorriente();

    public function agregarTransaccion(Request $request);
}

