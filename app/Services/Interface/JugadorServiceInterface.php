<?php
// app/Services/Interface/JugadorServiceInterface.php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface JugadorServiceInterface
{
    public function getAll();
    public function getById($id);
    public function create(Request $request);
    public function update(Request $request, $id);
    public function delete($id);
    public function getByEquipo($equipoId);
    public function createMultiple(Request $request);
    public function searchByDni(Request $request);
}