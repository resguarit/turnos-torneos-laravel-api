<?php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface BloqueoTemporalServiceInterface
{
    public function bloquearHorario(Request $request);
    public function cancelarBloqueo(Request $request);
}