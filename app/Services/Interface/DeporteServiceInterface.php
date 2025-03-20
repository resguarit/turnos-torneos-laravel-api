<?php
// app/Services/Interface/DeporteServiceInterface.php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface DeporteServiceInterface
{
    public function getAll();
    public function getById($id);
    public function create(Request $request);
    public function update(Request $request, $id);
    public function delete($id);
}