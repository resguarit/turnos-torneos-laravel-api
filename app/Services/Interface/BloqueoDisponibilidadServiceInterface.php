<?php

namespace App\Services\Interface;

use Illuminate\Http\Request;
 
interface BloqueoDisponibilidadServiceInterface
{
    public function bloquearDisponibilidad(Request $request);
    public function desbloquearDisponibilidad(Request $request);
    public function getAll();
} 