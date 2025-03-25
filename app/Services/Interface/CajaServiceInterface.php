<?php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface CajaServiceInterface
{
    public function abrirCaja(Request $request);
    public function cerrarCaja(Request $request);
    public function getCaja();
    public function index();
}