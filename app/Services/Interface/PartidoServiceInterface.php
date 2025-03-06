<?php
// app/Services/Interface/PartidoServiceInterface.php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface PartidoServiceInterface
{
    public function getAll();
    public function getById($id);
    public function create(Request $request);
    public function update(Request $request, $id);
    public function delete($id);
    public function getByFecha($fechaId);
    public function getByEquipo($equipoId);
}