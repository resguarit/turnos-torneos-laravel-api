<?php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface TransaccionServiceInterface
{
    public function getTransacciones(Request $request);
    public function storeTransaccion(Request $request);
    public function saldoPorTurno($id);
}

