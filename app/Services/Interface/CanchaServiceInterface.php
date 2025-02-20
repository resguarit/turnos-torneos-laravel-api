<?php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface CanchaServiceInterface
{
    public function getCanchas();
    public function showCancha($id);
    public function storeCancha(Request $request);
    public function updateCancha(Request $request, $id);
    public function deleteCancha($id);
}