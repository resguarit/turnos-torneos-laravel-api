<?php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface HorarioServiceInterface
{
    public function getHorarios();
    public function showHorario($id);
    public function storeHorario(Request $request);
    public function deleteHorario($id);
    public function getHorariosPorDiaSemana(Request $request);
    public function deshabilitarFranjaHoraria(Request $request);
    public function habilitarFranjaHoraria(Request $request);
    public function showFranjasHorariasNoDisponibles(Request $request);
    public function getHorariosExtremosActivos(Request $request);
}