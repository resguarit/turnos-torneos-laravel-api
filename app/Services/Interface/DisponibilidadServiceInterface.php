<?php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface DisponibilidadServiceInterface
{
    public function getHorariosNoDisponibles();
    public function getHorariosDisponiblesPorFecha(Request $request);
    public function getCanchasPorHorarioFecha(Request $request);
    public function getDiasNoDisponibles();
}