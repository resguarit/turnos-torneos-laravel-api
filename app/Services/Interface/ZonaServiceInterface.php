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
}