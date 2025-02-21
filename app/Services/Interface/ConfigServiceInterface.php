<?php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface ConfigServiceInterface
{
    public function configurarHorarios(Request $request);
}