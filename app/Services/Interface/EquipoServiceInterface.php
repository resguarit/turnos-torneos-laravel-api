<?php
// app/Services/Interface/EquipoServiceInterface.php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface EquipoServiceInterface
{
    public function getAll();
    public function getById($id);
    public function create(Request $request);
    public function update(Request $request, $id);
    public function delete($id);
    public function getByZona($zonaId);
}