<?php
// app/Services/Interface/ZonaServiceInterface.php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface ZonaServiceInterface
{
    public function getAll();
    public function getById($id);
    public function create(Request $request);
    public function update(Request $request, $id);
    public function delete($id);
    public function getByTorneo($torneoId);
    public function createFechas(Request $request, $zonaId);
    public function crearGruposAleatoriamente($zonaId, $numGrupos);
    public function reemplazarEquipo($zonaId, $equipoIdViejo, $equipoIdNuevo);
    public function generarSiguienteRonda(Request $request, $zonaId);
    public function crearPlayoff(Request $request, $zonaId);
    public function agregarEquipos($zonaId, array $equipoIds);
    public function quitarEquipos($zonaId, array $equipoIds);
}