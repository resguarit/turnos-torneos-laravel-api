<?php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface TurnoServiceInterface
{
    public function getTurnos(Request $request);
    public function getAllTurnos();
    public function storeTurnoUnico(Request $request);
    public function storeTurnoFijo(Request $request);
    public function storeTurnoPersona(Request $request);
    public function updateTurno(Request $request, $id);
    public function deleteTurno($id);
    public function restoreTurno($id);
    public function showTurno($id);
    public function gridTurnos(Request $request);
    public function getTurnosByUser($userId);
    public function getProximosTurnos();
    public function cancelTurno($id, Request $request);
}