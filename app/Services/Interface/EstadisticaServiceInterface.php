<?php
// app/Services/Interface/EstadisticaServiceInterface.php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface EstadisticaServiceInterface
{
    public function getAll();
    public function getById($id);
    public function create(Request $request);
    public function update(Request $request, $id);
    public function delete($id);
    public function getByPartido($partidoId);
    public function getByEquipo($equipoId);
    public function getByJugador($jugadorId);
    public function getByZona($zonaId);
    public function createOrUpdateMultiple(Request $request, $partidoId);
    public function getJugadoresStatsByZona($zonaId);
}